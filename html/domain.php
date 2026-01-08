<?php include 'header.php'; ?>
<?php
$jsonFile = __DIR__ . '/domain.json';
$defaults = [
    'domain' => 'example.com',
    'subdomain' => 'www.example.com',
    'email' => 'name@example.com',
];

if (file_exists($jsonFile)) {
    $raw = file_get_contents($jsonFile);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $defaults;
} else {
    $data = $defaults;
}

?>

<body>
    <style>
        :root {
            --accent: #0b74de;
            --muted: #6b7280;
            --bg: #f8fafc
        }

        body {
            font-family: Inter, system-ui, Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: #111;
            margin: 0;
            padding: 28px
        }

        .wrap {
            width: 100%;
            margin: 0;
            padding: 0
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06)
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: 600
        }

        input[type=text],
        input[type=email],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #e6eef6;
            border-radius: 8px
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px
        }

        .muted {
            color: var(--muted);
            font-size: 13px
        }

        .actions {
            display: flex;
            gap: 8px;
            margin-top: 14px
        }

        button {
            padding: 10px 14px;
            border-radius: 8px;
            border: 0;
            background: var(--accent);
            color: #fff;
            font-weight: 700
        }

        .ghost {
            background: transparent;
            border: 1px solid #e6eef6;
            color: var(--accent)
        }

        .links {
            margin-top: 10px
        }

        .note {
            margin-top: 12px;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 8px;
            font-size: 13px
        }

        .checkbox {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 12px
        }

        pre {
            white-space: pre-wrap;
            background: #fbfcfe;
            padding: 10px;
            border-radius: 6px;
            border: 1px dashed #e6eef6;
            font-size: 13px
        }

        @media (max-width:700px) {
            .row {
                grid-template-columns: 1fr
            }
        }
    </style>
    <div class="containerindex">
        <div class="grid">
            <div class="wrap">
                <div class="card">
                    <form method="post" action="request_cert.php">
                        <label for="domain">Primary domain</label>
                        <input id="domain" name="domain" type="text" placeholder="example.com" required pattern="^[A-Za-z0-9.-]{1,253}$" value="<?php if ($data['domain'] !== "example.com") echo $data['domain']; ?>" />

                        <label for="subdomains" class="muted">Subdomains</label>
                        <input id="subdomains" name="subdomains" type="text" placeholder="example.com (optional)" value="<?php if ($data['subdomain'] !== "www.example.com") echo $data['subdomain']; ?>" />

                        <label for="email">Contact email (for Let\'s Encrypt notices)</label>
                        <input id="email" name="email" type="email" placeholder="your_name@example.com" value="<?php if ($data['email'] !== "name@example.com") echo $data['email']; ?>" required />


                        <div class="row">
                            <div>
                                <label for="staging">Test mode</label>
                                <select id="staging" name="staging">
                                    <option value="0">Production</option>
                                    <option value="1">Staging (use for testing to avoid rate limits)</option>
                                </select>
                            </div>
                        </div>


                        <div class="checkbox">
                            <input type="checkbox" id="agree_tc" name="agree_tc" required />
                            <div>
                                <label for="agree_tc" style="font-weight:700">I agree to Certbot's Terms of Service and confirm that ports <strong>80 (HTTP)</strong> and <strong>443 (HTTPS)</strong> are forwarded to this server.</label>
                                <div class="muted">By checking this you authorise the server operator to run Certbot and modify nginx configuration for the supplied domain(s).</div>
                            </div>
                        </div>


                        <div class="links">
                            <a href="https://letsencrypt.org/repository/#let-s-encrypt-subscriber-agreement" target="_blank" rel="noopener">Certbot / Let's Encrypt Terms &amp; Conditions</a>
                            &nbsp;•&nbsp;
                            <a href="https://letsencrypt.org/privacy/">Privacy Policy</a>
                        </div>


                        <div class="actions">
                            <button type="submit">Request Certificate</button>
                            <button type="reset" class="ghost">Reset</button>
                        </div>


                        <div class="note">
                            <strong>Why ports 80 and 443 are required</strong>
                            <pre>
- Port 80 (HTTP) is used by Certbot for the HTTP-01 challenge: Let's Encrypt connects over HTTP to verify you control the domain.
- Port 443 (HTTPS) is required to serve TLS traffic after the certificate is issued. Nginx must accept HTTPS on port 443 so browsers and streaming clients can connect securely.


Ensure both ports are reachable from the public internet and forwarded to this server's IP. If you use a firewall, add rules to allow inbound TCP 80 and 443.
                            </pre>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.getElementById('certForm').addEventListener('submit', function(e) {
            var dom = document.getElementById('domain').value.trim();
            var subs = document.getElementById('subdomains').value.trim();
            if (!dom) {
                e.preventDefault();
                alert('Primary domain is required');
                return;
            }

            var ok = document.getElementById('agree_tc').checked;
            if (!ok) {
                e.preventDefault();
                alert('You must agree to the terms and confirm ports 80 and 443 are forwarded.');
            }
        });
    </script>
</body>
<?php include 'footer.php'; ?>