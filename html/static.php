<?php

/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/

function generateRandomString($length = 16)
{
    $bytes = random_bytes(ceil($length / 2));
    $randomString = bin2hex($bytes);
    return substr($randomString, 0, $length);
}
function setptsFromMs($ms)
{
    // convert ms → seconds
    $sec = $ms / 1000;

    // format with up to 3 decimals (avoid scientific notation)
    $secFormatted = number_format($sec, 3, '.', '');

    return 'setpts=PTS+' . $secFormatted . '/TB';
}

function adelayFromMs($ms, $channels = 2)
{
    // build "ms|ms|ms..." pattern for each audio channel
    $parts = array_fill(0, $channels, (string)$ms);
    $pattern = implode('|', $parts);

    return 'adelay=' . $pattern;
}

function deleteDir(string $dir): void
{
    if (!is_dir($dir)) return;

    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($dir);
}

function find_first_physical_ethernet(): ?string
{
    foreach (scandir('/sys/class/net') as $iface) {
        if ($iface === '.' || $iface === '..' || $iface === 'lo') {
            continue;
        }

        $net = "/sys/class/net/$iface";

        if (!is_link("$net/device")) {
            continue;
        }

        $type = @trim(file_get_contents("$net/type"));
        if ($type !== '1') {
            continue;
        }

        if (is_dir("$net/wireless")) {
            continue;
        }

        if (is_dir("$net/bridge")) {
            continue;
        }

        $addrAssignType = @trim(file_get_contents("$net/addr_assign_type"));
        if ($addrAssignType !== '0') {
            continue;
        }
        return $iface;
    }
    return null;
}

function build_interface(array $cfg, string $type): array
{
    $out = [];

    /* ---------- IPv4 ---------- */
    if ($cfg['mode'] === 'dhcp') {
        $out['dhcp4'] = true;
    } elseif ($cfg['mode'] === 'static') {
        $out['dhcp4'] = false;

        if ($cfg["network_{$type}_ip"] !== '') {
            $out['addresses'][] = $cfg["network_{$type}_ip"]; // already CIDR
        }

        if ($cfg["network_{$type}_gateway"] !== '') {
            $out['gateway4'] = $cfg["network_{$type}_gateway"];
        }

        $dns = array_filter([
            $cfg["network_{$type}_dns1"],
            $cfg["network_{$type}_dns2"]
        ]);

        if ($dns) {
            $out['nameservers']['addresses'] = array_values($dns);
        }
    } else {
        $out['dhcp4'] = false;
    }

    /* ---------- IPv6 ---------- */
    if ($cfg['modev6'] === 'auto') {
        $out['dhcp6'] = true;
        $out['accept-ra'] = true;
    } elseif ($cfg['modev6'] === 'dhcpv6') {
        $out['dhcp6'] = true;
        $out['accept-ra'] = false;
    } elseif ($cfg['modev6'] === 'static') {
        $out['dhcp6'] = false;
        $out['accept-ra'] = false;

        if (
            $cfg["network_{$type}_ipv6"] !== '' &&
            $cfg["network_{$type}_ipv6_prefix"] !== ''
        ) {
            $out['addresses'][] =
                $cfg["network_{$type}_ipv6"] . '/' .
                $cfg["network_{$type}_ipv6_prefix"];
        }

        if ($cfg["network_{$type}_ipv6_gateway"] !== '') {
            $out['gateway6'] = $cfg["network_{$type}_ipv6_gateway"];
        }

        $dns6 = array_filter([
            $cfg["network_{$type}_ipv6_dns1"],
            $cfg["network_{$type}_ipv6_dns2"]
        ]);

        if ($dns6) {
            $out['nameservers']['addresses'] =
                array_merge($out['nameservers']['addresses'] ?? [], $dns6);
        }
    } else {
        $out['dhcp6'] = false;
        $out['accept-ra'] = false;
    }

    return $out;
}

