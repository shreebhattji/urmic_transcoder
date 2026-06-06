<!-- header.php -->
<?php

/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md

*/

require 'require_login.php';
include 'static.php';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ShreeBhattJi - URMI Digital Transcoder</title>
    <script src="chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="all.min.css" id="local-fa-css" style="display:none;">
</head>

<body>
    <header class="top-header-1">
        <a href="index.php" style="color:white; text-decoration:none;">
            <i class="fas fa-robot"></i> URMI Digital Transcoder
        </a>
    </header>
    <header class="top-header-2">
        <nav aria-label="Top navigation">
            <a href="https://learn.urmic.org/" target="_blank"><i class="fas fa-graduation-cap"></i> Tutorials</a>
            <a href="about_us.php"><i class="fas fa-info-circle"></i> About Us</a>
            <a href="contact_us.php"><i class="fas fa-envelope"></i> Contact Us</a>
            <a href="premium_service.php"><i class="fas fa-crown"></i> Premium Service</a>
        </nav>
    </header>

    <header class="site-header">
        <nav aria-label="Top navigation">
            <a href="input.php"><i class="fas fa-file-upload"></i> Input</a>
            <a href="index.php"><i class="fas fa-tachometer-alt"></i> Monitor</a>
            <a href="network.php"><i class="fas fa-shield-alt"></i> Network</a>
            <a href="firmware.php"><i class="fas fa-microchip"></i> Firmware</a>
            <a href="password.php"><i class="fas fa-lock"></i> Password</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </header>

    <script>
        // Fallback mechanism for Font Awesome
        document.addEventListener('DOMContentLoaded', function() {
            const cdnLink = document.querySelector('link[href*="font-awesome"]');
            const localLink = document.getElementById('local-fa-css');
            
            // Check if CDN failed to load
            cdnLink.addEventListener('error', function() {
                // Switch to local CSS if CDN fails
                if (localLink) {
                    localLink.style.display = 'block';
                    cdnLink.disabled = true;
                }
            });
        });
    </script>