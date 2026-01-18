<?php include 'header.php'; ?>
<?php

$coreFile = "/var/www/core.json";

/* ---------------------------------------------------------
   STATE HELPERS
--------------------------------------------------------- */

function loadCoreState(): array
{
    global $coreFile;

    if (!file_exists($coreFile)) {
        return ["cursor" => 0, "allocations" => []];
    }

    $state = json_decode(file_get_contents($coreFile), true);
    return $state ?: ["cursor" => 0, "allocations" => []];
}

function saveCoreState(array $state): void
{
    global $coreFile;
    file_put_contents($coreFile, json_encode($state, JSON_PRETTY_PRINT));
}

/* ---------------------------------------------------------
   NUMA + SMT TOPOLOGY (SOURCE OF TRUTH)
--------------------------------------------------------- */

function getNumaTopology(): array
{
    $nodes = [];

    /* discover nodes */
    foreach (glob('/sys/devices/system/node/node*') as $nodePath) {
        $node = (int)str_replace('node', '', basename($nodePath));
        $nodes[$node] = [];
    }

    /* read cpu → node + core_id */
    foreach (glob('/sys/devices/system/cpu/cpu[0-9]*') as $cpuPath) {
        $cpu = (int)str_replace('cpu', '', basename($cpuPath));
        $topo = "$cpuPath/topology";

        if (!is_dir($topo)) {
            continue;
        }

        $coreId = (int)trim(file_get_contents("$topo/core_id"));

        $node = null;
        foreach (glob("$cpuPath/node*") as $n) {
            $node = (int)str_replace('node', '', basename($n));
            break;
        }

        if ($node === null) {
            continue;
        }

        $nodes[$node][$coreId][] = $cpu;
    }

    /* normalize ordering */
    ksort($nodes);
    foreach ($nodes as &$cores) {
        ksort($cores);
        foreach ($cores as &$threads) {
            sort($threads); // primary thread first
        }
    }

    return $nodes;
}

/* ---------------------------------------------------------
   BUILD GLOBAL ROUND-ROBIN PLAN
--------------------------------------------------------- */

function buildAllocationPlan(array $nodes): array
{
    $plan = [];

    if (empty($nodes)) {
        return $plan;
    }

    $maxCores = max(array_map('count', $nodes));

    /* PASS 1 — physical cores only */
    for ($i = 0; $i < $maxCores; $i++) {
        foreach ($nodes as $node => $cores) {
            $coreIds = array_keys($cores);
            if (isset($coreIds[$i])) {
                $plan[] = [
                    "node" => $node,
                    "cpu"  => $cores[$coreIds[$i]][0]
                ];
            }
        }
    }

    /* PASS 2 — SMT siblings */
    for ($i = 0; $i < $maxCores; $i++) {
        foreach ($nodes as $node => $cores) {
            $coreIds = array_keys($cores);
            if (isset($coreIds[$i]) && count($cores[$coreIds[$i]]) > 1) {
                $plan[] = [
                    "node" => $node,
                    "cpu"  => $cores[$coreIds[$i]][1]
                ];
            }
        }
    }

    return $plan;
}

/* ---------------------------------------------------------
   ALLOCATION API
--------------------------------------------------------- */

function allocateCore(int $serviceId): array
{
    $state = loadCoreState();

    if (isset($state["allocations"][$serviceId])) {
        return $state["allocations"][$serviceId];
    }

    $nodes = getNumaTopology();
    $plan  = buildAllocationPlan($nodes);

    if (empty($plan)) {
        return ["node" => 0, "cpu" => 0];
    }

    $slot = $plan[$state["cursor"] % count($plan)];
    $state["cursor"]++;

    $state["allocations"][$serviceId] = $slot;
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

function getServiceCore(int $serviceId): ?array
{
    $state = loadCoreState();
    return $state["allocations"][$serviceId] ?? null;
}

$jsonFile = __DIR__ . "/input.json";
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}
$data = json_decode(file_get_contents($jsonFile), true);

/* Fix old entries missing service_name or volume */
foreach ($data as $k => $d) {
    if (!isset($d["service_name"])) $data[$k]["service_name"] = "";
    if (!isset($d["volume"])) $data[$k]["volume"] = "0";
}
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

