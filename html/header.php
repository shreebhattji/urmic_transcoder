<?php
/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/static.php';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ShreeBhattJi - URMIC Transcoder</title>
    <script src="chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        :root {
            --bg: #020617;
            --panel: #0f172a;
            --panel2: #020617;
            --accent: #38bdf8;
            --accent2: #6366f1;
            --text: #e5e7eb;
            --muted: #94a3b8;
            --border: rgba(255, 255, 255, .08);
            --radius: 14px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 50% -20%, #0b1225 0%, #020617 60%);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ----- HEADERS ----- */
        .top-header-1 {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #020617;
            border-bottom: 1px solid var(--border);
            z-index: 1002;
            font-size: 18px;
            font-weight: 600;
        }

        .top-header-1 a {
            text-decoration: none;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
        }

        .top-header-2 {
            position: fixed;
            top: 48px;
            left: 0;
            right: 0;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 26px;
            background: #020617;
            border-bottom: 1px solid var(--border);
            z-index: 1001;
            font-size: 14px;
        }

        .top-header-2 a {
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s ease, border-color 0.2s ease;
            padding: 4px 8px;
        }

        .top-header-2 a:hover,
        .top-header-2 a.active {
            color: var(--text);
            font-weight: 600;
        }

        .top-header-2 a.active {
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
        }

        .site-header {
            position: fixed;
            top: 90px;
            left: 0;
            right: 0;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(2, 6, 23, .85);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            z-index: 1000;
        }

        .site-header nav {
            display: flex;
            gap: 26px;
            flex-wrap: wrap;
        }

        .site-header a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease, border-color 0.2s ease;
            padding: 4px 8px;
        }

        .site-header a:hover {
            color: var(--accent);
        }

        .site-header a.active {
            color: var(--accent);
            font-weight: 700;
            border-bottom: 2px solid var(--accent);
        }

        .page-wrap {
            padding-top: 150px;
            min-height: 100vh;
            width: 100%;
            display: block;
            position: relative;
            z-index: 1;
        }

        /* ----- CONTAINER & GRID ----- */
        .containerindex {
            max-width: 1280px;
            margin: 30px auto;
            padding: 24px;
            background: linear-gradient(180deg, #0f1b33, #020617);
            border: 1px solid rgba(56, 189, 248, .25);
            border-radius: var(--radius);
            box-shadow:
                0 0 0 1px rgba(56, 189, 248, .08),
                0 25px 70px rgba(0, 0, 0, .75),
                0 0 40px rgba(56, 189, 248, .06);
            position: relative;
        }

        .containerindex::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: var(--radius);
            pointer-events: none;
            box-shadow: inset 0 0 35px rgba(99, 102, 241, .08);
        }

        .grid {
            display: grid;
            gap: 26px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        /* ----- CARDS ----- */
        .card {
            padding: 20px;
            border-radius: var(--radius);
            background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, .015));
            border: 1px solid rgba(255, 255, 255, .12);
            backdrop-filter: blur(8px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, .55);
            margin-bottom: 24px;
        }

        .card h2 {
            margin: 0 0 18px;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
            color: #f1f5f9;
        }

        .card h3 {
            margin: 0 0 16px;
            font-size: 17px;
            font-weight: 600;
            color: #f8fafc;
            text-shadow: 0 0 8px rgba(56, 189, 248, .15);
        }

        .card.wide {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            grid-column: 1 / -1;
        }

        .card.wide h3 {
            border-bottom: 1px solid rgba(20, 255, 255, .08);
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .card p {
            margin: 6px 0;
            line-height: 1.6;
            color: #cbd5e1;
            font-size: 15px;
        }

        /* ----- BUTTONS & CTA ----- */
        button[type="submit"],
        .cta-primary,
        .green-btn {
            background: linear-gradient(90deg, #0ea5e9, #6366f1);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
            text-decoration: none;
            display: inline-block;
        }

        button[type="submit"]:hover,
        .cta-primary:hover,
        .green-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
        }

        .cta {
            display: inline-block;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: .25s;
        }

        .cta-ghost {
            border: 1px solid var(--border);
            color: var(--text);
            background: transparent;
        }

        .cta-ghost:hover {
            background: rgba(255, 255, 255, .05);
            border-color: rgba(255, 255, 255, .2);
        }

        /* ----- ABOUT US PAGE STYLES ----- */
        .about-hero {
            text-align: center;
            margin-bottom: 28px;
        }

        .about-hero h1 {
            font-size: 28px;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 6px;
        }

        .about-hero p {
            color: #94a3b8;
            font-size: 15px;
        }

        .story-quote {
            background: rgba(30, 41, 59, 0.6);
            border-left: 4px solid #38bdf8;
            border-radius: 0 8px 8px 0;
            padding: 14px 18px;
            margin-bottom: 16px;
            font-style: italic;
            color: #e2e8f0;
            font-size: 14px;
            line-height: 1.6;
        }

        .partner-btn-wrap {
            margin-top: 24px;
            text-align: center;
        }

        .history-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .history-item {
            display: flex;
            gap: 12px;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.6;
            background: rgba(15, 23, 42, 0.5);
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }

        .history-item .dot {
            color: #38bdf8;
            font-weight: bold;
            font-size: 16px;
            line-height: 1;
            margin-top: 2px;
        }

        .history-item strong {
            color: #f8fafc;
        }

        .history-item a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 600;
        }

        .history-item a:hover {
            text-decoration: underline;
        }

        /* ----- CONTACT US PAGE STYLES ----- */
        .contact-hero {
            text-align: center;
            margin-bottom: 28px;
        }

        .contact-hero h1 {
            font-size: 28px;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 6px;
        }

        .contact-hero p {
            color: #94a3b8;
            font-size: 15px;
        }

        .service-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .service-item {
            display: flex;
            gap: 12px;
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.6;
        }

        .service-item .icon {
            color: #38bdf8;
            font-weight: bold;
            font-size: 16px;
            line-height: 1;
            margin-top: 2px;
        }

        .service-item strong {
            color: #f8fafc;
        }

        .service-item a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 600;
        }

        .service-item a:hover {
            text-decoration: underline;
        }

        .contact-info-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-block {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.5;
        }

        .info-block .label {
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            font-weight: 600;
        }

        .info-block a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 600;
        }

        .info-block a:hover {
            text-decoration: underline;
        }

        .social-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .social-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(30, 41, 59, 0.8);
            color: #38bdf8;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .social-btn:hover {
            background: #38bdf8;
            color: #0f172a;
            transform: translateY(-2px);
        }

        .social-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* ----- PREMIUM SERVICE PAGE STYLES ----- */
        .premium-hero {
            text-align: center;
            margin-bottom: 28px;
        }

        .premium-hero h1 {
            font-size: 28px;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 6px;
        }

        .premium-hero p {
            color: #94a3b8;
            font-size: 15px;
        }

        .plan-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .plan-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .plan-title {
            font-size: 22px;
            font-weight: 700;
            color: #38bdf8;
            margin-bottom: 6px;
        }

        .plan-price {
            font-size: 32px;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 8px;
        }

        .plan-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0 0 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.5;
        }

        .feature-item .check {
            color: #38bdf8;
            font-weight: bold;
            font-size: 16px;
            line-height: 1;
            margin-top: 2px;
        }

        .feature-item strong {
            color: #f8fafc;
        }

        .plan-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .benefits-box h3 {
            font-size: 18px;
            color: #38bdf8;
            margin-bottom: 16px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 12px;
        }

        .benefits-list {
            padding-left: 20px;
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.7;
        }

        .benefits-list li {
            margin-bottom: 10px;
        }

        .benefits-list strong {
            color: #f8fafc;
        }

        .info-note {
            background: rgba(30, 41, 59, 0.7);
            border-left: 4px solid #38bdf8;
            border-radius: 0 8px 8px 0;
            padding: 14px 18px;
            margin-top: 18px;
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .info-note strong {
            color: #f8fafc;
        }

        /* ----- MEDIA QUERIES ----- */
        @media (max-width: 700px) {
            .site-header nav {
                gap: 14px;
            }

            .page-wrap {
                padding-top: 180px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- HEADER ROW 1 -->
    <header class="top-header-1">
        <a href="https://urmic.org/urmic-mpeg2-transcoder/">URMI MPEG2 Transcoder</a>
    </header>

    <!-- HEADER ROW 2 -->
    <header class="top-header-2">
        <a href="https://learn.urmic.org/" target="_blank">Tutorials</a>
        <a href="about_us.php" class="<?= $current_page === 'about_us.php' ? 'active' : '' ?>">About Us</a>
        <a href="contact_us.php" class="<?= $current_page === 'contact_us.php' ? 'active' : '' ?>">Contact Us</a>
        <a href="premium_service.php" class="<?= $current_page === 'premium_service.php' ? 'active' : '' ?>">Premium Service</a>
    </header>

    <!-- HEADER ROW 3 -->
    <header class="site-header">
        <nav aria-label="Main navigation">
            <a href="input.php" class="<?= $current_page === 'input.php' ? 'active' : '' ?>"><i class="fas fa-file-upload"></i> Input</a>
            <a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Monitor</a>
            <a href="network.php" class="<?= $current_page === 'network.php' ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Network</a>
            <a href="firmware.php" class="<?= $current_page === 'firmware.php' ? 'active' : '' ?>"><i class="fas fa-microchip"></i> Firmware</a>
            <a href="password.php" class="<?= $current_page === 'password.php' ? 'active' : '' ?>"><i class="fas fa-lock"></i> Password</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </header>

    <div class="page-wrap">