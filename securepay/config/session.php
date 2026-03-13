<?php
/**
 * FILE: config/session.php
 * PURPOSE: Configure PHP sessions securely before starting them.
 *
 * CRITICAL RULE: This file must be included BEFORE session_start() on
 * every single page. The settings below only take effect if set before
 * the session begins. Getting this wrong silently uses PHP's insecure
 * defaults.
 *
 * WHAT IS A SESSION?
 *   When a user logs in, PHP generates a random ID (e.g., "abc123xyz").
 *   The SERVER stores data linked to that ID (who the user is, their role).
 *   The CLIENT (browser) stores only the ID in a cookie called PHPSESSID.
 *   On every request, the browser sends the cookie, the server looks up the data.
 *   An attacker who steals the cookie ID can impersonate the user — so we
 *   make the cookie as hard to steal as possible.
 */
define('SESSION_IDLE_TIMEOUT', 1800); // 30 minutes


// SESSION COOKIE SECURITY FLAGS
// -------------------------------------------------------

// httponly = true
//   The session cookie CANNOT be read by JavaScript (document.cookie).
//   This neutralises XSS attacks that try to steal the cookie:
//   even if an attacker injects <script> into your page, they can't
//   read the session cookie.
ini_set('session.cookie_httponly', 1);

// secure = true
//   The cookie is ONLY sent over HTTPS connections, never plain HTTP.
//   Prevents the cookie from being intercepted by a network eavesdropper.
//   (In Docker dev with HTTP, set this to 0 temporarily, but ALWAYS 1 in prod)
ini_set('session.cookie_secure', 1);

// samesite = Strict
//   The cookie is NOT sent with requests that originate from another website.
//   Example: if attacker's site has a hidden form that POSTs to your /transfer.php,
//   the browser will NOT attach your session cookie — so the server sees no session.
//   This is a strong second layer of CSRF protection (on top of CSRF tokens).
ini_set('session.cookie_samesite', 'Strict');

// SESSION ID STRENGTH
// -------------------------------------------------------

// use_strict_mode = 1
//   If a browser sends a session ID that doesn't exist on the server,
//   PHP will REJECT it and generate a new one instead of creating a new
//   session with the attacker's chosen ID.
//   Prevents Session Fixation: attacker can't pre-set a session ID and
//   wait for a victim to log in with it.
ini_set('session.use_strict_mode', 1);

// entropy_length / bits_per_character: make the session ID unpredictable
// PHP 7.1+ uses a cryptographically secure random generator by default.
// These settings ensure the ID is 128 bits of randomness — effectively
// impossible to guess by brute force.
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);  // 48 chars * 6 bits = 288 bits

// SESSION STORAGE
// -------------------------------------------------------

// use_only_cookies = 1
//   Session ID must come from a cookie, NEVER from a URL parameter
//   (e.g., ?PHPSESSID=abc123 in the URL).
//   URL-based sessions are visible in browser history, server logs,
//   referrer headers — very easy to steal.
ini_set('session.use_only_cookies', 1);

// SESSION LIFETIME
// -------------------------------------------------------
// Session expires after 30 minutes of inactivity.
// The browser-side cookie also expires (not a "session cookie" that
// survives until browser close — which is harder to control).
$session_lifetime = 1800; // 30 minutes in seconds
ini_set('session.gc_maxlifetime', $session_lifetime);
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path'     => '/',
    'secure'   => true,      // HTTPS only — change to false for local HTTP dev
    'httponly' => true,
    'samesite' => 'Strict',
]);

// NOW we can safely start the session.
// All pages that need session data include this file, which starts the session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * FUNCTION: regenerate_session()
 * Call this immediately after a successful login.
 *
 * WHY?
 *   Before login, the user might have visited the site as a guest.
 *   Their session ID was generated before authentication.
 *   If an attacker somehow got hold of that pre-login session ID
 *   (Session Fixation attack), they would have a valid ID for the now-
 *   authenticated session.
 *
 *   session_regenerate_id(true) does two things:
 *     1. Creates a brand-new session ID
 *     2. DELETES the old session data from the server
 *   So even if the attacker had the old ID, it is now worthless.
 */
function regenerate_session(): void {
    session_regenerate_id(true);
}

/**
 * FUNCTION: destroy_session()
 * Call this on logout.
 *
 * A proper logout must do THREE things:
 *   1. Clear all session variables in memory
 *   2. Delete the session data file on the server
 *   3. Overwrite the cookie in the browser with an expired one
 * Doing only one or two is insufficient — the session can often be
 * resurrected. This function does all three.
 */
function destroy_session(): void {
    // 1. Wipe all data from the $_SESSION superglobal
    $_SESSION = [];

    // 2. Delete the session file on the server
    session_destroy();

    // 3. Overwrite the cookie with an expired timestamp
    //    This tells the browser to delete it immediately.
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,   // A time far in the past
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}