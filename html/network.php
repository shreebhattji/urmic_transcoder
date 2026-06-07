<?php
/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/
include 'header.php';

// Load network configuration
$config_file = '/etc/urmi/network.json';
$network_config = [];

if (file_exists($config_file)) {
    $config_data = file_get_contents($config_file);
    $network_config = json_decode($config_data, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $interface = $_POST['interface'] ?? '';
        $action = $_POST['action'];

        if ($action === 'save') {
            // Save configuration
            $config = [
                'interface' => $interface,
                'method' => $_POST['method'] ?? '',
                'ip' => $_POST['ip'] ?? '',
                'netmask' => $_POST['netmask'] ?? '',
                'gateway' => $_POST['gateway'] ?? '',
                'dns' => $_POST['dns'] ?? ''
            ];

            $network_config[$interface] = $config;
            file_put_contents($config_file, json_encode($network_config, JSON_PRETTY_PRINT));
        } elseif ($action === 'activate') {
            // Activate interface
            exec("sudo ip link set $interface up", $output, $return_code);
        } elseif ($action === 'deactivate') {
            // Deactivate interface
            exec("sudo ip link set $interface down", $output, $return_code);
        } elseif ($action === 'save_general') {
            // Save general settings
            $general_config = [
                'hostname' => $_POST['hostname'] ?? '',
                'dns_servers' => $_POST['dns_servers'] ?? '',
                'ntp_servers' => $_POST['ntp_servers'] ?? '',
                'proxy' => $_POST['proxy'] ?? ''
            ];

            $network_config['general'] = $general_config;
            file_put_contents($config_file, json_encode($network_config, JSON_PRETTY_PRINT));
        }
    }
}

// Get network interfaces excluding specific ones
$interfaces = [];
$output = [];
exec('ip addr show', $output);

$current_interface = null;
$interface_data = [];

foreach ($output as $line) {
    // Match interface name
    if (preg_match('/^\d+:\s+([a-zA-Z0-9]+):/', $line, $matches)) {
        $current_interface = $matches[1];

        // Skip interfaces we want to exclude
        if (strpos($current_interface, 'enx') === 0) {
            $current_interface = null;
            continue;
        }

        if ($current_interface === 'lo') {
            $current_interface = null;
            continue;
        }

        // Check if interface is a bridge or docker interface
        if (
            strpos($current_interface, 'docker') === 0 ||
            strpos($current_interface, 'br-') === 0 ||
            strpos($current_interface, 'veth') === 0
        ) {
            $current_interface = null;
            continue;
        }

        $interface_data[$current_interface] = [
            'name' => $current_interface,
            'ip' => '',
            'mac' => '',
            'status' => 'down',
            'config' => $network_config[$current_interface] ?? null
        ];
    }

    // Extract IP address
    if ($current_interface && preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
        $interface_data[$current_interface]['ip'] = $matches[1];
    }

    // Extract MAC address
    if ($current_interface && preg_match('/link\/ether\s+([a-f0-9:]+)/', $line, $matches)) {
        $interface_data[$current_interface]['mac'] = $matches[1];
    }

    // Check if interface is up
    if ($current_interface && strpos($line, 'state UP') !== false) {
        $interface_data[$current_interface]['status'] = 'up';
    }
}

// Get general settings
$general_config = $network_config['general'] ?? [
    'hostname' => '',
    'dns_servers' => '',
    'ntp_servers' => '',
    'proxy' => ''
];
?>

