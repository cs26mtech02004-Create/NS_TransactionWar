<?php
/**
 * FILE: logout.php
 * PURPOSE: Securely terminate the user's session.
 *
 * A PROPER LOGOUT MUST DO THREE THINGS:
 *   1. Wipe $_SESSION data in PHP memory
 *   2. Delete the session file on the server
 *   3. Tell the browser to delete the session cookie
 *
 * Doing only one or two allows the session to be partially resurrected.
 * For example: clearing $_SESSION but not deleting the server file means
 * the file still exists and a new request with the old cookie will create
 * a blank session rather than redirecting to login — potentially allowing
 * a "ghost" session.
 *
 * The destroy_session() function in config/session.php handles all three.
 *
 * CSRF PROTECTION ON LOGOUT:
 *   Even logout needs protection. Without it, an attacker can embed:
 *     <img src="https://securepay.com/logout.php">
 *   on their page and log out any victim who visits it (annoying at minimum,
 *   disruptive as part of a larger attack).
 *
 *   Logout is triggered via POST with a CSRF token, OR via a GET link
 *   where we verify the token is in the URL (simpler approach used here).
 *   We use a GET + token approach because logout buttons are typically links.
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/csrf.php';

// Verify CSRF token passed as a GET parameter
// The logout link in the nav includes ?csrf_token=[token]
// This prevents CSRF-forced-logout attacks
$token_from_url = $_GET['csrf_token'] ?? '';
$stored_token   = $_SESSION['csrf_token'] ?? '';

// Only validate if there IS a session (logged in).
// If not logged in, just redirect silently — nothing to log out from.
if (isset($_SESSION['user_id'])) {
    if (empty($stored_token) || !hash_equals($stored_token, $token_from_url)) {
        // Invalid CSRF token — someone tried to force a logout
        error_log('[CSRF FAIL on logout] IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(403);
        die('Invalid logout request.');
    }
}

// Destroy the session completely (all 3 steps — see session.php)
destroy_session();

// Redirect to login page with a goodbye message
// We use a new session (session was destroyed, session_start is called
// again inside session.php next time it's included) to pass the flash message
// Actually, since session is destroyed, we pass the message as a query param
// — but query params can be tampered with. For a simple non-security-critical
// message, this is fine. We escape it on output.
header('Location: /login.php?msg=logged_out');
exit();