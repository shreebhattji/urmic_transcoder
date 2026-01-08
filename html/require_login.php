<?php
// auth/require_login.php

declare(strict_types=1);
session_start();

/* ---------- SECURITY HEADERS (optional but recommended) ---------- */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ---------- LOGIN CHECK ---------- */
if (empty($_SESSION['user'])) {

    // Prevent cache of protected pages
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    header('Location: /login.php', true, 302);
    exit;
}
