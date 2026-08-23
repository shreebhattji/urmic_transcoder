<?php

/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/

require_once __DIR__ . '/require_login.php';
$coreFile = "/var/www/core.json";

function getInterfaceIp($interfaceName)
{
    $networkFile = "/var/www/network.json";
    if (!file_exists($networkFile)) {
        return "";
    }

    $networkData = json_decode(file_get_contents($networkFile), true);
    if (!is_array($networkData)) {
        return "";
    }

    // First check by interface name
    if (isset($networkData[$interfaceName])) {
        $interfaceData = $networkData[$interfaceName];
        if (isset($interfaceData["method"]) && $interfaceData["method"] === "static" && isset($interfaceData["ip"])) {
            // Return just the IP part (remove subnet mask)
            $ip = explode('/', $interfaceData["ip"])[0];
            return $ip;
        }
    }

    // Then check by interface nickname
    foreach ($networkData as $interfaceKey => $interfaceData) {
        if (isset($interfaceData["interface_nickname"]) && $interfaceData["interface_nickname"] === $interfaceName) {
            if (isset($interfaceData["method"]) && $interfaceData["method"] === "static" && isset($interfaceData["ip"])) {
                // Return just the IP part (remove subnet mask)
                $ip = explode('/', $interfaceData["ip"])[0];
                return $ip;
            }
        }
    }

    return "";
}

function loadCoreState(): array
{
    global $coreFile;
    if (!file_exists($coreFile)) {
        return ["cursor" => 0, "allocations" => []];
    }

    $state = json_decode(file_get_contents($coreFile), true);
    return is_array($state) ? $state : ["cursor" => 0, "allocations" => []];
}

function saveCoreState(array $state): void
{
    global $coreFile;
    file_put_contents($coreFile, json_encode($state, JSON_PRETTY_PRINT));
}

function parseCpuList(string $cpuList): array
{
    $cpus = [];

    foreach (explode(',', $cpuList) as $part) {
        if (strpos($part, '-') !== false) {
            [$start, $end] = array_map('intval', explode('-', $part));
            for ($i = $start; $i <= $end; $i++) {
                $cpus[] = $i;
            }
        } else {
            $cpus[] = (int)$part;
        }
    }

    sort($cpus);
    return $cpus;
}

function buildNumaPlan(): array
{
    $nodes = [];
    $nodePaths = glob('/sys/devices/system/node/node*', GLOB_ONLYDIR);

    foreach ($nodePaths as $nodePath) {
        $nodeId = (int)str_replace('node', '', basename($nodePath));
        $cpuList = trim(file_get_contents("$nodePath/cpulist"));
        $nodes[$nodeId] = parseCpuList($cpuList);
    }

    ksort($nodes);
    $nodeIds = array_keys($nodes);

    $finalPlan = [];
    $maxCpus = max(array_map('count', $nodes));
    
    for ($i = 0; $i < $maxCpus; $i++) {
        foreach ($nodeIds as $nid) {
            if (isset($nodes[$nid][$i])) {
                $finalPlan[] = [
                    "node" => $nid,
                    "cpu"  => $nodes[$nid][$i],
                ];
            }
        }
    }

    return $finalPlan;
}

function allocateCore(int $serviceId): array
{
    $state = loadCoreState();

    if (isset($state["allocations"][$serviceId])) {
        return $state["allocations"][$serviceId];
    }

    $plan = buildNumaPlan();
    $planCount = count($plan);

    $occupied = [];
    foreach ($state["allocations"] as $a) {
        $occupied[$a["node"] . ":" . $a["cpu"]] = true;
    }

    foreach ($plan as $index => $slot) {
        $key = $slot["node"] . ":" . $slot["cpu"];
        if (!isset($occupied[$key])) {
            $state["allocations"][$serviceId] = $slot;
            $state["cursor"] = ($index + 1) % $planCount;
            saveCoreState($state);
            return $slot;
        }
    }

    $slot = $plan[$state["cursor"] % $planCount];
    $state["allocations"][$serviceId] = $slot;
    $state["cursor"] = ($state["cursor"] + 1) % $planCount;

    saveCoreState($state);
    return $slot;
}

