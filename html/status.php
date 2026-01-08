<?php include 'header.php'; ?>
<?php

$domain = "";
$https = false;

$jsonFile = __DIR__ . '/domain.json';

if (file_exists($jsonFile)) {
    $raw = file_get_contents($jsonFile);
    $data = json_decode($raw, true);
    $domain = $data['domain'];
    $https = $data['https'];
} else {
    $domain = $_SERVER['SERVER_NAME'];
}

$jsonFile = __DIR__ . '/output.json';
if (file_exists($jsonFile)) {
    $raw = file_get_contents($jsonFile);
    $data = json_decode($raw, true);
}

$service_rtmp0_multiple = $data['service_rtmp0_multiple'];
$service_rtmp0_hls = $data['service_rtmp0_hls'];
$service_rtmp0_dash = $data['service_rtmp0_dash'];
$service_rtmp1_multiple = $data['service_rtmp1_multiple'];
$service_rtmp1_hls = $data['service_rtmp1_hls'];
$service_rtmp1_dash = $data['service_rtmp1_dash'];
$service_srt_multiple = $data['service_srt_multiple'];

$text = "<h3>Encoder</h3>";
$text .= "<h5>http://" . $domain;
if ($https) $text .= "<br>https://" . $domain;
$text .= "</h5>";

if ($service_rtmp0_multiple == 'enable') {
    $text .= "<h5>rtmp://" . $domain . "/shree/bhattji<br>";
    if ($service_rtmp0_dash == 'enable') {
        $text .= "http://" . $domain . "/hls/shree/bhattji.m3u8<br>";
        if ($https) {
            $text .= "https://" . $domain . "/hls/shree/bhattji.m3u8<br><br>";
        }
    }
    if ($service_rtmp0_dash == 'enable') {
        $text .= "http://" . $domain . "/dash/shree/bhattji.mpd<br>";
        if ($https) {
            $text .= "https://" . $domain . "/dash/shree/bhattji.mpd<br>";
        }
    }
    $text .= "</h5>";
}
if ($service_rtmp1_multiple == 'enable') {
    $text .= "<h5>rtmp://" . $domain . "/shreeshree/bhattji<br>";
    if ($service_rtmp1_dash == 'enable') {
        $text .= "http://" . $domain . "/hls/shreeshree/bhattji.m3u8<br>";
        if ($https) {
            $text .= "https://" . $domain . "/hls/shreeshree/bhattji.m3u8<br><br>";
        }
    }
    if ($service_rtmp1_dash == 'enable') {
        $text .= "http://" . $domain . "/dash/shreeshree/bhattji.mpd<br>";
        if ($https) {
            $text .= "https://" . $domain . "/dash/shreeshree/bhattji.mpd<br>";
        }
    }
    $text .= "</h5>";
}

if($service_srt_multiple){
    $text .= "<h5>srt://" . $domain . ":1937?streamid=shree/bhatt/ji</h5><br><br>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action'])) {
        $data = explode("_", $_POST['action']);

        switch ($data[0]) {
            case 'main':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl enable encoder-main');
                        exec('sudo systemctl restart encoder-main');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-main');
                        exec('sudo systemctl restart encoder-main');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-main');
                        exec('sudo systemctl disable encoder-main');
                        break;
                }
                break;
            case 'rtmp0':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl enable encoder-rtmp0');
                        exec('sudo systemctl restart encoder-rtmp0');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-rtmp0');
                        exec('sudo systemctl restart encoder-rtmp0');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-rtmp0');
                        exec('sudo systemctl disable encoder-rtmp0');
                        break;
                }
                break;
            case 'rtmp1':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl enable encoder-rtmp1');
                        exec('sudo systemctl restart encoder-rtmp1');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-rtmp1');
                        exec('sudo systemctl restart encoder-rtmp1');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-rtmp1');
                        exec('sudo systemctl disable encoder-rtmp1');
                        break;
                }
                break;
            case 'srt':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl enable srt');
                        exec('sudo systemctl restart srt');
                        exec('sudo systemctl enable encoder-srt');
                        exec('sudo systemctl restart encoder-srt');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable srt');
                        exec('sudo systemctl restart srt');
                        exec('sudo systemctl enable encoder-srt');
                        exec('sudo systemctl restart encoder-srt');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-srt');
                        exec('sudo systemctl disable encoder-srt');
                        exec('sudo systemctl stop srt');
                        exec('sudo systemctl disable srt');
                        break;
                }
                break;
            case 'udp0':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl restart encoder-udp0');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-udp0');
                        exec('sudo systemctl restart encoder-udp0');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-udp0');
                        exec('sudo systemctl disable encoder-udp0');
                        break;
                }
            case 'udp1':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl restart encoder-udp1');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-udp1');
                        exec('sudo systemctl restart encoder-udp1');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-udp1');
                        exec('sudo systemctl disable encoder-udp1');
                        break;
                }
            case 'udp2':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl restart encoder-udp2');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-udp2');
                        exec('sudo systemctl restart encoder-udp2');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-udp2');
                        exec('sudo systemctl disable encoder-udp2');
                        break;
                }
                break;
            case 'custom':
                switch ($data[1]) {
                    case 'restart':
                        exec('sudo systemctl restart encoder-custom');
                        break;
                    case 'enable':
                        exec('sudo systemctl enable encoder-custom');
                        exec('sudo systemctl restart encoder-custom');
                        break;
                    case 'disable':
                        exec('sudo systemctl stop encoder-custom');
                        exec('sudo systemctl disable encoder-custom');
                        break;
                }
                break;
        }
    }
}