function generate_netplan(array $data, string $iface): array
{
    $netplan = [
        'network' => [
            'version' => 2,
            'renderer' => 'networkd',
            'ethernets' => [],
            'vlans' => []
        ]
    ];

    /* ---------- BASE INTERFACE (PRIMARY FIRST) ---------- */
    if (
        $data['primary']['mode'] !== 'disabled' ||
        $data['primary']['modev6'] !== 'disabled'
    ) {
        $base_vlan = trim($data['primary']['network_primary_vlan'] ?? '');

        if ($base_vlan === '') {
            // Configure base NIC
            $netplan['network']['ethernets'][$iface] =
                build_interface($data['primary'], 'primary');
        }
    }

    /* ---------- BASE INTERFACE (SECONDARY ONLY IF NOT SET) ---------- */
    if (
        !isset($netplan['network']['ethernets'][$iface]) &&
        (
            $data['secondary']['mode'] !== 'disabled' ||
            $data['secondary']['modev6'] !== 'disabled'
        )
    ) {
        $base_vlan = trim($data['secondary']['network_secondary_vlan'] ?? '');

        if ($base_vlan === '') {
            $netplan['network']['ethernets'][$iface] =
                build_interface($data['secondary'], 'secondary');
        }
    }

    /* ---------- VLANs (PRIMARY) ---------- */
    $p_vlan = trim($data['primary']['network_primary_vlan'] ?? '');
    if ($p_vlan !== '') {
        // Ensure base interface exists
        $netplan['network']['ethernets'][$iface] ??= new stdClass();

        $netplan['network']['vlans']["{$iface}.{$p_vlan}"] =
            array_merge(
                ['id' => (int)$p_vlan, 'link' => $iface],
                build_interface($data['primary'], 'primary')
            );
    }

    /* ---------- VLANs (SECONDARY) ---------- */
    $s_vlan = trim($data['secondary']['network_secondary_vlan'] ?? '');
    if ($s_vlan !== '') {
        $netplan['network']['ethernets'][$iface] ??= new stdClass();

        $netplan['network']['vlans']["{$iface}.{$s_vlan}"] =
            array_merge(
                ['id' => (int)$s_vlan, 'link' => $iface],
                build_interface($data['secondary'], 'secondary')
            );
    }

    /* ---------- Normalize vlans ---------- */
    if (empty($netplan['network']['vlans'])) {
        $netplan['network']['vlans'] = new stdClass();
    }

    return $netplan;
}

function validate_config(array $data): bool
{
    $p_enabled = (
        $data['primary']['mode'] !== 'disabled' ||
        $data['primary']['modev6'] !== 'disabled'
    );

    $s_enabled = (
        $data['secondary']['mode'] !== 'disabled' ||
        $data['secondary']['modev6'] !== 'disabled'
    );

    $p_vlan = trim($data['primary']['network_primary_vlan'] ?? '');
    $s_vlan = trim($data['secondary']['network_secondary_vlan'] ?? '');

    /* If both enabled → at least one VLAN required */
    if ($p_enabled && $s_enabled && $p_vlan === '' && $s_vlan === '') {
        echo "<script>alert('Primary and Secondary are enabled, but no VLAN is defined.');</script>";
        return false;
    }

    /* Block duplicate VLAN IDs */
    if ($p_vlan !== '' && $s_vlan !== '' && $p_vlan === $s_vlan) {
        echo "<script>alert('Primary and Secondary cannot use the same VLAN ID.');</script>";
        return false;
    }

    return true;
}


function netplan_yaml(array $data, int $indent = 0): string
{
    $out = '';
    $pad = str_repeat('  ', $indent);

    foreach ($data as $key => $value) {

        if ($value instanceof stdClass) {
            $out .= "{$pad}{$key}: {}\n";
            continue;
        }

        if (is_bool($value)) {
            $out .= "{$pad}{$key}: " . ($value ? 'true' : 'false') . "\n";
            continue;
        }

        if (!is_array($value)) {
            $out .= "{$pad}{$key}: {$value}\n";
            continue;
        }

        if (array_keys($value) === range(0, count($value) - 1)) {
            $out .= "{$pad}{$key}:\n";
            foreach ($value as $item) {
                $out .= "{$pad}  - {$item}\n";
            }
            continue;
        }

        $out .= "{$pad}{$key}:\n";
        $out .= netplan_yaml($value, $indent + 1);
    }

    return $out;
}
