<?php
/**
 * FILE: includes/auth_guard.php
 * PURPOSE: Protect a page so only logged-in users can access it.
 *
 * HOW TO USE:
 *   Add this ONE line at the very top of any page that requires login:
 *     require_once __DIR__ . '/../includes/auth_guard.php';
 *
 * WHAT IT DOES:
 *   Checks if $_SESSION['user_id'] is set (meaning the user logged in).
 *   If NOT set, the user is redirected to login.php and the script stops.
 *   If set, the script continues normally.
 *
 * WHY A SEPARATE FILE?
 *   Centralisation. If you decide to change the auth check logic (e.g.,
 *   add an "account banned" check), you change it in ONE place and every
 *   protected page benefits automatically. Without this, you'd have to
 *   update every single PHP file — and you'd inevitably miss one.
 *
 * SECURITY NOTE:
 *   Never rely on client-side JS or hidden form fields to "hide" pages.
 *   Security must be enforced on the SERVER. A user can disable JS,
 *   manually type URLs, or craft raw HTTP requests — server-side checks
 *   cannot be bypassed this way.
 */

// session.php starts the session and configures secure cookie flags.
// We require it here so auth_guard is self-contained — you only need
// to include auth_guard and everything it needs is pulled in automatically.
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Store the page they were trying to reach so we can redirect them
    // back after login (good UX and also tells us what was attempted).
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

    // header() sends an HTTP redirect response to the browser.
    // 'Location: login.php' tells the browser to go to login.php.
    header('Location: /login.php');

    // exit() is CRITICAL here.
    // Without it, PHP continues executing the rest of the page even after
    // sending the redirect header. The browser redirects, but the server
    // has still processed the page and potentially leaked data into the
    // response body. Always exit() immediately after a security redirect.
    exit();
}

// If we reach this line, the user is authenticated.
// The including file can now safely use $_SESSION['user_id'], etc.