?>
<style>
    .card-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .card-left,
    .card-right {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .card-left {
        flex: 1 1 55%;
    }

    .card-right {
        flex: 1 1 40%;
        align-items: flex-end;
        text-align: right;
    }

    .input-wrapper {
        position: relative;
        width: 100%;
    }

    .input-wrapper input {
        width: 100%;
        padding: 10px 40px 10px 12px;
        border-radius: 25px;
        border: 1px solid #ccc;
        font-size: 0.95rem;
        outline: none;
        background: #f9fafb;
    }

    .copy-icon {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.1rem;
        color: #444;
        pointer-events: none;
        /* visual only */
    }

    .service-label {
        font-size: 0.9rem;
        color: #4b5563;
    }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 6px;
    }

    .badge-enabled {
        background: #16a34a22;
        color: #15803d;
        border: 1px solid #16a34a;
    }

    .badge-disabled {
        background: #b91c1c22;
        color: #b91c1c;
        border: 1px solid #b91c1c;
    }

    .service-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }

    .service-buttons button {
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 0.85rem;
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-restart {
        border-color: #0f172a;
        background: #0f172a;
        color: #fff;
    }

    .btn-enable {
        border-color: #15803d;
        background: #15803d;
        color: #fff;
    }

    .btn-disable {
        border-color: #b91c1c;
        background: #b91c1c;
        color: #fff;
    }

    .hls-player-wrapper {
        max-width: 900px;
        margin: 20px auto;
        padding: 16px;
        box-sizing: border-box;
        background: #121212;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: #f1f1f1;
    }

    .hls-video {
        width: 100%;
        max-height: 70vh;
        border-radius: 10px;
        background: #000;
        outline: none;
    }

    .hls-video:focus-visible {
        outline: 2px solid #1e88e5;
        outline-offset: 2px;
    }

    @media (max-width: 768px) {
        .card-right {
            align-items: flex-start;
            text-align: left;
        }
    }
</style>
<div class="containerindex">
    <div class="grid">
        <div class="card wide">
            <h3>Input Service</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-main 2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;
            ?>

            <div class="card-row">
                <div class="service-label">
                    <strong>Service</strong>

                    <?php if ($serviceEnabled): ?>
                        <span class="badge badge-enabled">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="service-buttons">
                    <button type="submit" name="action" value="main_restart" class="btn-restart">
                        Restart
                    </button>

                    <?php if ($serviceEnabled): ?>
                        <button type="submit" name="action" value="main_disable" class="btn-disable">
                            Disable
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="main_enable" class="btn-enable">
                            Enable
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card wide">
            <h3>RTMP0 Server</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-rtmp0 2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;

            ?>

            <div class="card-row">
                <div class="card-right">
                    <div class="service-label">
                        <strong>Service</strong>

                        <?php if ($serviceEnabled): ?>
                            <span class="badge badge-enabled">Enabled</span>
                        <?php else: ?>
                            <span class="badge badge-disabled">Disabled</span>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="service-buttons">
                        <button type="submit" name="action" value="rtmp0_restart" class="btn-restart">Restart</button>

                        <?php if ($serviceEnabled): ?>
                            <button type="submit" name="action" value="rtmp0_disable" class="btn-disable">Disable</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="rtmp0_enable" class="btn-enable">Enable</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="card wide">
            <h3>RTMP1 Server</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-rtmp1 2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;

            ?>

            <div class="card-row">
                <div class="card-right">
                    <div class="service-label">
                        <strong>Service</strong>

                        <?php if ($serviceEnabled): ?>
                            <span class="badge badge-enabled">Enabled</span>
                        <?php else: ?>
                            <span class="badge badge-disabled">Disabled</span>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="service-buttons">
                        <button type="submit" name="action" value="rtmp1_restart" class="btn-restart">Restart</button>

                        <?php if ($serviceEnabled): ?>
                            <button type="submit" name="action" value="rtmp1_disable" class="btn-disable">Disable</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="rtmp1_enable" class="btn-enable">Enable</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="card wide">
            <h3>SRT Server</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-srt 2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;
            ?>

            <div class="card-row">
                <div class="card-right">
                    <div class="service-label">
                        <strong>Service</strong>

                        <?php if ($serviceEnabled): ?>
                            <span class="badge badge-enabled">Enabled</span>
                        <?php else: ?>
                            <span class="badge badge-disabled">Disabled</span>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="service-buttons">
                        <button type="submit" name="action" value="srt_restart" class="btn-restart">Restart</button>

                        <?php if ($serviceEnabled): ?>
                            <button type="submit" name="action" value="srt_disable" class="btn-disable">Disable</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="srt_enable" class="btn-enable">Enable</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="card">
            <h3>Udp0 Service</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-udp0   2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;
            ?>

            <div class="card-row">
                <div class="service-label">
                    <strong>Service</strong>

                    <?php if ($serviceEnabled): ?>
                        <span class="badge badge-enabled">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="service-buttons">
                    <button type="submit" name="action" value="udp0_restart" class="btn-restart">
                        Restart
                    </button>

                    <?php if ($serviceEnabled): ?>
                        <button type="submit" name="action" value="udp0_disable" class="btn-disable">
                            Disable
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="udp0_enable" class="btn-enable">
                            Enable
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card">
            <h3>Udp1 Service</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-udp1   2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;
            ?>

            <div class="card-row">
                <div class="service-label">
                    <strong>Service</strong>

                    <?php if ($serviceEnabled): ?>
                        <span class="badge badge-enabled">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="service-buttons">
                    <button type="submit" name="action" value="udp1_restart" class="btn-restart">
                        Restart
                    </button>

                    <?php if ($serviceEnabled): ?>
                        <button type="submit" name="action" value="udp1_disable" class="btn-disable">
                            Disable
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="udp1_enable" class="btn-enable">
                            Enable
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card">
            <h3>Udp2 Service</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-udp2   2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;
            ?>

            <div class="card-row">
                <div class="service-label">
                    <strong>Service</strong>

                    <?php if ($serviceEnabled): ?>
                        <span class="badge badge-enabled">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="service-buttons">
                    <button type="submit" name="action" value="udp_restart" class="btn-restart">
                        Restart
                    </button>

                    <?php if ($serviceEnabled): ?>
                        <button type="submit" name="action" value="udp_disable" class="btn-disable">
                            Disable
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="udp_enable" class="btn-enable">
                            Enable
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card">
            <h3>Custom Output Service</h3>
            <?php
            $status = shell_exec("sudo systemctl is-active encoder-custom   2>&1");
            $status = trim($status);

            if ($status === "active")
                $serviceEnabled = true;
            else
                $serviceEnabled = false;
            ?>

            <div class="card-row">
                <div class="service-label">
                    <strong>Service</strong>

                    <?php if ($serviceEnabled): ?>
                        <span class="badge badge-enabled">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="service-buttons">
                    <button type="submit" name="action" value="custom_restart" class="btn-restart">
                        Restart
                    </button>

                    <?php if ($serviceEnabled): ?>
                        <button type="submit" name="action" value="custom_disable" class="btn-disable">
                            Disable
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="custom_enable" class="btn-enable">
                            Enable
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card wide">
            <h3>Output Links</h3>
            <?php echo $text; ?>
        </div>
        <br>
        <br>
        <br>

    </div>
</div>

<br>
<br>
<?php include 'footer.php'; ?>