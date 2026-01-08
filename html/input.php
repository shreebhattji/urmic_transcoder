<?php include 'header.php'; ?>
<?php

$jsonFile = __DIR__ . "/input.json";
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}
$data = json_decode(file_get_contents($jsonFile), true);

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "add") {
    $new = [
        "id" => time(),
        "input_udp" => $_POST["input_udp"],
        "output_udp" => $_POST["output_udp"],
        "video_format" => $_POST["video_format"],
        "audio_format" => $_POST["audio_format"],
        "resolution" => $_POST["resolution"],
        "video_bitrate" => $_POST["video_bitrate"],
        "audio_bitrate" => $_POST["audio_bitrate"],
        "service" => $_POST["service"]
    ];

    $data[] = $new;
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    $ffmpeg = "ffmpeg -fflags +genpts+discardcorrupt -i udp://@" . $new["input_udp"] . "?overrun_nonfatal=1&fifo_size=50000000 ";
    switch ($new["video_format"]) {
        case "mpeg2video":
            $ffmpeg .= " -vf scale=" . $new["resolution"] . "  -c:v mpeg2video -pix_fmt yuv420p -b:v " . $new["video_bitrate"] . "k -maxrate " . $new["video_bitrate"] . "k -minrate " . $new["video_bitrate"] . "k -bufsize " . $new["video_bitrate"] . "k";
            break;
        case "h264":
            $ffmpeg .= " -vf scale=" . $new["resolution"] . "  -c:v h264 -pix_fmt yuv420p -b:v " . $new["video_bitrate"] . "k -maxrate " . $new["video_bitrate"] . "k -minrate " . $new["video_bitrate"] . "k -bufsize " . $new["video_bitrate"] . "k";
            break;
        case "h265":
            $ffmpeg .= " -vf scale=" . $new["resolution"] . "  -c:v h265 -pix_fmt yuv420p -b:v " . $new["video_bitrate"] . "k -maxrate " . $new["video_bitrate"] . "k -minrate " . $new["video_bitrate"] . "k -bufsize " . $new["video_bitrate"] . "k";
            break;
    }
    $ffmpeg .= " -c:a " . $new["audio_format"] . " -b:a " . $new["audio_bitrate"] . "k -ar 48000 -ac 2 -f mpegts udp://@" . $new["output_udp"];
    file_put_contents("/var/www/encoder/" . $new["id"], $ffmpeg);
    exec("sudo systemctl enable encoder@" . $new["id"]);
    exec("sudo systemctl restart encoder@" . $new["id"]);
    echo "OK";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "delete") {
    $id = intval($_POST["id"]);
    $newData = [];

    foreach ($data as $row) {
        if ($row["id"] != $id) $newData[] = $row;
    }

    file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
    exec("sudo systemctl stop encoder@" . $id);
    exec("sudo systemctl disable encoder@" . $id);
    unlink("/var/www/encoder/" . $id);
    echo "OK";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "edit") {
    $id = intval($_POST["id"]);
    $newData = [];

    foreach ($data as $row) {
        if ($row["id"] == $id) {
            $row = [
                "id" => $id,
                "input_udp" => $_POST["input_udp"],
                "output_udp" => $_POST["output_udp"],
                "video_format" => $_POST["video_format"],
                "audio_format" => $_POST["audio_format"],
                "resolution" => $_POST["resolution"],
                "video_bitrate" => $_POST["video_bitrate"],
                "audio_bitrate" => $_POST["audio_bitrate"],
                "service" => $_POST["service"]
            ];
            exec("sudo systemctl restart encoder@" . $id);
        }
        $newData[] = $row;
    }

    file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT));
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

    input,
    select {
        width: 100%;
        padding: 6px;
        margin-bottom: 10px;
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

    .delete-btn {
        background: #b40000;
        color: white;
    }

    .edit-btn {
        background: #0066cc;
        color: white;
    }
</style>
<div class="containerindex">
    <div class="grid">

        <h2>Service List</h2>
        <button onclick="openAddPopup()">Add Service</button>

        <table>
            <tr>
                <th>ID</th>
                <th>Input UDP</th>
                <th>Output UDP</th>
                <th>Video Format</th>
                <th>Audio Format</th>
                <th>Resolution</th>
                <th>Video Bitrate</th>
                <th>Audio Bitrate</th>
                <th>Service</th>
                <th>Actions</th>
            </tr>

            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= $row["id"] ?></td>
                    <td><?= $row["input_udp"] ?></td>
                    <td><?= $row["output_udp"] ?></td>
                    <td><?= $row["video_format"] ?></td>
                    <td><?= $row["audio_format"] ?></td>
                    <td><?= $row["resolution"] ?></td>
                    <td><?= $row["video_bitrate"] ?></td>
                    <td><?= $row["audio_bitrate"] ?></td>
                    <td><?= $row["service"] ?></td>
                    <td>
                        <button class="edit-btn" onclick='openEditPopup(<?= json_encode($row) ?>)'>Edit</button>
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

            <input type="text" id="in_udp" placeholder="Input UDP">
            <input type="text" id="out_udp" placeholder="Output UDP">

            <select id="video_format">
                <option value="mpeg2video">MPEG2</option>
                <option value="h264">H.264</option>
                <option value="h265">H.265</option>
            </select>

            <select id="audio_format">
                <option value="mp2">MP2</option>
                <option value="mp3">MP3</option>
                <option value="aac">AAC</option>
                <option value="ac3">AC3</option>
            </select>

            <select id="resolution">
                <option value="720:576">720x576</option>
                <option value="1280:720">1280x720</option>
                <option value="1920:1080">1920x1080</option>
            </select>

            <input type="text" id="video_bitrate" placeholder="Video Bitrate (kbps)">
            <input type="text" id="audio_bitrate" placeholder="Audio Bitrate (kbps)">

            <select id="service">
                <option value="enable">Enable</option>
                <option value="disable">Disable</option>
            </select>

            <button id="saveBtn" onclick="saveService()">Save</button>
            <button onclick="closePopup()">Close</button>
        </div>

    </div>
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

        document.getElementById("service_id").value = row.id;
        document.getElementById("in_udp").value = row.input_udp;
        document.getElementById("out_udp").value = row.output_udp;
        document.getElementById("video_format").value = row.video_format;
        document.getElementById("audio_format").value = row.audio_format;
        document.getElementById("resolution").value = row.resolution;
        document.getElementById("video_bitrate").value = row.video_bitrate;
        document.getElementById("audio_bitrate").value = row.audio_bitrate;
        document.getElementById("service").value = row.service;

        document.getElementById("saveBtn").setAttribute("onclick", "updateService()");
        showPopup();
    }

    function showPopup() {
        document.getElementById("overlay").style.display = "block";
        document.getElementById("popup").style.display = "block";
    }

    function closePopup() {
        document.getElementById("overlay").style.display = "none";
        document.getElementById("popup").style.display = "none";
    }

    function clearFields() {
        document.getElementById("service_id").value = "";
        document.getElementById("in_udp").value = "";
        document.getElementById("out_udp").value = "";
        document.getElementById("video_format").value = "h264";
        document.getElementById("audio_format").value = "aac";
        document.getElementById("resolution").value = "1920x1080";
        document.getElementById("video_bitrate").value = "";
        document.getElementById("audio_bitrate").value = "";
        document.getElementById("service").value = "enable";
    }

    // ------------------------ SAVE ------------------------
    function saveService() {
        let form = new FormData();
        form.append("action", "add");
        form.append("input_udp", in_udp.value);
        form.append("output_udp", out_udp.value);
        form.append("video_format", video_format.value);
        form.append("audio_format", audio_format.value);
        form.append("resolution", resolution.value);
        form.append("video_bitrate", video_bitrate.value);
        form.append("audio_bitrate", audio_bitrate.value);
        form.append("service", document.getElementById("service").value);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res.includes("OK")) location.reload();
            });
    }

    // ------------------------ UPDATE ------------------------
    function updateService() {
        let form = new FormData();
        form.append("action", "edit");
        form.append("id", service_id.value);
        form.append("input_udp", in_udp.value);
        form.append("output_udp", out_udp.value);
        form.append("video_format", video_format.value);
        form.append("audio_format", audio_format.value);
        form.append("resolution", resolution.value);
        form.append("video_bitrate", video_bitrate.value);
        form.append("audio_bitrate", audio_bitrate.value);
        form.append("service", document.getElementById("service").value);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res.includes("OK")) location.reload();
            });
    }

    // ------------------------ DELETE ------------------------
    function deleteService(id) {
        if (!confirm("Delete this service?")) return;

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
</script>
<?php include 'footer.php'; ?>