<?php
/**
 * FILE: includes/header.php
 * PURPOSE: Shared page header — included at the top of EVERY page.
 *
 * This file does three jobs:
 *   1. Sends HTTP security headers (before any HTML output)
 *   2. Opens the HTML document with the ATM theme structure
 *   3. Renders the top navigation bar
 *
 * HOW TO USE:
 *   At the top of any page (after auth/session setup):
 *     $page_title = 'Dashboard';
 *     require_once __DIR__ . '/includes/header.php';
 *
 * VARIABLE: $page_title
 *   Set this before including header.php. It appears in the browser tab
 *   and the status bar. If not set, defaults to 'SecurePay'.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/logger.php';

// Send all security headers before ANY output
send_security_headers();

// Log this page visit automatically.
// Every page that includes header.php gets logged — no per-page setup needed.
// $pdo may or may not be available depending on which page includes header.php.
// We pass it if available, otherwise file-only logging occurs.
log_activity(isset($pdo) ? $pdo : null);

// Safe: if $page_title not set by including page, use default
$page_title = isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : 'SecurePay';

// Is the current visitor logged in?
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$nav_username = $is_logged_in ? htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!--
        viewport meta: makes the page usable on mobile.
        user-scalable=no is deliberately NOT set — users should be able
        to zoom in on their transaction details.
    -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!--
        SECURITY: Disable DNS prefetching.
        Browsers prefetch DNS for links on the page. This can leak
        which external domains your page links to. We disable it.
    -->
    <meta http-equiv="x-dns-prefetch-control" content="off">

    <!--
        SECURITY: No caching for authenticated pages.
        Without this, pressing Back after logout can show cached pages
        with the user's data still visible.
        Cache-Control is also sent as an HTTP header in security_headers.php
        but meta tags provide a fallback for proxies that ignore headers.
    -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">

    <title><?= $page_title ?> — SecurePay ATM</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<div class="atm-screen">

    <!-- TOP NAVIGATION BAR -->
    <header class="atm-header">
        <div class="atm-logo">
            SECURE<span>PAY</span>
        </div>

        <nav class="atm-nav">
            <?php if ($is_logged_in): ?>
                <!-- Show user's name + navigation links when logged in -->
                <span class="nav-user">[ <?= $nav_username ?> ]</span>
                <a href="/dashboard.php">HOME</a>
                <a href="/transfer.php">TRANSFER</a>
                <a href="/search.php">SEARCH</a>
                <a href="/profile.php">PROFILE</a>
                <a href="/transaction_history.php">HISTORY</a>
                <a href="/logout.php" class="nav-logout">LOGOUT</a>
            <?php else: ?>
                <!-- Guest: show login/register links -->
                <a href="/login.php">LOGIN</a>
                <a href="/register.php">REGISTER</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- STATUS BAR: shows current page name + simulated "terminal" info -->
    <div class="atm-statusbar">
        <span>
            <!-- htmlspecialchars already applied to $page_title above -->
            &gt; <?= strtoupper($page_title) ?>
        </span>
        <span>
            <?php if ($is_logged_in): ?>
                SESSION ACTIVE
                <span class="status-blink">●</span>
            <?php else: ?>
                GUEST MODE
            <?php endif; ?>
            &nbsp;|&nbsp;
            <?= date('d-M-Y H:i') ?>
        </span>
    </div>

    <!-- MAIN CONTENT: closing </div> and </body> are in footer.php -->
    <main class="atm-main">