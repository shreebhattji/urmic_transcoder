<?php include 'header.php'; ?>
<?php

/* ---------------- CPU CORE MANAGEMENT ---------------- */

$coreFile = "/var/www/core.json";
if (!file_exists($coreFile)) {
    file_put_contents($coreFile, json_encode([]));
}

function getTotalCores(): int
{
    return intval(trim(shell_exec("nproc")));
}

function allocateCore(int $serviceId): int
{
    global $coreFile;

    $map = json_decode(file_get_contents($coreFile), true) ?: [];
    $used = array_values($map);
    $total = getTotalCores();

    for ($i = 0; $i < $total; $i++) {
        if (!in_array($i, $used, true)) {
            $map[$serviceId] = $i;
            file_put_contents($coreFile, json_encode($map, JSON_PRETTY_PRINT));
            return $i;
        }
    }

    $core = $serviceId % $total;
    $map[$serviceId] = $core;
    file_put_contents($coreFile, json_encode($map, JSON_PRETTY_PRINT));
    return $core;
}

function getServiceCore(int $serviceId): ?int
{
    global $coreFile;
    $map = json_decode(file_get_contents($coreFile), true) ?: [];
    return $map[$serviceId] ?? null;
}

function freeCore(int $serviceId): void
{
    global $coreFile;
    $map = json_decode(file_get_contents($coreFile), true) ?: [];
    if (isset($map[$serviceId])) {
        unset($map[$serviceId]);
        file_put_contents($coreFile, json_encode($map, JSON_PRETTY_PRINT));
    }
}

/* ---------------- DATA LOAD ---------------- */

$jsonFile = __DIR__ . "/input.json";
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}
$data = json_decode(file_get_contents($jsonFile), true);

/* Fix legacy entries */
foreach ($data as $k => $d) {
    if (!isset($d["service_name"])) $data[$k]["service_name"] = "";
    if (!isset($d["volume"])) $data[$k]["volume"] = "0";
}
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

/* ---------------- ADD ---------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "add") {

    $id = time();
    $core = allocateCore($id);

    $new = [
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

    $data[] = $new;
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

    $ffmpeg = 'taskset -c ' . $core . ' ffmpeg -hide_banner -loglevel error \
 -thread_queue_size 16384 \
 -fflags +genpts+discardcorrupt+nobuffer \
 -flags +low_delay \
 -i "udp://@' . $new["input_udp"] . '?fifo_size=50000000&buffer_size=50000000&overrun_nonfatal=1" \
 -vf "scale=' . $new["resolution"] . ',format=yuv420p" \
 -c:v ' . $new["video_format"] . ' \
 -threads 1 \
 -r 25 -g 50 -bf 0 \
 -qmin 3 -qmax 35 \
 -me_method dia -subq 0 \
 -b:v ' . $new["video_bitrate"] . 'k \
 -minrate ' . $new["video_bitrate"] . 'k \
 -maxrate ' . $new["video_bitrate"] . 'k \
 -bufsize ' . ((int)$new["video_bitrate"] * 2) . 'k \
 -c:a ' . $new["audio_format"] . ' \
 -b:a ' . $new["audio_bitrate"] . 'k -ar 48000 -ac 2 \
 -af "volume=' . $new["volume"] . 'dB,aresample=async=1000" \
 -metadata service_provider="ShreeBhattJI" ';

    if ($new["service_name"] !== "")
        $ffmpeg .= '-metadata service_name="' . $new["service_name"] . '" ';

    $ffmpeg .= '-pcr_period 20 \
 -f mpegts "udp://' . $new["output_udp"] . '?pkt_size=1316&flush_packets=1"';

    file_put_contents("/var/www/encoder/{$id}.sh", $ffmpeg);

    if ($new["service"] === "enable") {
        exec("sudo systemctl enable encoder@$id");
        exec("sudo systemctl restart encoder@$id");
    }

    echo "OK";
    exit;
}

/* ---------------- DELETE ---------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "delete") {

    $id = intval($_POST["id"]);
    $data = array_values(array_filter($data, fn($r) => $r["id"] != $id));
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

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
    $core = getServiceCore($id) ?? allocateCore($id);

    foreach ($data as &$row) {
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

            $ffmpeg = 'taskset -c ' . $core . ' ffmpeg -hide_banner -loglevel error ...';
            file_put_contents("/var/www/encoder/$id.sh", $ffmpeg);

            if ($row["service"] === "enable") {
                exec("sudo systemctl enable encoder@$id");
                exec("sudo systemctl restart encoder@$id");
            } else {
                exec("sudo systemctl stop encoder@$id");
                exec("sudo systemctl disable encoder@$id");
            }
        }
    }

    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    echo "OK";
    exit;
}

/* ---------------- RESTART ---------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "restart") {
    exec("sudo systemctl restart encoder@" . intval($_POST["id"]));
    echo "OK";
    exit;
}

?>

<?php include 'footer.php'; ?>