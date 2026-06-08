<?php
/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/
include 'header.php';

// Load network configuration
$config_file = '/var/www/network.json';
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
        $multicast = isset($_POST['multicast']) ? 'on' : 'off';

        if ($action === 'save') {
            // Save configuration
            $config = [
                'interface' => $interface,
                'method' => $_POST['method'] ?? '',
                'ip' => $_POST['ip'] ?? '',
                'gateway' => $_POST['gateway'] ?? '',
                'dns' => $_POST['dns'] ?? '',
                'multicast' => $multicast
            ];

            $network_config[$interface] = $config;
            file_put_contents($config_file, json_encode($network_config, JSON_PRETTY_PRINT));

            // Generate netplan configuration
            generate_netplan_config($network_config);
        } elseif ($action === 'toggle') {
            // Toggle interface state
            $current_status = $interface_data[$interface]['status'] ?? 'down';
            if ($current_status === 'up') {
                exec("sudo ip link set $interface down", $output, $return_code);
            } else {
                exec("sudo ip link set $interface up", $output, $return_code);
            }
        }
    }
}

// Generate netplan configuration file
function generate_netplan_config($config)
{
    // Create backup of cloud-init configuration
    $cloud_init_file = '/etc/netplan/50-cloud-init.yaml';
    $backup_file = '/var/www/50-cloud-init.yaml_backup';
    $source_file = '/var/www/50-cloud-init.yaml';

    exec('sudo cp /etc/netplan/50-cloud-init.yaml /var/www/50-cloud-init.yaml_backup');


    $netplan_content = "network:\n  version: 2\n  ethernets:\n";

    foreach ($config as $interface => $settings) {
        // Skip virtual interfaces and loopback
        if (
            strpos($interface, 'enx') === 0 ||
            strpos($interface, 'docker') === 0 ||
            strpos($interface, 'br-') === 0 ||
            strpos($interface, 'veth') === 0 ||
            $interface === 'lo'
        ) {
            continue;
        }

        $netplan_content .= "    $interface:\n";

        switch ($settings['method']) {
            case 'dhcp':
                $netplan_content .= "      dhcp4: true\n";
                break;
            case 'static':
                $netplan_content .= "      addresses:\n        - " . $settings['ip'] . "/24\n";
                if (!empty($settings['gateway'])) {
                    $netplan_content .= "      gateway4: " . $settings['gateway'] . "\n";
                }
                if (!empty($settings['dns'])) {
                    $netplan_content .= "      nameservers:\n        addresses:\n          - " . $settings['dns'] . "\n";
                }
                break;
            case 'disable':
            default:
                $netplan_content .= "      dhcp4: false\n";
                break;
        }
    }

    // Write to netplan file
    file_put_contents('/var/www/50-cloud-init.yaml', $netplan_content);

    // Apply netplan configuration with validation
    $output = [];
    $return_code = 0;

    // Run netplan try to validate configuration
    exec('sudo cp /var/www/50-cloud-init.yaml /etc/netplan/50-cloud-init.yaml', $output, $return_code);
    exec('sudo netplan try', $output, $return_code);

    if ($return_code !==  0) {
        if (file_exists($backup_file)) {
            exec('sudo cp /var/www/50-cloud-init.yaml_backup /etc/netplan/50-cloud-init.yaml', $output, $return_code);
            exec('sudo netplan apply', $output, $return_code);
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

// Get selected interface from GET parameter or first interface
$selected_interface = $_GET['interface'] ?? array_keys($interface_data)[0] ?? null;
?>

<div class="containerindex">
    <div class="grid">
        <div class="card wide">
            <h3>Network Configuration</h3>

            <!-- Interface selection tabs -->
            <div class="interface-tabs">
                <div class="interface-list">
                    <?php foreach ($interface_data as $interface): ?>
                        <button type="button"
                            class="tab-button <?php echo $selected_interface === $interface['name'] ? 'active' : ''; ?>"
                            data-interface="<?php echo htmlspecialchars($interface['name']); ?>">
                            <?php echo htmlspecialchars($interface['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main container for network settings -->
            <?php if ($selected_interface && isset($interface_data[$selected_interface])): ?>
                <div class="interface-card">
                    <div class="interface-header">
                        <h5>Interface Settings</h5>
                        <span class="badge bg-<?php echo $interface_data[$selected_interface]['status'] === 'up' ? 'success' : 'secondary'; ?>">
                            <?php echo htmlspecialchars($interface_data[$selected_interface]['status']); ?>
                        </span>
                    </div>
                    <div class="interface-body">
                        <p><strong>IP Address:</strong> <?php echo htmlspecialchars($interface_data[$selected_interface]['ip'] ?: 'N/A'); ?></p>
                        <p><strong>MAC Address:</strong> <?php echo htmlspecialchars($interface_data[$selected_interface]['mac'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="interface-footer">
                        <form method="post" action="" class="interface-form">
                            <input type="hidden" name="interface" value="<?php echo htmlspecialchars($selected_interface); ?>">
                            <input type="hidden" name="action" value="save">

                            <div class="mb-3">
                                <label class="form-label">Interface Name</label>
                                <input type="text" class="form-control" name="interface"
                                    value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['interface'] ?? $selected_interface); ?>"
                                    placeholder="Enter interface name">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Multicast</label>
                                <div class="switch-container">
                                    <label class="switch">
                                        <input
                                            type="checkbox"
                                            class="multicast-toggle"
                                            name="multicast"
                                            value="on"
                                            <?php echo (($interface_data[$selected_interface]['config']['multicast'] ?? 'off') === 'on') ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="switch-label">
                                        <?php echo (($interface_data[$selected_interface]['config']['multicast'] ?? 'off') === 'on') ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Configuration Method</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="method" id="disable-<?php echo $selected_interface; ?>"
                                        value="disable" <?php echo ($interface_data[$selected_interface]['config']['method'] ?? '') === 'disable' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="disable-<?php echo $selected_interface; ?>">
                                        Disable
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="method" id="dhcp-<?php echo $selected_interface; ?>"
                                        value="dhcp" <?php echo ($interface_data[$selected_interface]['config']['method'] ?? '') === 'dhcp' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="dhcp-<?php echo $selected_interface; ?>">
                                        DHCP
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="method" id="static-<?php echo $selected_interface; ?>"
                                        value="static" <?php echo ($interface_data[$selected_interface]['config']['method'] ?? '') === 'static' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="static-<?php echo $selected_interface; ?>">
                                        Static IP
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="static-ip-fields-<?php echo $selected_interface; ?>"
                                style="<?php echo ($interface_data[$selected_interface]['config']['method'] ?? '') === 'static' ? 'display: block;' : 'display: none;'; ?>">
                                <div class="input-group">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" name="ip"
                                        value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['ip'] ?? ''); ?>"
                                        placeholder="192.168.1.100">
                                </div>

                                <div class="input-group">
                                    <label class="form-label mt-2">Gateway</label>
                                    <input type="text" class="form-control" name="gateway"
                                        value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['gateway'] ?? ''); ?>"
                                        placeholder="192.168.1.1">
                                </div>

                                <div class="input-group">
                                    <label class="form-label mt-2">DNS Server</label>
                                    <input type="text" class="form-control" name="dns"
                                        value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['dns'] ?? ''); ?>"
                                        placeholder="8.8.8.8">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">Save Configuration</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No network interfaces found or selected.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Toggle static IP fields based on method selection
    document.querySelectorAll('input[name="method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Get the interface name from the radio button's ID
            const interfaceName = this.id.split('-')[1]; // Get interface name from ID like "static-eth0"
            const staticFields = document.getElementById(`static-ip-fields-${interfaceName}`);

            if (this.value === 'static') {
                staticFields.style.display = 'block';
            } else {
                staticFields.style.display = 'none';
            }
        });
    });

    // Tab switching functionality
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const interfaceName = this.getAttribute('data-interface');
            window.location.href = '?interface=' + encodeURIComponent(interfaceName);
        });
    });

    // Multicast toggle switch functionality
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.multicast-toggle').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const switchContainer = this.closest('.switch-container');
                const label = switchContainer.querySelector('.switch-label');

                if (label) {
                    label.textContent = this.checked ? 'Enabled' : 'Disabled';
                }
            });
        });
    });
</script>

<?php include 'footer.php' ?>