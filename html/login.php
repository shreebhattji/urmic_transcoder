<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
session_start();
    
if (!empty($_SESSION['user'])) {
    header('Location: /index.php', true, 302);
    exit;
}
/* ---------- CONFIG ---------- */
$usersFile    = '/var/www/users.json';
$attemptsFile = '/var/www/attempts.json';

$MAX_ATTEMPTS = 3;
$LOCK_TIME    = 3600;

/* ---------- HELPERS ---------- */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function load_json($file): array
{
    return is_file($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}

function save_json($file, $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------- RATE LIMIT ---------- */
$ip = client_ip();
$attempts = load_json($attemptsFile);

if (isset($attempts[$ip])) {
    if (
        $attempts[$ip]['count'] >= $MAX_ATTEMPTS &&
        time() - $attempts[$ip]['last'] < $LOCK_TIME
    ) {
        http_response_code(429);
        die("Too many attempts. Try again later.");
    }
}

/* ---------- LOGIN ---------- */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Invalid request');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($_POST['agree'])) {

        $error = 'You must agree to the Privacy Policy and Terms & Conditions.';
        echo '<script>alert("'
            . htmlspecialchars($error, ENT_QUOTES)
            . '");</script>';
    }
    $users = load_json($usersFile);

    $valid = isset($users[$username]) &&
        password_verify($password, $users[$username]['password']);

    if ($valid) {
        session_regenerate_id(true);
        unset($attempts[$ip]);
        save_json($attemptsFile, $attempts);
        $_SESSION['user'] = $username;
        header('Location: index.php');
        exit;
    }

    // Failed login
    $attempts[$ip]['count'] = ($attempts[$ip]['count'] ?? 0) + 1;
    $attempts[$ip]['last']  = time();
    save_json($attemptsFile, $attempts);

    $error = 'Invalid username or password';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>URMIC powred by Shreebhattji</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-1: #0f172a;
            --bg-2: #1d4ed8;
            --bg-3: #22c55e;
            --accent: #f97316;
            --text-main: #f9fafb;
            --text-muted: #cbd5f5;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at top left, #22c55e33, transparent 60%),
                radial-gradient(circle at bottom right, #f9731633, transparent 65%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2));
            overflow: hidden;
        }

        .page-wrap {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            isolation: isolate;
        }

        .rain-container {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        .raindrop {
            position: absolute;
            width: 2px;
            height: 70px;
            background: linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.9),
                    rgba(255, 255, 255, 0));
            opacity: 0.55;
            border-radius: 999px;
            filter: blur(0.3px);
            animation-name: fall;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            will-change: transform;
        }

        @keyframes fall {
            0% {
                transform: translate3d(0, -120px, 0);
            }

            100% {
                transform: translate3d(0, 110vh, 0);
            }
        }

        .card {
            position: relative;
            z-index: 1;
            max-width: 720px;
            width: 100%;
            padding: 2.5rem 2rem;
            border-radius: 1.75rem;
            border: 1px solid rgba(148, 163, 184, 0.6);
            background: linear-gradient(135deg,
                    rgba(15, 23, 42, 0.9),
                    rgba(15, 23, 42, 0.7)) border-box;
            backdrop-filter: blur(16px);
            box-shadow:
                0 20px 60px rgba(15, 23, 42, 0.7),
                0 0 0 1px rgba(15, 23, 42, 0.9);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-main);
            background: linear-gradient(90deg,
                    rgba(59, 130, 246, 0.6),
                    rgba(34, 197, 94, 0.75));
        }

        .badge-dot {
            width: 0.35rem;
            height: 0.35rem;
            border-radius: 999px;
            background: #bbf7d0;
        }

        h1 {
            font-size: clamp(2.1rem, 4vw, 2.8rem);
            line-height: 1.08;
        }

        h1 span.highlight {
            font-size: clamp(1.1rem, 2vw, 1.8rem);
            background-image: linear-gradient(120deg,
                    #22c55e,
                    #a855f7,
                    #f97316);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            white-space: nowrap;
        }

        p.subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            max-width: 40rem;
        }

        .tagline {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #e5e7eb;
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-top: 0.75rem;
        }

        .pill {
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.8);
            font-size: 0.75rem;
            color: #e5e7eb;
            background: radial-gradient(circle at top left,
                    rgba(59, 130, 246, 0.22),
                    rgba(15, 23, 42, 0.7));
        }

        .footer {
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .brand {
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand span {
            color: var(--accent);
        }

        .links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .link {
            text-decoration: none;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-bottom: 1px dashed rgba(148, 163, 184, 0.7);
        }

        .link:hover {
            color: #ffffff;
            border-bottom-style: solid;
        }

        @media (min-width: 640px) {
            .card {
                padding: 3rem;
            }
        }

        @media (max-width: 480px) {
            .footer {
                align-items: flex-start;
                gap: 0.4rem;
            }
        }
    </style>
</head>

<body>
    <div class="rain-container" id="rain"></div>

    <div class="page-wrap">
        <main class="card">

            <h1>Welcome</h1>

            <?php if ($error): ?>
                <p style="color:#fca5a5"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                <div style="display:flex;flex-direction:column;gap:0.75rem">
                    <input
                        type="text"
                        name="username"
                        placeholder="Username"
                        required
                        style="padding:0.7rem;border-radius:0.5rem;border:none" />

                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                        style="padding:0.7rem;border-radius:0.5rem;border:none" />
                    <label style="display:flex; gap:0.5rem; align-items:flex-start; font-size:0.8rem; color:#e5e7eb;">
                        <input
                            type="checkbox"
                            name="agree"
                            value="1"
                            required
                            style="margin-top:0.15rem">
                        <span>
                            I agree to the
                            <a href="https://urmic.org/2025/12/31/privacy-policy-and-terms-conditions-for-encoder/" target="_blank" class="link">Privacy Policy</a>
                            and
                            <a href="https://urmic.org/2025/12/31/privacy-policy-and-terms-conditions-for-encoder/" target="_blank" class="link">Terms & Conditions</a>
                        </span>
                    </label>
                    <button
                        type="submit"
                        style="padding:0.75rem;border-radius:0.6rem;border:none;
           background:#22c55e;color:#000;font-weight:600">
                        Login
                    </button>
                </div>
            </form>

            <div style="margin-top:0.75rem">
                <a href="forgot.php" class="link">Forgot password?</a>
            </div>
            <div class="footer">
                <div class="brand">
                    URMIC • <span>Shreebhattji</span>
                </div>
                <div class="links">
                    <a href="https://urmic.org/trusted-partners/" class="link">Meet Out Partners</a>
                </div>
            </div>

        </main>
    </div>

    <script>
        (function() {
            const container = document.getElementById("rain");

            function generateRain() {
                if (!container) return;

                container.innerHTML = "";

                // Density: more width -> more raindrops
                const width = window.innerWidth;
                const height = window.innerHeight;

                const baseDensity = 0.16; // drops per vw
                let drops = Math.floor(width * baseDensity);

                if (window.innerWidth < 600) {
                    drops = Math.floor(width * 0.10);
                } else if (window.innerWidth > 1400) {
                    drops = Math.floor(width * 0.18);
                }

                for (let i = 0; i < drops; i++) {
                    const drop = document.createElement("span");
                    drop.className = "raindrop";

                    const left = Math.random() * 100; // vw
                    const delay = Math.random() * -3; // negative so it’s already falling
                    const duration = 0.7 + Math.random() * 1.2; // seconds
                    const offsetY = Math.random() * height * 0.3;

                    drop.style.left = left + "vw";
                    drop.style.top = -120 - offsetY + "px";
                    drop.style.animationDuration = duration + "s";
                    drop.style.animationDelay = delay + "s";
                    drop.style.opacity = (0.35 + Math.random() * 0.55).toFixed(2);

                    container.appendChild(drop);
                }
            }

            // Basic debounce for resize
            let resizeTimer = null;
            window.addEventListener("resize", () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(generateRain, 180);
            });

            window.addEventListener("DOMContentLoaded", generateRain);
        })();
    </script>
</body>

</html>