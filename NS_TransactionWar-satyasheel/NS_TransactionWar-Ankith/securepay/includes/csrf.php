<?php
/**
 * FILE: includes/csrf.php
 * PURPOSE: Generate and validate CSRF tokens for every form.
 *
 * WHAT IS CSRF?
 *   Cross-Site Request Forgery. Imagine you are logged into SecurePay.
 *   You then visit attacker.com which has this hidden HTML:
 *
 *     <form action="https://securepay.com/transfer.php" method="POST">
 *       <input name="to_user" value="attacker_id">
 *       <input name="amount" value="100">
 *     </form>
 *     <script>document.forms[0].submit();</script>
 *
 *   Your browser automatically sends your SecurePay session cookie with
 *   this request (because the cookie is scoped to securepay.com).
 *   The server sees a valid session and processes the transfer — you just
 *   got robbed without clicking anything.
 *
 * THE FIX — SYNCHRONISER TOKEN PATTERN:
 *   1. When we render a form, we generate a random secret token and store
 *      it in the user's SESSION on the server.
 *   2. We embed the same token as a hidden field in the HTML form.
 *   3. When the form is submitted, we check that the submitted token matches
 *      the one in the session.
 *   4. attacker.com cannot know the token (it's secret, per-session) so
 *      any forged request will have the wrong or missing token → rejected.
 *
 * NOTE: SameSite=Strict on the session cookie also blocks CSRF, but we
 *   implement tokens as well because defence-in-depth means multiple layers.
 *   One layer failing does not compromise the whole system.
 */

require_once __DIR__ . '/../config/session.php';

/**
 * generate_csrf_token()
 * Creates a cryptographically random token and stores it in the session.
 * Returns the token so it can be embedded in a form hidden field.
 *
 * bin2hex(random_bytes(32)) produces 64 hex characters of true randomness
 * from the OS's CSPRNG (/dev/urandom on Linux). This cannot be guessed.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * csrf_input()
 * Returns the HTML for a hidden CSRF input field.
 * Drop this inside every <form>:
 *
 *   <?= csrf_input() ?>
 *
 * htmlspecialchars() on the token value is good practice even though
 * hex strings can't contain HTML special characters — be explicit.
 */
function csrf_input(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' 
           . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * verify_csrf_token()
 * Call this at the top of every POST handler.
 * If the token is missing or wrong, stop execution immediately.
 *
 * hash_equals() does a TIMING-SAFE comparison.
 * WHY? Normal string comparison (===) short-circuits: it stops at the
 * first character that differs. An attacker can measure response times
 * to guess the token character by character (timing attack).
 * hash_equals() always compares ALL characters regardless of where
 * the mismatch is, so timing gives no information.
 */
function verify_csrf_token(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    if (empty($stored) || !hash_equals($stored, $submitted)) {
        // Log the attempt
        error_log('[CSRF FAIL] IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') 
                  . ' URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }

    // Rotate the token after each use.
    // This makes CSRF tokens single-use — even if an attacker somehow
    // intercepts one, it stops working the moment the legitimate user
    // submits a form.
    unset($_SESSION['csrf_token']);
}