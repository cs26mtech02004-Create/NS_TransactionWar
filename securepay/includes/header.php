<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';

send_security_headers();
log_activity(isset($pdo) ? $pdo : null);

$page_title   = isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : 'SecurePay';
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$nav_username = $is_logged_in ? htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') : '';

// Generate a fresh CSRF token for the logout form.
// Using generate_csrf_token() ensures the token is stored in the session
// and is always current — no stale token problem.
$logout_csrf = $is_logged_in ? generate_csrf_token() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-dns-prefetch-control" content="off">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title><?= $page_title ?> — SecurePay</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<div class="atm-screen">

    <header class="atm-header">
        <div class="atm-logo">SECURE<span>PAY</span></div>

        <nav class="atm-nav">
            <?php if ($is_logged_in): ?>
                <span class="nav-user"><?= $nav_username ?></span>
                <a href="/dashboard.php">HOME</a>
                <a href="/transfer.php">TRANSFER</a>
                <a href="/search.php">SEARCH</a>
                <a href="/profile.php">PROFILE</a>
                <a href="/transaction_history.php">HISTORY</a>
                <!--
                    LOGOUT uses a POST form — not a link.
                    Why: A plain <a href="/logout.php?csrf_token=..."> is fragile.
                    The token embedded in the URL may be stale if the page was cached
                    or the user stays on a page for longer than the token lifetime.
                    A POST form always submits the current token from the session.
                    Styled as an inline form so it looks identical to a nav link.
                -->
                <form method="POST" action="/logout.php" class="nav-logout-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($logout_csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="nav-logout-btn">LOGOUT</button>
                </form>
            <?php else: ?>
                <a href="/login.php">LOGIN</a>
                <a href="/register.php">REGISTER</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="atm-statusbar">
        <span>&gt; <?= strtoupper($page_title) ?></span>
        <span>
            <?php if ($is_logged_in): ?>
                SESSION ACTIVE <span class="status-blink">●</span>
            <?php else: ?>
                GUEST
            <?php endif; ?>
            &nbsp;|&nbsp; <?= date('d M Y, H:i') ?>
        </span>
    </div>

    <main class="atm-main">