/* ---------------- ADD NEW ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "add") {

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

    $alloc = getServiceCore($id);
    if ($alloc === null) {
        $alloc = allocateCore($id);
    }

    $core = (int)$alloc["cpu"];
    $node = (int)$alloc["node"];

    $ffmpeg = 'numactl --cpunodebind=' . $node
        . ' --membind=' . $node
        . ' taskset -c ' . $core
        . ' ffmpeg -hide_banner -loglevel info -thread_queue_size 65536 -fflags +genpts+discardcorrupt+nobuffer -readrate 1.0'
        . ' -i "udp://@' . $new["input_udp"] . '?fifo_size=100000000&buffer_size=100000000&overrun_nonfatal=1"'
        . ' -vf "yadif=mode=0:deint=0,scale=' . $new["resolution"] . ',format=yuv420p" '
        . ' -c:v ' . $new["video_format"] . ' -flags -ildct-ilme -threads 1 -g 10 -bf 0 '
        . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize 1835k '
        . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1:first_pts=0" '
        . ' -metadata service_provider="ShreeBhattJI" ';
    if ($new["service_name"] !== "") {
        $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
    }
    $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1"';


    if ($new["service_name"] !== "")
        $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '"';
    $ffmpeg .= ' -f mpegts "udp://@' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000"';

    file_put_contents("/var/www/encoder/{$new["id"]}.sh", $ffmpeg);

    if ($new["service"] === "enable") {
        exec("sudo systemctl enable encoder@{$new["id"]}");
        exec("sudo systemctl restart encoder@{$new["id"]}");
    }
    echo "OK";
    exit;
}

/* ---------------- DELETE ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "delete") {

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
}

/* ---------------- EDIT ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "edit") {

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
            $alloc = getServiceCore($id);
            if ($alloc === null) {
                $alloc = allocateCore($id);
            }

            $core = (int)$alloc["cpu"];
            $node = (int)$alloc["node"];


            $ffmpeg = 'numactl --cpunodebind=' . $node
                . ' --membind=' . $node
                . ' taskset -c ' . $core
                . ' ffmpeg -hide_banner -loglevel info -thread_queue_size 65536 -fflags +genpts+discardcorrupt+nobuffer -readrate 1.0'
                . ' -i "udp://@' . $new["input_udp"] . '?fifo_size=100000000&buffer_size=100000000&overrun_nonfatal=1"'
                . ' -vf "yadif=mode=0:deint=0,scale=' . $new["resolution"] . ',format=yuv420p" '
                . ' -c:v ' . $new["video_format"] . ' -flags -ildct-ilme -threads 1 -g 10 -bf 0 '
                . ' -b:v ' . $new["video_bitrate"] . 'k -minrate ' . max(0, $new["video_bitrate"] - 500) . 'k -maxrate ' . ($new["video_bitrate"] + 500) . 'k -bufsize 1835k '
                . ' -c:a ' . $new["audio_format"] . ' -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 -af "volume=' . $new["volume"] . 'dB,aresample=async=1:first_pts=0" '
                . ' -metadata service_provider="ShreeBhattJI" ';
            if ($new["service_name"] !== "") {
                $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';
            }
            $ffmpeg .= ' -pcr_period 20 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000&flush_packets=1"';


            if ($new["service_name"] !== "")
                $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '"';
            $ffmpeg .= ' -f mpegts "udp://@' . $new["output_udp"] . '?pkt_size=1316&bitrate=4500000"';


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
}

/* ---------------- RESTART ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "restart") {
    $id = intval($_POST["id"]);
    exec("sudo systemctl restart encoder@$id");
    echo "OK";
    exit;
}

?>
<style>
    body {
        font-family: Arial;
        padding: 20px;
    }

    button {
        padding: 6px 12px;
        cursor: pointer;
    }

    .restart-btn {
        background: #ffaa00;
    }

    .delete-btn {
        background: #b40000;
        color: white;
    }

    .edit-btn {
        background: #0066cc;
        color: white;
    }

    #popup {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        padding: 20px;
        border: 1px solid #333;
        width: 350px;
    }

    #overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        border: 1px solid #ccc;
        padding: 10px;
    }

    input,
    select {
        width: 100%;
        padding: 6px;
        margin-bottom: 10px;
    }
</style>

<div class="containerindex">
    <div class="grid">
        <div class="card">

            <h2>Service List</h2>
            <button onclick="openAddPopup()">Add Service</button>
            <button>Start All</button>
            <button>Stop All</button>
            <button>Update All</button>
        </div>
        <table>
            <tr>
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

            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= $row["id"] ?></td>
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

                    <td>
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
                <option value="720:576" selected>720x576</option>
            </select>

            <input type="text" id="video_bitrate" placeholder="Video Bitrate">

            <input type="text" id="audio_bitrate" placeholder="Audio Bitrate">

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

            <select id="service">
                <option value="enable">Enable</option>
                <option value="disable">Disable</option>
            </select>

            <button id="saveBtn" onclick="saveService()">Save</button>
            <button onclick="closePopup()">Close</button>
            <br>

        </div>
        <br>
    </div>
    <br>
</div>

<script>
    function openAddPopup() {
        document.getElementById("popup_title").innerText = "Add Service";
        document.getElementById("saveBtn").setAttribute("onclick", "saveService()");
        clearFields();
        showPopup();
    }

    function openEditPopup(row) {
        document.getElementById("popup_title").innerText = "Edit Service";

        service_id.value = row.id;
        service_name.value = row.service_name;
        in_udp.value = row.input_udp;
        out_udp.value = row.output_udp;
        video_format.value = row.video_format;
        audio_format.value = row.audio_format;
        resolution.value = row.resolution;
        video_bitrate.value = row.video_bitrate;
        audio_bitrate.value = row.audio_bitrate;
        volume.value = row.volume;
        service.value = row.service;

        document.getElementById("saveBtn").setAttribute("onclick", "updateService()");
        showPopup();
    }

    function showPopup() {
        overlay.style.display = "block";
        popup.style.display = "block";
    }

    function closePopup() {
        overlay.style.display = "none";
        popup.style.display = "none";
    }

    function clearFields() {
        service_id.value = "";
        service_name.value = "";
        in_udp.value = "";
        out_udp.value = "";
        video_format.value = "mpeg2video";
        audio_format.value = "mp2";
        resolution.value = "720:576";
        video_bitrate.value = "3000";
        audio_bitrate.value = "96";
        volume.value = "0";
        service.value = "enable";
    }

    function saveService() {
        let form = new FormData();
        form.append("action", "add");
        form.append("service_name", service_name.value);
        form.append("input_udp", in_udp.value);
        form.append("output_udp", out_udp.value);
        form.append("video_format", video_format.value);
        form.append("audio_format", audio_format.value);
        form.append("resolution", resolution.value);
        form.append("video_bitrate", video_bitrate.value);
        form.append("audio_bitrate", audio_bitrate.value);
        form.append("volume", volume.value);
        form.append("service", service.value);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res.includes("OK")) location.reload();
            });
    }

    function updateService() {
        let form = new FormData();
        form.append("action", "edit");
        form.append("id", service_id.value);

        form.append("service_name", service_name.value);
        form.append("input_udp", in_udp.value);
        form.append("output_udp", out_udp.value);
        form.append("video_format", video_format.value);
        form.append("audio_format", audio_format.value);
        form.append("resolution", resolution.value);
        form.append("video_bitrate", video_bitrate.value);
        form.append("audio_bitrate", audio_bitrate.value);
        form.append("volume", volume.value);
        form.append("service", service.value);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res.includes("OK")) location.reload();
            });
    }

    function deleteService(id) {
        if (!confirm("Delete service?")) return;

        let form = new FormData();
        form.append("action", "delete");
        form.append("id", id);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res.includes("OK")) location.reload();
            });
    }

    function restartService(id) {
        if (!confirm("Restart?")) return;

        let form = new FormData();
        form.append("action", "restart");
        form.append("id", id);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res.includes("OK")) alert("Service restarted");
            });
    }
</script>

<?php include 'footer.php'; ?>