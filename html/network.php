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
                'dns' => $_POST['dns'] ?? '',
                'multicast' => $_POST['multicast'] ?? 'off'
            ];

            $network_config[$interface] = $config;
            file_put_contents($config_file, json_encode($network_config, JSON_PRETTY_PRINT));
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
            <div class="network-settings-container">
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
                            <!-- Activation toggle button -->
                            <div class="activation-buttons">
                                <button type="submit" name="action" value="toggle" 
                                        class="btn btn-<?php echo $interface_data[$selected_interface]['status'] === 'up' ? 'warning' : 'success'; ?>">
                                    <?php echo $interface_data[$selected_interface]['status'] === 'up' ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </div>
                            
                            <form method="post" action="" class="interface-form">
                                <input type="hidden" name="interface" value="<?php echo htmlspecialchars($selected_interface); ?>">
                                <input type="hidden" name="action" value="save">

                                <div class="mb-3">
                                    <label class="form-label">Configuration Method</label>
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
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" name="ip"
                                        value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['ip'] ?? ''); ?>"
                                        placeholder="192.168.1.100">

                                    <label class="form-label mt-2">Netmask</label>
                                    <input type="text" class="form-control" name="netmask"
                                        value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['netmask'] ?? ''); ?>"
                                        placeholder="255.255.255.0">

                                    <label class="form-label mt-2">Gateway</label>
                                    <input type="text" class="form-control" name="gateway"
                                        value="<?php echo htmlspecialchars($interface_data[$selected_interface]['config']['gateway'] ?? ''); ?>"
                                        placeholder="192.168.1.1">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Multicast</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="multicast" id="multicast-on-<?php echo $selected_interface; ?>"
                                            value="on" <?php echo ($interface_data[$selected_interface]['config']['multicast'] ?? 'off') === 'on' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="multicast-on-<?php echo $selected_interface; ?>">
                                            Enable
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="multicast" id="multicast-off-<?php echo $selected_interface; ?>"
                                            value="off" <?php echo ($interface_data[$selected_interface]['config']['multicast'] ?? 'off') === 'off' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="multicast-off-<?php echo $selected_interface; ?>">
                                            Disable
                                        </label>
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

    // Tab switching functionality
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const interfaceName = this.getAttribute('data-interface');
            window.location.href = '?interface=' + encodeURIComponent(interfaceName);
        });
    });
</script>

<?php include 'footer.php' ?>