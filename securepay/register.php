<?php
/**
 * FILE: register.php
 * PURPOSE: User registration — shows form and handles submission.
 *
 * FLOW:
 *   GET  request → show empty registration form
 *   POST request → validate input → create user → redirect to login
 *
 * SECURITY MEASURES IN THIS FILE:
 *   1. CSRF token verification on POST
 *   2. Server-side input validation (never trust client-side)
 *   3. Password hashing with bcrypt (PASSWORD_BCRYPT)
 *   4. PDO prepared statements (SQL injection prevention)
 *   5. Duplicate username/email check before insert
 *   6. Generic error messages (no information leakage)
 *   7. New user balance set to Rs. 100 at the DB level
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in, no need to register — send to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit();
}

$errors  = [];   // Validation error messages shown to user
$success = '';   // Success message

// ── POST: HANDLE FORM SUBMISSION ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // STEP 1: Verify CSRF token FIRST — before touching any user input.
    // If the token is invalid, this dies immediately (see csrf.php).
    verify_csrf_token();

    // STEP 2: Collect and sanitise input.
    // trim() removes leading/trailing whitespace.
    // We do NOT use htmlspecialchars() here — that is for OUTPUT, not storage.
    // Storing escaped data in the DB is wrong; you'd double-escape on display.
    // Store raw, escape on output.
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';
    $confirm  =       $_POST['confirm']  ?? '';

    // STEP 3: Validate each field.

    // Username: 3–30 chars, alphanumeric + underscores only.
    // Regex: ^ = start, [a-zA-Z0-9_] = allowed chars, {3,30} = length, $ = end
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3–30 characters (letters, numbers, underscore only).';
    }

    // Email: use PHP's built-in filter — more reliable than a custom regex
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 254) {
        // RFC 5321 max email length
        $errors[] = 'Email address is too long.';
    }

    // Password strength rules — enforce server-side
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        // bcrypt silently truncates at 72 bytes — reject longer passwords
        // to avoid a false sense of security
        $errors[] = 'Password must be 72 characters or fewer.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    // Confirm password must match
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // STEP 4: If no validation errors, attempt to create the account
    if (empty($errors)) {

        // Check for duplicate username OR email in one query.
        // WHY ONE QUERY? Reduces DB round trips. Also, we return a generic
        // error — we don't tell the user WHICH field is taken, to prevent
        // username/email enumeration attacks.
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            // Deliberately vague: attacker cannot discover if username or
            // email was the conflict
            $errors[] = 'An account with that username or email already exists.';
        } else {

            // STEP 5: Hash the password.
            // PASSWORD_BCRYPT generates a random salt automatically and
            // embeds it in the hash string. Each call produces a different
            // hash even for the same password.
            // Cost factor 12 means 2^12 = 4096 iterations — slow enough to
            // make brute-force expensive, fast enough for normal login.
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // STEP 6: Insert the user.
            // Balance of 100.00 is set here in the INSERT — not from user input.
            // Never accept a starting balance from the client side.
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, balance, created_at)
                 VALUES (?, ?, ?, 100.00, NOW())'
            );
            $stmt->execute([$username, $email, $hash]);

            // Registration successful — redirect to login.
            // Use POST/Redirect/GET pattern: redirect after POST prevents
            // browser "resubmit form?" prompt on page refresh.
            $_SESSION['flash_success'] = 'Account created! You have been credited Rs. 100. Please log in.';
            header('Location: /login.php');
            exit();
        }
    }
}

// ── GET: RENDER THE FORM ──────────────────────────────────────
$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-auth-wrap">
    <div class="atm-auth-box">

        <div class="atm-auth-logo">
            <h1>SECURE<span style="color:var(--amber)">PAY</span></h1>
            <p class="tagline">NEW ACCOUNT REGISTRATION</p>
        </div>

        <?php foreach ($errors as $err): ?>
            <!-- htmlspecialchars() here escapes $err — even though we wrote these
                 strings ourselves, it's a good habit to always escape output -->
            <div class="atm-alert atm-alert-error">
                ⚠ <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <div class="atm-panel">
            <div class="atm-panel-header">CREATE ACCOUNT</div>
            <div class="atm-panel-body">

                <form method="POST" action="/register.php" autocomplete="off">

                    <?= csrf_input() ?>
                    <!--
                        csrf_input() outputs:
                          <input type="hidden" name="csrf_token" value="[64-char-hex]">
                        This token is verified on POST before anything else runs.
                    -->

                    <div class="atm-form-group">
                        <label for="username">USERNAME</label>
                        <!--
                            value preserved on error so user doesn't retype everything.
                            htmlspecialchars() prevents XSS: if user typed <script> in username,
                            it's rendered as literal text in the value attribute, not executed.
                        -->
                        <input
                            type="text"
                            id="username"
                            name="username"
                            maxlength="30"
                            pattern="[a-zA-Z0-9_]{3,30}"
                            placeholder="3–30 chars, letters/numbers/underscore"
                            value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="off"
                        >
                    </div>

                    <div class="atm-form-group">
                        <label for="email">EMAIL ADDRESS</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="254"
                            placeholder="user@example.com"
                            value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="off"
                        >
                    </div>

                    <div class="atm-form-group">
                        <label for="password">
                            PASSWORD
                            <span id="pw-strength" style="float:right;font-size:0.75rem"></span>
                        </label>
                        <!--
                            autocomplete="new-password" tells the browser this is a new
                            password field. Without it, the browser might autofill an old
                            password and the user doesn't notice.
                        -->
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            maxlength="72"
                            placeholder="Min 8 chars, 1 uppercase, 1 number"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="atm-form-group">
                        <label for="confirm">CONFIRM PASSWORD</label>
                        <input
                            type="password"
                            id="confirm"
                            name="confirm"
                            minlength="8"
                            maxlength="72"
                            placeholder="Re-enter password"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-full">
                            ▶ CREATE ACCOUNT
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <p class="text-center text-muted mt-2" style="font-size:0.82rem">
            Already have an account?
            <a href="/login.php">LOGIN HERE</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>