<div class="container">
    <h2>Network Configuration</h2>

    <!-- Main container for network settings -->
    <div class="network-settings-container">
        <!-- Tabs for different sections -->
        <ul class="nav nav-tabs" id="networkTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                    General Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="interfaces-tab" data-bs-toggle="tab" data-bs-target="#interfaces" type="button" role="tab">
                    Interfaces
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="networkTabContent">
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>General Network Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="save_general">

                            <div class="mb-3">
                                <label for="hostname" class="form-label">Hostname</label>
                                <input type="text" class="form-control" id="hostname" name="hostname"
                                    value="<?php echo htmlspecialchars($general_config['hostname']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="dns_servers" class="form-label">DNS Servers (comma separated)</label>
                                <input type="text" class="form-control" id="dns_servers" name="dns_servers"
                                    value="<?php echo htmlspecialchars($general_config['dns_servers']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="ntp_servers" class="form-label">NTP Servers (comma separated)</label>
                                <input type="text" class="form-control" id="ntp_servers" name="ntp_servers"
                                    value="<?php echo htmlspecialchars($general_config['ntp_servers']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="proxy" class="form-label">Proxy Server</label>
                                <input type="text" class="form-control" id="proxy" name="proxy"
                                    value="<?php echo htmlspecialchars($general_config['proxy']); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary">Save General Settings</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Interfaces Tab -->
            <div class="tab-pane fade" id="interfaces" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Network Interfaces</h5>
                    </div>
                    <div class="card-body">
                        <!-- Interface Tabs -->
                        <ul class="nav nav-tabs interface-tabs" id="interfaceTabs" role="tablist">
                            <?php $first = true;
                            foreach ($interface_data as $interface): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $first ? 'active' : ''; ?>"
                                        id="tab-<?php echo $interface['name']; ?>"
                                        data-bs-toggle="tab"
                                        data-bs-target="#<?php echo $interface['name']; ?>"
                                        type="button"
                                        role="tab">
                                        <?php echo htmlspecialchars($interface['name']); ?>
                                    </button>
                                </li>
                            <?php $first = false;
                            endforeach; ?>
                        </ul>

                        <!-- Interface Tab Content -->
                        <div class="tab-content interface-tab-content" id="interfaceTabContent">
                            <?php $first = true;
                            foreach ($interface_data as $interface): ?>
                                <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>"
                                    id="<?php echo $interface['name']; ?>"
                                    role="tabpanel">
                                    <div class="interface-settings">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>IP Address:</strong> <?php echo htmlspecialchars($interface['ip'] ?: 'N/A'); ?></p>
                                                <p><strong>MAC Address:</strong> <?php echo htmlspecialchars($interface['mac'] ?: 'N/A'); ?></p>
                                                <p><strong>Status:</strong>
                                                    <span class="badge bg-<?php echo $interface['status'] === 'up' ? 'success' : 'secondary'; ?>">
                                                        <?php echo htmlspecialchars($interface['status']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <form method="post" action="">
                                                    <input type="hidden" name="interface" value="<?php echo htmlspecialchars($interface['name']); ?>">
                                                    <input type="hidden" name="action" value="save">
                                                    <div class="mb-3">
                                                        <label class="form-label">Configuration Method</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="method" id="dhcp-<?php echo $interface['name']; ?>"
                                                                value="dhcp" <?php echo ($interface['config']['method'] ?? '') === 'dhcp' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="dhcp-<?php echo $interface['name']; ?>">
                                                                DHCP
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="method" id="static-<?php echo $interface['name']; ?>"
                                                                value="static" <?php echo ($interface['config']['method'] ?? '') === 'static' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="static-<?php echo $interface['name']; ?>">
                                                                Static IP
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3" id="static-ip-fields-<?php echo $interface['name']; ?>"
                                                        style="<?php echo ($interface['config']['method'] ?? '') === 'static' ? 'display: block;' : 'display: none;'; ?>">
                                                        <label class="form-label">IP Address</label>
                                                        <input type="text" class="form-control" name="ip"
                                                            value="<?php echo htmlspecialchars($interface['config']['ip'] ?? ''); ?>"
                                                            placeholder="192.168.1.100">
                                                        <label class="form-label mt-2">Netmask</label>
                                                        <input type="text" class="form-control" name="netmask"
                                                            value="<?php echo htmlspecialchars($interface['config']['netmask'] ?? ''); ?>"
                                                            placeholder="255.255.255.0">
                                                        <label class="form-label mt-2">Gateway</label>
                                                        <input type="text" class="form-control" name="gateway"
                                                            value="<?php echo htmlspecialchars($interface['config']['gateway'] ?? ''); ?>"
                                                            placeholder="192.168.1.1">
                                                        <label class="form-label mt-2">DNS Server</label>
                                                        <input type="text" class="form-control" name="dns"
                                                            value="<?php echo htmlspecialchars($interface['config']['dns'] ?? ''); ?>"
                                                            placeholder="8.8.8.8">
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                                                        <div>
                                                            <button type="submit" name="action" value="activate"
                                                                class="btn btn-success <?php echo $interface['status'] === 'up' ? 'disabled' : ''; ?>">
                                                                Activate
                                                            </button>
                                                            <button type="submit" name="action" value="deactivate"
                                                                class="btn btn-danger <?php echo $interface['status'] === 'down' ? 'disabled' : ''; ?>">
                                                                Deactivate
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php $first = false;
                            endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle static IP fields based on method selection
    document.querySelectorAll('input[name^="method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const interfaceName = this.name.replace('method', '');
            const staticFields = document.getElementById(`static-ip-fields-${interfaceName}`);

            if (this.value === 'static') {
                staticFields.style.display = 'block';
            } else {
                staticFields.style.display = 'none';
            }
        });
    });
</script>

<?php include 'footer.php' ?>