function freeCore(int $serviceId): void
{
    $state = loadCoreState();
    if (isset($state["allocations"][$serviceId])) {
        unset($state["allocations"][$serviceId]);
        saveCoreState($state);
    }
}

function updateEncoderScriptAndService(array $service): void
{
    $id = intval($service["id"]);
    $alloc = allocateCore($id);
    $core = (int)$alloc["cpu"];
    $node = (int)$alloc["node"];
    $inputIp = "";
    $outputIp = "";

    if (isset($service["input_interface"]) && $service["input_interface"] !== "none") {
        $inputIp = getInterfaceIp($service["input_interface"]);
    }

    if (isset($service["output_interface"]) && $service["output_interface"] !== "none") {
        $outputIp = getInterfaceIp($service["output_interface"]);
    }

    $ffmpeg = 'numactl --cpunodebind=' . $node
        . ' --preferred=' . $node
        . ' taskset -c ' . $core
        . ' ffmpeg -hide_banner -loglevel quiet -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer '
        . ' -i "udp://' . $service["input_udp"] . '?reuse=1&fifo_size=8192&buffer_size=262144&overrun_nonfatal=1&timeout=5000000';

    if ($inputIp != "")
        $ffmpeg .= '&localaddr=' . $inputIp;
    $ffmpeg .= '" -vf "scale=' . $service["resolution"] . ',format=yuv420p" '
        . ' -c:v ' . $service["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 7 '
        . ' -b:v ' . $service["video_bitrate"] . 'k -minrate ' . max(0, $service["video_bitrate"] - 500) . 'k -maxrate ' . ($service["video_bitrate"] + 500) . 'k -bufsize ' .  ($service["video_bitrate"] + 500) . 'k '
        . ' -c:a ' . $service["audio_format"] . ' -b:a ' . $service["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $service["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
        . ' -metadata service_provider="ShreeBhattJI" ';
    if (isset($service["service_name"]) && $service["service_name"] !== "") {
        $ffmpeg .= '-metadata service_name="' . $service["service_name"] . '" ';
    }
    $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $service["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1';
    if ($outputIp != "")
        $ffmpeg .= '&localaddr=' . $outputIp;

    $ffmpeg .= '"';

    file_put_contents("/var/www/encoder/$id.sh", $ffmpeg);

    if (isset($service["service"]) && $service["service"] === "enable") {
        exec("sudo systemctl enable encoder@$id");
        exec("sudo systemctl restart encoder@$id");
    } else {
        exec("sudo systemctl stop encoder@$id");
        exec("sudo systemctl disable encoder@$id");
    }
}

function all_service_update()
{
    unlink("/var/www/core.json");
    $script = __DIR__ . "/stop_all_encoders.sh";
    exec("sudo chmod +x " . $script);
    exec("sudo {$script} 2>&1", $output, $code);

    $jsonFile = __DIR__ . "/input.json";
    if (!file_exists($jsonFile)) {
        die("input.json not found");
    }
    $data = json_decode(file_get_contents($jsonFile), true);

    if (!is_array($data)) {
        die("Invalid JSON format");
    }

    foreach ($data as &$new) {
        $alloc = allocateCore($new["id"]);
        $core = (int)$alloc["cpu"];
        $node = (int)$alloc["node"];

        $inputIp = "";
        $outputIp = "";

        if (isset($new["input_interface"]) && $new["input_interface"] !== "none") {
            $inputIp = getInterfaceIp($new["input_interface"]);
        }

        if (isset($new["output_interface"]) && $new["output_interface"] !== "none") {
            $outputIp = getInterfaceIp($new["output_interface"]);
        }

        $ffmpeg = 'numactl --cpunodebind=' . $node
            . ' --preferred=' . $node
            . ' taskset -c ' . $core
            . ' ffmpeg -hide_banner -loglevel quiet -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer '
            . ' -i "udp://' . $new["input_udp"] . '?reuse=1&fifo_size=8192&buffer_size=262144&overrun_nonfatal=1&timeout=5000000';

        if ($inputIp != "")
            $ffmpeg .= '&localaddr=' . $inputIp;
        $ffmpeg .= '" -vf "scale=' . $new["resolution"] . ',format=yuv420p" '
            . ' -c:v ' . $new["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 7 '
            . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize ' .  ($new["video_bitrate"] + 500) . 'k '
            . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
            . ' -metadata service_provider="ShreeBhattJI" ';
        if ($new["service_name"] !== "") {
            $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
        }
        $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1';
        if ($outputIp != "")
            $ffmpeg .= '&localaddr=' . $outputIp;

        $ffmpeg .= '"';
        file_put_contents("/var/www/encoder/" . $new["id"] . ".sh", $ffmpeg);

        if ($new["service"] === "enable") {
            exec("sudo systemctl enable encoder@{$new["id"]}");
            exec("sudo systemctl restart encoder@{$new["id"]}");
        }
    }
    unset($new);
    file_put_contents(
        $jsonFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function all_service_start()
{
    unlink("/var/www/core.json");
    $script = __DIR__ . "/stop_all_encoders.sh";
    exec("sudo chmod +x " . $script);
    exec("sudo {$script} 2>&1", $output, $code);

    $jsonFile = __DIR__ . "/input.json";
    if (!file_exists($jsonFile)) {
        die("input.json not found");
    }
    $data = json_decode(file_get_contents($jsonFile), true);

    if (!is_array($data)) {
        die("Invalid JSON format");
    }

    foreach ($data as &$new) {
        $alloc = allocateCore($new["id"]);
        $core = (int)$alloc["cpu"];
        $node = (int)$alloc["node"];
        $new["service"] = "enable";
        $inputIp = "";
        $outputIp = "";

        if (isset($new["input_interface"]) && $new["input_interface"] !== "none") {
            $inputIp = getInterfaceIp($new["input_interface"]);
        }

        if (isset($new["output_interface"]) && $new["output_interface"] !== "none") {
            $outputIp = getInterfaceIp($new["output_interface"]);
        }

        $ffmpeg = 'numactl --cpunodebind=' . $node
            . ' --preferred=' . $node
            . ' taskset -c ' . $core
            . ' ffmpeg -hide_banner -loglevel quiet -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer '
            . ' -i "udp://' . $new["input_udp"] . '?reuse=1&fifo_size=8192&buffer_size=262144&overrun_nonfatal=1&timeout=5000000';

        if ($inputIp != "")
            $ffmpeg .= '&localaddr=' . $inputIp;
        $ffmpeg .= '" -vf "scale=' . $new["resolution"] . ',format=yuv420p" '
            . ' -c:v ' . $new["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 7 '
            . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize ' .  ($new["video_bitrate"] + 500) . 'k '
            . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
            . ' -metadata service_provider="ShreeBhattJI" ';
        if ($new["service_name"] !== "") {
            $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
        }
        $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1';
        if ($outputIp != "")
            $ffmpeg .= '&localaddr=' . $outputIp;

        $ffmpeg .= '"';
        file_put_contents("/var/www/encoder/" . $new["id"] . ".sh", $ffmpeg);

        if ($new["service"] === "enable") {
            exec("sudo systemctl enable encoder@{$new["id"]}");
            exec("sudo systemctl restart encoder@{$new["id"]}");
        }
    }
    unset($new);
    file_put_contents(
        $jsonFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function all_service_stop()
{
    unlink("/var/www/core.json");
    $script = __DIR__ . "/stop_all_encoders.sh";
    exec("sudo chmod +x " . $script);
    exec("sudo {$script} 2>&1", $output, $code);

    $jsonFile = __DIR__ . "/input.json";
    if (!file_exists($jsonFile)) {
        die("input.json not found");
    }
    $data = json_decode(file_get_contents($jsonFile), true);

    if (!is_array($data)) {
        die("Invalid JSON format");
    }

    foreach ($data as &$new) {
        if (isset($new["service"]) && $new["service"] === "enable") {
            $new["service"] = "disable";
        }
    }
    unset($new);
    file_put_contents(
        $jsonFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

// Load network interfaces
$networkFile = "/var/www/network.json";
$interfaces = [];
if (file_exists($networkFile)) {
    $networkData = json_decode(file_get_contents($networkFile), true);
    if (is_array($networkData)) {
        foreach ($networkData as $interfaceName => $interfaceData) {
            $nickname = !empty($interfaceData["interface_nickname"])
                ? $interfaceData["interface_nickname"]
                : $interfaceName;
            $interfaces[$interfaceName] = $nickname;
        }
    }
}

$jsonFile = __DIR__ . "/input.json";
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}
$data = json_decode(file_get_contents($jsonFile), true);

foreach ($data as $k => $d) {
    if (!isset($d["service_name"])) $data[$k]["service_name"] = "";
    if (!isset($d["volume"])) $data[$k]["volume"] = "0";
    // Extract interface from input_udp and output_udp if not already present
    if (isset($d["input_udp"]) && !isset($d["input_interface"])) {
        $parts = explode(':', $d["input_udp"]);
        if (count($parts) >= 3) {
            $data[$k]["input_interface"] = $parts[0];
            $data[$k]["input_udp"] = $parts[1] . ':' . $parts[2];
        }
    }
    if (isset($d["output_udp"]) && !isset($d["output_interface"])) {
        $parts = explode(':', $d["output_udp"]);
        if (count($parts) >= 3) {
            $data[$k]["output_interface"] = $parts[0];
            $data[$k]["output_udp"] = $parts[1] . ':' . $parts[2];
        }
    }
}
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

// Process POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    switch ($_POST["action"]) {
        case "add":
            $new = [
                "id" => time(),
                "service_name" => $_POST["service_name"],
                "input_udp" => $_POST["input_udp"],
                "input_interface" => $_POST["in_interface"],
                "output_udp" => $_POST["output_udp"],
                "output_interface" => $_POST["out_interface"],
                "video_format" => $_POST["video_format"],
                "audio_format" => $_POST["audio_format"],
                "resolution" => $_POST["resolution"],
                "video_bitrate" => $_POST["video_bitrate"],
                "audio_bitrate" => $_POST["audio_bitrate"],
                "volume" => $_POST["volume"],
                "service" => $_POST["service"]
            ];

            $data[] = $new;
            file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

            updateEncoderScriptAndService($new);

            // Redirect to refresh page after adding
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "delete":
            $id = intval($_POST["id"]);
            $newData = [];

            foreach ($data as $row) {
                if ($row["id"] != $id) $newData[] = $row;
            }

            file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
            exec("sudo systemctl stop encoder@$id");
            exec("sudo systemctl disable encoder@$id");
            freeCore($id);

            if (file_exists("/var/www/encoder/$id.sh")) unlink("/var/www/encoder/$id.sh");

            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "edit":
            $id = intval($_POST["id"]);
            $newData = [];

            foreach ($data as $row) {
                if (intval($row["id"]) === $id) {
                    $service_name = trim($_POST["service_name"]);
                    $input_udp = trim($_POST["input_udp"]);
                    $output_udp = trim($_POST["output_udp"]);

                    $row = [
                        "id" => $id,
                        "service_name" => $service_name !== "" ? $service_name : ($row["service_name"] ?? ""),
                        "input_udp" => $input_udp !== "" ? $input_udp : ($row["input_udp"] ?? ""),
                        "input_interface" => $_POST["in_interface"],
                        "output_udp" => $output_udp !== "" ? $output_udp : ($row["output_udp"] ?? ""),
                        "output_interface" => $_POST["out_interface"],
                        "video_format" => $_POST["video_format"],
                        "audio_format" => $_POST["audio_format"],
                        "resolution" => $_POST["resolution"],
                        "video_bitrate" => $_POST["video_bitrate"],
                        "audio_bitrate" => $_POST["audio_bitrate"],
                        "volume" => $_POST["volume"],
                        "service" => $_POST["service"]
                    ];
                    updateEncoderScriptAndService($row);
                }
                $newData[] = $row;
            }

            file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "edit_batch":
            $targetIds = array_map('intval', explode(',', $_POST["id"]));
            $newData = [];

            foreach ($data as $row) {
                if (in_array(intval($row["id"]), $targetIds, true)) {
                    $service_name = trim($_POST["service_name"]);
                    $input_udp = trim($_POST["input_udp"]);
                    $output_udp = trim($_POST["output_udp"]);

                    $row = [
                        "id" => intval($row["id"]),
                        "service_name" => $service_name !== "" ? $service_name : ($row["service_name"] ?? ""),
                        "input_udp" => $input_udp !== "" ? $input_udp : ($row["input_udp"] ?? ""),
                        "input_interface" => $_POST["in_interface"],
                        "output_udp" => $output_udp !== "" ? $output_udp : ($row["output_udp"] ?? ""),
                        "output_interface" => $_POST["out_interface"],
                        "video_format" => $_POST["video_format"],
                        "audio_format" => $_POST["audio_format"],
                        "resolution" => $_POST["resolution"],
                        "video_bitrate" => $_POST["video_bitrate"],
                        "audio_bitrate" => $_POST["audio_bitrate"],
                        "volume" => $_POST["volume"],
                        "service" => $_POST["service"]
                    ];
                    updateEncoderScriptAndService($row);
                }
                $newData[] = $row;
            }

            file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "restart":
            $id = intval($_POST["id"]);
            exec("sudo systemctl restart encoder@$id");
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "start_all":
            all_service_start();
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "stop_all":
            all_service_stop();
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "update_all":
            all_service_update();
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
        case "delete_selected":
            $ids = isset($_POST["ids"]) ? array_map('intval', explode(',', $_POST["ids"])) : [];
            if (!empty($ids)) {
                $newData = [];
                foreach ($data as $row) {
                    if (!in_array(intval($row["id"]), $ids, true)) {
                        $newData[] = $row;
                    } else {
                        $id = intval($row["id"]);
                        exec("sudo systemctl stop encoder@$id");
                        exec("sudo systemctl disable encoder@$id");
                        freeCore($id);
                        if (file_exists("/var/www/encoder/$id.sh")) unlink("/var/www/encoder/$id.sh");
                    }
                }
                file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
            }
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
            break;
    }
}

// Reload data after processing POST
$data = json_decode(file_get_contents($jsonFile), true);
foreach ($data as $k => $d) {
    if (!isset($d["service_name"])) $data[$k]["service_name"] = "";
    if (!isset($d["volume"])) $data[$k]["volume"] = "0";
    // Extract interface from input_udp and output_udp if not already present
    if (isset($d["input_udp"]) && !isset($d["input_interface"])) {
        $parts = explode(':', $d["input_udp"]);
        if (count($parts) >= 3) {
            $data[$k]["input_interface"] = $parts[0];
            $data[$k]["input_udp"] = $parts[1] . ':' . $parts[2];
        }
    }
    if (isset($d["output_udp"]) && !isset($d["output_interface"])) {
        $parts = explode(':', $d["output_udp"]);
        if (count($parts) >= 3) {
            $data[$k]["output_interface"] = $parts[0];
            $data[$k]["output_udp"] = $parts[1] . ':' . $parts[2];
        }
    }
}
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

include 'header.php';
?>

<div class="containerindex">
    <div class="card wide">
        <h2>Service List</h2>
        <div class="button-container" style="margin-bottom: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="green-btn" onclick="openAddPopup()">Add Service</button>
            <button class="green-btn" onclick="submitAction('start_all')">Start All</button>
            <button class="green-btn" onclick="submitAction('update_all')">Update All</button>
            <button class="red-btn" onclick="submitAction('stop_all')">Stop All</button>
            <button class="green-btn" onclick="editSelectedService()" >Edit Selected</button>
            <button class="red-btn" onclick="deleteSelectedServices()" >Delete Selected</button>
        </div>
        <form id="actionForm" method="post" style="display:none;">
            <input type="hidden" name="action" id="action">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        </form>

        <!-- Service Table -->
        <div class="table-container">
            <table class="service-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                        <th>No</th>
                        <th>Status</th>
                        <th>Service Name</th>
                        <th>UDP</th>
                        <th>Video Info</th>
                        <th>Audio Info</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="service-checkbox" value="<?= $row['id'] ?>" data-row='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                            </td>
                            <td><?= $i++ ?></td>
                            <td>
                                <div><?= $row['id'] ?> </div>
                                <div><?php if (isset($row["service"]) && $row["service"] === "enable"): ?>
                                        <span style="color: green;">(Running)</span>
                                    <?php else: ?>
                                        <span style="color: red;">(Stopped)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= $row["service_name"] ?></td>
                            <td>
                                <div><strong>In:</strong>
                                    <?php
                                    $inInterface = $row["input_interface"] ?? '';
                                    echo  $row["input_udp"];
                                    ?>
                                </div>
                                <div><strong>In Port:</strong>
                                    <?php
                                    $inPort = $interfaces[$inInterface] ?? $inInterface;
                                    echo $inPort;
                                    ?>
                                </div>
                                <div><strong>Out:</strong>
                                    <?php
                                    $outInterface = $row["output_interface"] ?? '';
                                    echo  $row["output_udp"];
                                    ?>
                                </div>
                                <div><strong>Out Port:</strong>
                                    <?php
                                    $outPort = $interfaces[$outInterface] ?? $outInterface;
                                    echo $outPort;
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <div><strong>Video:</strong> <?= $row["video_format"] ?></div>
                                    <div><strong>Resolution:</strong> <?= $row["resolution"] ?></div>
                                    <div><strong>Bitrate:</strong> <?= $row["video_bitrate"] ?> kbps</div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <div><strong>Audio:</strong> <?= $row["audio_format"] ?></div>
                                    <div><strong>Bitrate:</strong> <?= $row["audio_bitrate"] ?> kbps</div>
                                    <div><strong>Volume:</strong> <?= $row["volume"] ?> dB</div>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons" style="display: flex; flex-direction: column;">
                                    <button class="edit-btn" onclick='openEditPopup(<?= json_encode($row) ?>)'>Edit</button>
                                    <button class="restart-btn" onclick="restartService(<?= $row['id'] ?>)">Restart</button>
                                    <button class="delete-btn" onclick="deleteService(<?= $row['id'] ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- POPUP -->
    <div id="overlay"></div>
</div>
<div id="popup">
    <h3 id="popup_title">Add Service</h3>

    <input type="hidden" id="service_id">

    <input type="text" id="service_name" placeholder="Service Name">

    <div style="display: flex; gap: 10px;">
        <div style="flex: 1;">
            <label for="in_interface">Input Interface:</label>
            <select id="in_interface">
                <option value="none">None</option>
                <?php foreach ($interfaces as $interfaceName => $nickname): ?>
                    <option value="<?= $interfaceName ?>"><?= $nickname ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1;">
            <label for="out_interface">Output Interface:</label>
            <select id="out_interface">
                <option value="none">None</option>
                <?php foreach ($interfaces as $interfaceName => $nickname): ?>
                    <option value="<?= $interfaceName ?>"><?= $nickname ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <input type="text" id="in_udp" placeholder="228.1.1.1:8001">
    <input type="text" id="out_udp" placeholder="228.1.1.1:8002">

    <select id="video_format">
        <option value="mpeg2video" selected>MPEG2</option>
    </select>

    <select id="audio_format">
        <option value="mp2" selected>MP2</option>
    </select>

    <select id="resolution">
        <option value="720x576" selected>720x576</option>
        <option value="1280x720">1280x720</option>
        <option value="1920x1080">1920x1080</option>
    </select>

    <input type="number" id="video_bitrate" placeholder="Video Bitrate (kbps)" value="4096">
    <input type="number" id="audio_bitrate" placeholder="Audio Bitrate (kbps)" value="128">

    <select id="volume">
        <option value="-4">-4 dB</option>
        <option value="-3">-3 dB</option>
        <option value="-2">-2 dB</option>
        <option value="-1">-1 dB</option>
        <option value="0">0 dB</option>
        <option value="1">1 dB</option>
        <option value="2">2 dB</option>
        <option value="3">3 dB</option>
        <option value="4">4 dB</option>
        <option value="5">5 dB</option>
        <option value="10">10 dB</option>
        <option value="12">12 dB</option>
        <option value="15">15 dB</option>
    </select>

    <select id="service_status">
        <option value="enable">Enable</option>
        <option value="disable">Disable</option>
    </select>

    <div style="margin-top: 15px; display: flex; justify-content: space-between;">
        <button id="saveBtn">Save</button>
        <button type="button" id="cancelBtn">Cancel</button>
    </div>
</div>

<script>
    function openAddPopup() {
        document.getElementById('popup_title').textContent = 'Add Service';
        document.getElementById('service_id').value = '';
        document.getElementById('service_name').value = '';
        document.getElementById('service_name').placeholder = 'Service Name';
        document.getElementById('in_udp').value = '';
        document.getElementById('in_udp').placeholder = '228.1.1.1:8001';
        document.getElementById('out_udp').value = '';
        document.getElementById('out_udp').placeholder = '228.1.1.1:8002';
        document.getElementById('video_format').value = 'mpeg2video';
        document.getElementById('audio_format').value = 'mp2';
        document.getElementById('resolution').value = '720x576';
        document.getElementById('video_bitrate').value = '4096';
        document.getElementById('audio_bitrate').value = '128';
        document.getElementById('volume').value = '0';
        document.getElementById('service_status').value = 'enable';

        // Set default to "none" for interfaces
        document.getElementById('in_interface').value = 'none';
        document.getElementById('out_interface').value = 'none';

        document.getElementById('popup').style.display = 'block';
        document.getElementById('overlay').style.display = 'block';
    }

    function openEditPopup(serviceData) {
        document.getElementById('popup_title').textContent = 'Edit Service';
        document.getElementById('service_id').value = serviceData.id;
        document.getElementById('service_name').value = serviceData.service_name || '';
        document.getElementById('service_name').placeholder = 'Service Name';
        document.getElementById('in_udp').value = serviceData.input_udp || '';
        document.getElementById('in_udp').placeholder = '228.1.1.1:8001';
        document.getElementById('out_udp').value = serviceData.output_udp || '';
        document.getElementById('out_udp').placeholder = '228.1.1.1:8002';
        document.getElementById('video_format').value = serviceData.video_format || 'mpeg2video';
        document.getElementById('audio_format').value = serviceData.audio_format || 'mp2';
        document.getElementById('resolution').value = serviceData.resolution || '720x576';
        document.getElementById('video_bitrate').value = serviceData.video_bitrate || '4096';
        document.getElementById('audio_bitrate').value = serviceData.audio_bitrate || '128';
        document.getElementById('volume').value = serviceData.volume || '0';
        document.getElementById('service_status').value = serviceData.service || 'enable';

        // Set interface values if they exist, default to "none" if not set
        if (serviceData.input_interface) {
            document.getElementById('in_interface').value = serviceData.input_interface;
        } else {
            document.getElementById('in_interface').value = 'none';
        }
        if (serviceData.output_interface) {
            document.getElementById('out_interface').value = serviceData.output_interface;
        } else {
            document.getElementById('out_interface').value = 'none';
        }

        document.getElementById('popup').style.display = 'block';
        document.getElementById('overlay').style.display = 'block';
    }

    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf']) ?>;

    function appendCsrf(form) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf';
        csrfInput.value = CSRF_TOKEN;
        form.appendChild(csrfInput);
    }

    function closePopup() {
        document.getElementById('popup').style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
    }

    function restartService(id) {
        if (confirm('Are you sure you want to restart this service?')) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = ''; // Current page

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'restart';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            appendCsrf(form);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteService(id) {
        if (confirm('Are you sure you want to delete this service?')) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = ''; // Current page

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            appendCsrf(form);
            document.body.appendChild(form);
            form.submit();
        }
    }

    document.getElementById('saveBtn').addEventListener('click', function() {
        const id = document.getElementById('service_id').value;
        const serviceData = {
            service_name: document.getElementById('service_name').value,
            input_udp: document.getElementById('in_udp').value,
            in_interface: document.getElementById('in_interface').value,
            output_udp: document.getElementById('out_udp').value,
            out_interface: document.getElementById('out_interface').value,
            video_format: document.getElementById('video_format').value,
            audio_format: document.getElementById('audio_format').value,
            resolution: document.getElementById('resolution').value,
            video_bitrate: document.getElementById('video_bitrate').value,
            audio_bitrate: document.getElementById('audio_bitrate').value,
            volume: document.getElementById('volume').value,
            service: document.getElementById('service_status').value
        };

        const form = document.createElement('form');
        form.method = 'post';
        form.action = '';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        if (!id) {
            actionInput.value = 'add';
        } else if (id.includes(',')) {
            actionInput.value = 'edit_batch';
        } else {
            actionInput.value = 'edit';
        }

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        // Add all service data fields
        for (const [key, value] of Object.entries(serviceData)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }

        form.appendChild(actionInput);
        form.appendChild(idInput);
        appendCsrf(form);
        document.body.appendChild(form);
        form.submit();
    });

    function submitAction(action) {
        document.getElementById('action').value = action;
        document.getElementById('actionForm').submit();
    }

    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.service-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
    }

    function editSelectedService() {
        const checked = Array.from(document.querySelectorAll('.service-checkbox:checked'));
        if (checked.length === 0) {
            alert('Please select at least one service to edit.');
            return;
        }

        if (checked.length === 1) {
            try {
                const serviceData = JSON.parse(checked[0].getAttribute('data-row'));
                openEditPopup(serviceData);
            } catch (e) {
                alert('Could not parse service data for editing.');
            }
        } else {
            const ids = checked.map(cb => cb.value).join(',');
            try {
                const firstServiceData = JSON.parse(checked[0].getAttribute('data-row'));
                openEditPopup(firstServiceData);
                document.getElementById('popup_title').textContent = `Edit Selected Services (${checked.length} Services)`;
                document.getElementById('service_id').value = ids;

                // For batch editing, clear unique fields so existing values are preserved unless typed
                document.getElementById('service_name').value = '';
                document.getElementById('service_name').placeholder = 'Keep existing Service Names';
                document.getElementById('in_udp').value = '';
                document.getElementById('in_udp').placeholder = 'Keep existing Input UDP';
                document.getElementById('out_udp').value = '';
                document.getElementById('out_udp').placeholder = 'Keep existing Output UDP';
            } catch (e) {
                alert('Could not parse service data for batch editing.');
            }
        }
    }

    function deleteSelectedServices() {
        const checked = Array.from(document.querySelectorAll('.service-checkbox:checked'));
        if (checked.length === 0) {
            alert('Please select at least one service to delete.');
            return;
        }
        if (confirm(`Are you sure you want to delete ${checked.length} selected service(s)?`)) {
            const ids = checked.map(cb => cb.value).join(',');
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_selected';

            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'ids';
            idsInput.value = ids;

            form.appendChild(actionInput);
            form.appendChild(idsInput);
            appendCsrf(form);
            document.body.appendChild(form);
            form.submit();
        }
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('overlay')) {
            closePopup();
        }
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePopup();
        }
    });

    document.getElementById('cancelBtn').addEventListener('click', closePopup);
</script>
<?php include 'footer.php'; ?>