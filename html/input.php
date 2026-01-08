<?php include 'header.php'; ?>
<?php

$jsonFile = __DIR__ . "/input.json";
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}

$data = json_decode(file_get_contents($jsonFile), true);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add") {
    $new = [
        "id" => time(),
        "input_udp" => $_POST["input_udp"],
        "output_udp" => $_POST["output_udp"],
        "video_format" => $_POST["video_format"],
        "audio_format" => $_POST["audio_format"],
        "resolution" => $_POST["resolution"],
        "video_bitrate" => $_POST["video_bitrate"],
        "audio_bitrate" => $_POST["audio_bitrate"],
        "status" => $_POST["status"]
    ];
    $data[] = $new;
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    echo "OK";
    exit;
}

?>
<style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
    }

    button {
        padding: 8px 14px;
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

    #popup input,
    #popup select {
        width: 100%;
        margin-bottom: 10px;
        padding: 6px;
    }

    #overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .table th,
    .table td {
        padding: 10px;
        border: 1px solid #ccc;
        text-align: left;
    }
</style>
</head>
<div class="containerindex">
    <div class="grid">
        <h2>Service List</h2>
        <button onclick="openPopup()">Add Service</button>

        <!-- TABLE LIST -->
        <table class="table">
            <tr>
                <th>ID</th>
                <th>Input UDP</th>
                <th>Output UDP</th>
                <th>Video Format</th>
                <th>Audio Format</th>
                <th>Resolution</th>
                <th>Video Bitrate</th>
                <th>Audio Bitrate</th>
                <th>Status</th>
            </tr>

            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row["id"]) ?></td>
                    <td><?= htmlspecialchars($row["input_udp"]) ?></td>
                    <td><?= htmlspecialchars($row["output_udp"]) ?></td>
                    <td><?= htmlspecialchars($row["video_format"]) ?></td>
                    <td><?= htmlspecialchars($row["audio_format"]) ?></td>
                    <td><?= htmlspecialchars($row["resolution"]) ?></td>
                    <td><?= htmlspecialchars($row["video_bitrate"]) ?></td>
                    <td><?= htmlspecialchars($row["audio_bitrate"]) ?></td>
                    <td><?= htmlspecialchars($row["status"]) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- POPUP -->
        <div id="overlay"></div>

        <div id="popup">
            <h3>Add Service</h3>

            <input type="text" id="in_udp" placeholder="Input UDP">
            <input type="text" id="out_udp" placeholder="Output UDP">

            <select id="video_format">
                <option value="h264">H.264</option>
                <option value="h265">H.265</option>
            </select>

            <select id="audio_format">
                <option value="aac">AAC</option>
                <option value="mp3">MP3</option>
            </select>

            <select id="resolution">
                <option value="1920x1080">1920x1080</option>
                <option value="1280x720">1280x720</option>
                <option value="720x576">720x576</option>
            </select>

            <input type="text" id="video_bitrate" placeholder="Video Bitrate (kbps)">
            <input type="text" id="audio_bitrate" placeholder="Audio Bitrate (kbps)">

            <select id="status">
                <option value="enable">Enable</option>
                <option value="disable">Disable</option>
            </select>

            <button onclick="saveService()">Save</button>
            <button onclick="closePopup()">Close</button>
        </div>
    </div>
</div>

<script>
    function openPopup() {
        document.getElementById("popup").style.display = "block";
        document.getElementById("overlay").style.display = "block";
    }

    function closePopup() {
        document.getElementById("popup").style.display = "none";
        document.getElementById("overlay").style.display = "none";
    }

    function saveService() {
        let form = new FormData();
        form.append("action", "add");
        form.append("input_udp", document.getElementById("in_udp").value);
        form.append("output_udp", document.getElementById("out_udp").value);
        form.append("video_format", document.getElementById("video_format").value);
        form.append("audio_format", document.getElementById("audio_format").value);
        form.append("resolution", document.getElementById("resolution").value);
        form.append("video_bitrate", document.getElementById("video_bitrate").value);
        form.append("audio_bitrate", document.getElementById("audio_bitrate").value);
        form.append("status", document.getElementById("status").value);

        fetch("input.php", {
                method: "POST",
                body: form
            })
            .then(r => r.text())
            .then(res => {
                if (res === "OK") location.reload();
            });
    }
</script>
<?php include 'footer.php'; ?>