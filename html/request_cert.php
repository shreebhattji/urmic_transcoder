<?php
// request_cert.php
// Parameters (POST):
//  - domain (required)
//  - subdomains (optional, comma-separated)
//  - email (required)
//  - staging (0 or 1)

$FORM_PAGE = "domain.php"; // redirect back to your form
$https = false;
function alert_and_back($message)
{
    global $https;
    global $domain;
    global $subdomains_raw;
    global $email;

    $jsonFile = __DIR__ . '/domain.json';
    $new = [
        'domain' => $domain,
        'subdomain' => $subdomains_raw,
        'email' => $email,
        'https' => $https
    ];
    $json = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($jsonFile, $json, LOCK_EX);


    global $FORM_PAGE;
    // SAFELY escape entire message for JavaScript (supports newlines, quotes, etc.)
    $msg = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Escape redirect target too
    $page = json_encode($FORM_PAGE);

    echo "<script>
        (function(){
            var msg = $msg;
            var dest = $page;

            // Run after DOM to avoid errors when printed inside <head>
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function(){
                    alert(msg);
                    window.location.href = dest;
                });
            } else {
                alert(msg);
                window.location.href = dest;
            }
        })();
    </script>";
    exit;
}
$domain = trim($_POST['domain'] ?? '');
$subdomains_raw = trim($_POST['subdomains'] ?? '');
$email = trim($_POST['email'] ?? '');

$staging = ($_POST['staging'] ?? "0") === "1" ? 1 : 0;

// Validation helpers
function valid_domain_name($d)
{
    $d = trim($d); // important!
    return (bool) preg_match(
        '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
        $d
    );
}

// Validate domain
if ($domain === '' || !valid_domain_name($domain)) {
    alert_and_back("Invalid domain name.");
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    alert_and_back("Invalid email address.");
}

// Process subdomains
$subdomains = [];
if ($subdomains_raw !== '') {
    $parts = preg_split('/[,\s;]+/', $subdomains_raw, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;

        // If user only entered "www", convert -> www.domain.com
        if (strpos($p, '.') === FALSE) {
            $candidate = "$p.$domain";
        } else {
            $candidate = $p;
        }

        if (!valid_domain_name($candidate)) {
            alert_and_back("Invalid subdomain: $p");
        }

        $subdomains[] = $candidate;
    }
}

// Merge primary domain + subdomains
$domains = array_values(array_unique(array_merge([$domain], $subdomains)));

// Build Certbot -d parameters
$dargs = "";
foreach ($domains as $d) {
    $dargs .= " -d " . escapeshellarg($d);
}

// Build certbot command
$certbot = "/usr/bin/certbot";
$cmd = "sudo $certbot --nginx --agree-tos --non-interactive --email "
    . escapeshellarg($email)
    . " $dargs";

if ($staging === 1) {
    $cmd .= " --staging";
}

// Run certbot
exec("$cmd 2>&1", $out, $rc);

if ($rc !== 0) {
    alert_and_back("Certbot failed:\n" . implode("\n", $out));
}

// Test nginx
exec("sudo nginx -t 2>&1", $test_out, $test_rc);

if ($test_rc !== 0) {
    alert_and_back("Certificate created, but nginx test failed:\n" . implode("\n", $test_out));
}

// Reload nginx
exec("sudo systemctl reload nginx 2>&1", $reload_out, $reload_rc);

if ($reload_rc !== 0) {
    alert_and_back("Cert created, nginx tested OK, but reload failed:\n" . implode("\n", $reload_out));
}
$https = true;
// Success
alert_and_back("Certificate installed successfully for:\n" . implode(", ", $domains));
