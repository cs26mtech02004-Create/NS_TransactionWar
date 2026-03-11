<?php
/**
 * FILE: login.php
 * PURPOSE: User login — shows form and handles authentication.
 *
 * SECURITY MEASURES IN THIS FILE:
 *   1. CSRF token verification on POST
 *   2. Rate limiting: 5 failures per IP → 15-minute lockout (stored in DB)
 *   3. Constant-time password verification (password_verify)
 *   4. Generic error: "Invalid credentials" (no username/password distinction)
 *   5. Session regeneration after successful login (prevents session fixation)
 *   6. Flash message system (success messages survive one redirect)
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

// Already logged in — send to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit();
}

$error   = '';
$success = '';

// Show flash success from registration redirect (if any)
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ── RATE LIMITING HELPER ──────────────────────────────────────
/**
 * check_rate_limit($ip)
 * Returns true if this IP is currently locked out.
 *
 * HOW IT WORKS:
 *   We store login attempts in the `login_attempts` table:
 *     (ip_address, attempt_time)
 *   We count attempts in the last 15 minutes.
 *   If >= 5, the IP is locked out for 15 minutes from the last attempt.
 *
 * WHY IN THE DATABASE?
 *   PHP sessions are per-user, not per-IP. Memory (APCu) would work too
 *   but requires extra setup. The DB approach works out of the box with
 *   no extra services and survives server restarts.
 *
 * NOTE: We use REMOTE_ADDR (the actual TCP connection IP), not any
 *   HTTP header. Headers like X-Forwarded-For can be forged by the client.
 */
function check_rate_limit(PDO $pdo, string $ip): bool {
    $window = 15 * 60; // 15 minutes in seconds
    $max    = 5;       // Max attempts before lockout

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ?
           AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, $window]);
    $count = (int) $stmt->fetchColumn();

    return $count >= $max;
}

/**
 * record_failed_attempt($ip)
 * Logs a failed attempt. Called only on failed logins — NOT on success.
 */
function record_failed_attempt(PDO $pdo, string $ip): void {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())'
    );
    $stmt->execute([$ip]);

    // Clean up old records (older than 15 min) to keep the table small.
    // We do this probabilistically (1-in-10 chance) so it doesn't slow
    // every failed login — "probabilistic GC" pattern.
    if (rand(1, 10) === 1) {
        $pdo->prepare(
            'DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        )->execute();
    }
}

// ── POST: HANDLE LOGIN ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // STEP 1: CSRF check
    verify_csrf_token();

    // STEP 2: Get the real client IP (TCP level — cannot be forged)
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // STEP 3: Check rate limit BEFORE doing any DB user lookup
    // This prevents brute force even before we identify the account
    if (check_rate_limit($pdo, $client_ip)) {
        $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
    } else {

        $identifier = trim($_POST['identifier'] ?? ''); // username or email
        $password   =       $_POST['password']   ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter your username/email and password.';
        } else {
            // STEP 4: Look up user by username OR email
            // Using a single query — no separate "does user exist?" check first.
            // WHY? Two-step lookup leaks whether the username exists.
            $stmt = $pdo->prepare(
                'SELECT id, username, password_hash, balance
                 FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1'
            );
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            // STEP 5: Verify password with constant-time comparison
            // password_verify() is timing-safe by design.
            //
            // IMPORTANT: We call password_verify() even if $user is false.
            // WHY? If we skip verification when the user isn't found, the
            // "user not found" case returns faster than the "wrong password"
            // case. An attacker measuring response time can enumerate valid
            // usernames. By always hashing (against a dummy hash), both paths
            // take the same time.
            $dummy_hash = '$2y$12$invalidhashfortimingneutralizationpurposesonlyXXXXXXXXXXX';
            $hash_to_check = $user ? $user['password_hash'] : $dummy_hash;
            $password_correct = password_verify($password, $hash_to_check);

            if ($user && $password_correct) {
                // STEP 6: Successful login

                // Regenerate session ID immediately to prevent session fixation
                // (destroys old session data, creates a new session ID)
                regenerate_session();

                // Store user data in session
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                // Store IP in session — we check this on sensitive operations
                // to detect session hijacking (IP change mid-session)
                $_SESSION['login_ip'] = $client_ip;

                // Redirect to dashboard (or wherever they were trying to go)
                $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
                unset($_SESSION['redirect_after_login']);

                // Validate the redirect URL to prevent open redirect attacks.
                // OPEN REDIRECT: attacker sends victim a link like:
                // /login.php?redirect=https://evil.com
                // After login, victim gets silently sent to evil.com.
                // Fix: only allow redirects to paths on OUR domain (starting with /).
                if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
                    $redirect = '/dashboard.php';
                }

                header('Location: ' . $redirect);
                exit();

            } else {
                // STEP 7: Failed login — record attempt, show generic error
                record_failed_attempt($pdo, $client_ip);

                // GENERIC ERROR: Do NOT say "wrong password" vs "user not found"
                // Both cases show the exact same message
                $error = 'Invalid credentials. Please check your username/email and password.';
            }
        }
    }
}

// ── GET: RENDER THE FORM ──────────────────────────────────────
$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-auth-wrap">
    <div class="atm-auth-box">

        <div class="atm-auth-logo">
            <h1>SECURE<span style="color:var(--amber)">PAY</span></h1>
            <p class="tagline">AUTHORISED ACCESS ONLY</p>
        </div>

        <?php if ($success): ?>
            <div class="atm-alert atm-alert-success">
                ✓ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="atm-alert atm-alert-error">
                ⚠ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="atm-panel">
            <div class="atm-panel-header">ENTER CREDENTIALS</div>
            <div class="atm-panel-body">

                <form method="POST" action="/login.php" autocomplete="off">

                    <?= csrf_input() ?>

                    <div class="atm-form-group">
                        <label for="identifier">USERNAME OR EMAIL</label>
                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            maxlength="254"
                            placeholder="Enter username or email"
                            value="<?= htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="off"
                        >
                    </div>

                    <div class="atm-form-group">
                        <label for="password">PASSWORD</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            maxlength="72"
                            placeholder="Enter password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-full">
                            ▶ AUTHENTICATE
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <p class="text-center text-muted mt-2" style="font-size:0.82rem">
            New user?
            <a href="/register.php">REGISTER HERE</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>