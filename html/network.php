<?php

/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/
include 'header.php' ?>

<div class="container">
    <h2>Network Interfaces</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Interface Name</th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Get network interfaces excluding specific ones
            $interfaces = [];
            
            // Get all interfaces using ip command
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
                    if (strpos($current_interface, 'docker') === 0 || 
                        strpos($current_interface, 'br-') === 0 ||
                        strpos($current_interface, 'veth') === 0) {
                        $current_interface = null;
                        continue;
                    }
                    
                    $interface_data[$current_interface] = [
                        'name' => $current_interface,
                        'ip' => '',
                        'mac' => '',
                        'status' => 'down'
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
            
            // Display the filtered interfaces
            foreach ($interface_data as $interface) {
                if (!empty($interface['ip']) || !empty($interface['mac'])) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($interface['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($interface['ip']) . "</td>";
                    echo "<td>" . htmlspecialchars($interface['mac']) . "</td>";
                    echo "<td>" . htmlspecialchars($interface['status']) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php' ?>