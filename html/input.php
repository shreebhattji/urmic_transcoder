<?php
/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md

*/
include 'header.php'; ?>
<?php
$coreFile = "/var/www/core.json";

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

function buildSequentialNumaPlan(): array
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

    // Interleave CPUs across nodes: N0,C0 → N1,C0 → N0,C1 → N1,C1 ...
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

    // Already allocated
    if (isset($state["allocations"][$serviceId])) {
        return $state["allocations"][$serviceId];
    }

    $plan = buildSequentialNumaPlan();
    $planCount = count($plan);

    // Build occupied set as node:cpu
    $occupied = [];
    foreach ($state["allocations"] as $a) {
        $occupied[$a["node"] . ":" . $a["cpu"]] = true;
    }

    // GAP FILLING (authoritative)
    foreach ($plan as $index => $slot) {
        $key = $slot["node"] . ":" . $slot["cpu"];
        if (!isset($occupied[$key])) {
            $state["allocations"][$serviceId] = $slot;
            $state["cursor"] = ($index + 1) % $planCount;
            saveCoreState($state);
            return $slot;
        }
    }

    // OVERFLOW (true round-robin)
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

        $ffmpeg = 'numactl --cpunodebind=' . $node
            . ' --preferred=' . $node
            . ' taskset -c ' . $core
            . ' ffmpeg -hide_banner -loglevel info -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer -readrate 1.0'
            . ' -i "udp://' . $new["input_udp"] . '?reuse=1&fifo_size=70000&buffer_size=70000&overrun_nonfatal=1&timeout=5000000"'
            . ' -vf "scale=' . $new["resolution"] . ',format=yuv420p" '
            . ' -c:v ' . $new["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 8 '
            . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize ' .  ($new["video_bitrate"] + 500) . 'k '
            . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
            . ' -metadata service_provider="ShreeBhattJI" ';
        if ($new["service_name"] !== "") {
            $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
        }
        $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1&localaddr=10.10.10.11"';

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
        $ffmpeg = 'numactl --cpunodebind=' . $node
            . ' --preferred=' . $node
            . ' taskset -c ' . $core
            . ' ffmpeg -hide_banner -loglevel info -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer -readrate 1.0'
            . ' -i "udp://' . $new["input_udp"] . '?reuse=1&fifo_size=70000&buffer_size=70000&overrun_nonfatal=1&timeout=5000000"'
            . ' -vf "scale=' . $new["resolution"] . ',format=yuv420p" '
            . ' -c:v ' . $new["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 8 '
            . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize ' .  ($new["video_bitrate"] + 500) . 'k '
            . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
            . ' -metadata service_provider="ShreeBhattJI" ';
        if ($new["service_name"] !== "") {
            $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
        }
        $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1&localaddr=10.10.10.11"';

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

$jsonFile = __DIR__ . "/input.json";
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}
$data = json_decode(file_get_contents($jsonFile), true);

foreach ($data as $k => $d) {
    if (!isset($d["service_name"])) $data[$k]["service_name"] = "";
    if (!isset($d["volume"])) $data[$k]["volume"] = "0";
}
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    switch ($_POST["action"]) {
        case "add":
            $new = [
                "id" => time(),
                "service_name" => $_POST["service_name"],
                "input_udp" => $_POST["input_udp"],
                "output_udp" => $_POST["output_udp"],
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

            $alloc = allocateCore($new["id"]);
            $core = (int)$alloc["cpu"];
            $node = (int)$alloc["node"];

            $ffmpeg = 'numactl --cpunodebind=' . $node
                . ' --preferred=' . $node
                . ' taskset -c ' . $core
                . ' ffmpeg -hide_banner -loglevel info -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer -readrate 1.0'
                . ' -i "udp://' . $new["input_udp"] . '?reuse=1&fifo_size=70000&buffer_size=70000&overrun_nonfatal=1&timeout=5000000"'
                . ' -vf "scale=' . $new["resolution"] . ',format=yuv420p" '
                . ' -c:v ' . $new["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 8 '
                . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize ' .  ($new["video_bitrate"] + 500) . 'k '
                . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
                . ' -metadata service_provider="ShreeBhattJI" ';
            if ($new["service_name"] !== "") {
                $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
            }
            $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1&localaddr=10.10.10.11"';

            file_put_contents("/var/www/encoder/" . $new["id"] . ".sh", $ffmpeg);

            if ($new["service"] === "enable") {
                exec("sudo systemctl enable encoder@{$new["id"]}");
                exec("sudo systemctl restart encoder@{$new["id"]}");
            }
            echo "OK";
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

            echo "OK";
            exit;
            break;
        case "edit":

            $id = intval($_POST["id"]);
            $newData = [];

            foreach ($data as $row) {
                if ($row["id"] == $id) {

                    $row = [
                        "id" => $id,
                        "service_name" => $_POST["service_name"],
                        "input_udp" => $_POST["input_udp"],
                        "output_udp" => $_POST["output_udp"],
                        "video_format" => $_POST["video_format"],
                        "audio_format" => $_POST["audio_format"],
                        "resolution" => $_POST["resolution"],
                        "video_bitrate" => $_POST["video_bitrate"],
                        "audio_bitrate" => $_POST["audio_bitrate"],
                        "volume" => $_POST["volume"],
                        "service" => $_POST["service"]
                    ];

                    $new = $row;
                    $alloc = allocateCore($new["id"]);
                    $core = (int)$alloc["cpu"];
                    $node = (int)$alloc["node"];
                    $ffmpeg = 'numactl --cpunodebind=' . $node
                        . ' --preferred=' . $node
                        . ' taskset -c ' . $core
                        . ' ffmpeg -hide_banner -loglevel info -thread_queue_size 512 -fflags +genpts+discardcorrupt+nobuffer -readrate 1.0'
                        . ' -i "udp://' . $new["input_udp"] . '?reuse=1&fifo_size=70000&buffer_size=70000&overrun_nonfatal=1&timeout=5000000"'
                        . ' -vf "scale=' . $new["resolution"] . ',format=yuv420p" '
                        . ' -c:v ' . $new["video_format"] . ' -pix_fmt yuv420p -flags -ildct-ilme -top 1 -threads 1 -g 25 -bf 2 -qmin 2 -qmax 8 '
                        . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize ' .  ($new["video_bitrate"] + 500) . 'k '
                        . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000:min_hard_comp=0.100000:first_pts=0" '
                        . ' -metadata service_provider="ShreeBhattJI" ';
                    if ($new["service_name"] !== "") {
                        $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
                    }
                    $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1&localaddr=10.10.10.11"';

                    file_put_contents("/var/www/encoder/$id.sh", $ffmpeg);

                    if ($new["service"] === "enable") {
                        exec("sudo systemctl enable encoder@$id");
                        exec("sudo systemctl restart encoder@$id");
                    } else {
                        exec("sudo systemctl stop encoder@$id");
                        exec("sudo systemctl disable encoder@$id");
                    }
                }

                $newData[] = $row;
            }

            file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
            echo "OK";
            exit;
            break;
        case "restart":
            $id = intval($_POST["id"]);
            exec("sudo systemctl restart encoder@$id");
            echo "OK";
            exit;
            break;
        case "start_all":
            all_service_start();
            break;
        case "stop_all":
            all_service_stop();
            break;
        case "update_all":
            all_service_update();
            break;
    }
}

?>

<div class="containerindex">
    <div class="grid">
        <div class="card">

            <h2>Service List</h2>
            <div style="margin-top:10px;">
                <button onclick="openAddPopup()">Add Service</button>
                <button onclick="submitAction('start_all')">Start All</button>
                <button onclick="submitAction('stop_all')">Stop All</button>
                <button onclick="submitAction('update_all')">Update All</button>
            </div>

            <form id="actionForm" method="post" style="display:none;">
                <input type="hidden" name="action" id="action">
            </form>
        </div>
        <table>
            <tr>
                <th>No</th>
                <th>ID</th>
                <th>Service Name</th>
                <th>Input</th>
                <th>Output</th>
                <th>Video</th>
                <th>Audio</th>
                <th>Resolution</th>
                <th>V-Bitrate</th>
                <th>A-Bitrate</th>
                <th>Volume (dB)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php $i = 1; ?>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row["service_name"] ?></td>
                    <td><?= $row["input_udp"] ?></td>
                    <td><?= $row["output_udp"] ?></td>
                    <td><?= $row["video_format"] ?></td>
                    <td><?= $row["audio_format"] ?></td>
                    <td><?= $row["resolution"] ?></td>
                    <td><?= $row["video_bitrate"] ?></td>
                    <td><?= $row["audio_bitrate"] ?></td>
                    <td><?= $row["volume"] ?> dB</td>
                    <td><?= $row["service"] ?></td>

                    <td style="margin-top:3px;">
                        <button class="edit-btn" onclick='openEditPopup(<?= json_encode($row) ?>)'>Edit</button>
                        <button class="restart-btn" onclick="restartService(<?= $row['id'] ?>)">Restart</button>
                        <button class="delete-btn" onclick="deleteService(<?= $row['id'] ?>)">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <!-- POPUP -->
        <div id="overlay"></div>
        <div id="popup">
            <h3 id="popup_title">Add Service</h3>

            <input type="hidden" id="service_id">

            <input type="text" id="service_name" placeholder="Service Name">

            <input type="text" id="in_udp" placeholder="Input UDP">
            <input type="text" id="out_udp" placeholder="Output UDP">

            <select id="video_format">
                <option value="mpeg2video" selected>MPEG2</option>
            </select>

            <select id="audio_format">
                <option value="mp2" selected>MP2</option>
            </select>

            <select id="resolution">
                <option value="720x480" selected>720x480</option>
                <option value="1280x720">1280x720</option>
                <option value="1920x1080">1920x1080</option>
            </select>

            <input type="number" id="video_bitrate" placeholder="Video Bitrate (kbps)" value="2048">
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

            <div style="text-align: center; margin-top: 15px;">
                <button id="saveBtn" onclick="saveService()">Save</button>
                <button type="button" onclick="closePopup()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function openAddPopup() {
    document.getElementById('popup').style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('popup_title').textContent = 'Add Service';
    document.getElementById('service_id').value = '';
    document.getElementById('service_name').value = '';
    document.getElementById('in_udp').value = '';
    document.getElementById('out_udp').value = '';
    document.getElementById('video_format').value = 'mpeg2video';
    document.getElementById('audio_format').value = 'mp2';
    document.getElementById('resolution').value = '720x480';
    document.getElementById('video_bitrate').value = '2048';
    document.getElementById('audio_bitrate').value = '128';
    document.getElementById('volume').value = '0';
    document.getElementById('service_status').value = 'enable';
}

function openEditPopup(service) {
    document.getElementById('popup').style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('popup_title').textContent = 'Edit Service';
    document.getElementById('service_id').value = service.id;
    document.getElementById('service_name').value = service.service_name;
    document.getElementById('in_udp').value = service.input_udp;
    document.getElementById('out_udp').value = service.output_udp;
    document.getElementById('video_format').value = service.video_format;
    document.getElementById('audio_format').value = service.audio_format;
    document.getElementById('resolution').value = service.resolution;
    document.getElementById('video_bitrate').value = service.video_bitrate;
    document.getElementById('audio_bitrate').value = service.audio_bitrate;
    document.getElementById('volume').value = service.volume;
    document.getElementById('service_status').value = service.service === 'enable' ? 'enable' : 'disable';
}

function closePopup() {
    document.getElementById('popup').style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
}

function saveService() {
    // Get form values
    const id = document.getElementById('service_id').value;
    const service_name = document.getElementById('service_name').value;
    const input_udp = document.getElementById('in_udp').value;
    const output_udp = document.getElementById('out_udp').value;
    const video_format = document.getElementById('video_format').value;
    const audio_format = document.getElementById('audio_format').value;
    const resolution = document.getElementById('resolution').value;
    const video_bitrate = document.getElementById('video_bitrate').value;
    const audio_bitrate = document.getElementById('audio_bitrate').value;
    const volume = document.getElementById('volume').value;
    const service_status = document.getElementById('service_status').value;
    
    // Create service object
    const service = {
        id: id,
        service_name: service_name,
        input_udp: input_udp,
        output_udp: output_udp,
        video_format: video_format,
        audio_format: audio_format,
        resolution: resolution,
        video_bitrate: video_bitrate,
        audio_bitrate: audio_bitrate,
        volume: volume,
        service: service_status
    };
    
    // In a real implementation, you would send this to the server
    console.log('Saving service:', service);
    closePopup();
}

// Close popup when clicking outside
document.getElementById('overlay').addEventListener('click', closePopup);

// Close popup with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePopup();
    }
});

// Add event listeners to buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to buttons
    document.querySelector('.card button:nth-child(1)').addEventListener('click', openAddPopup);
    
    // Add event listeners to edit buttons
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(function(button, index) {
        button.addEventListener('click', function() {
            // In a real implementation, you would get the service data from the table row
            const service = {
                id: index + 1,
                service_name: 'Service ' + (index + 1),
                input_udp: '239.0.0.' + (index + 1),
                output_udp: '239.0.0.' + (index + 1),
                video_format: 'mpeg2video',
                audio_format: 'mp2',
                resolution: '720x480',
                video_bitrate: '2048',
                audio_bitrate: '128',
                volume: '0',
                service: 'enable'
            };
            openEditPopup(service);
        });
    });
});
</script>

<?php include 'footer.php'; ?>