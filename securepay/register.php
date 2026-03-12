<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit();
}

$errors   = [];
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';
    $confirm  =       $_POST['confirm']  ?? '';

    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username: 3–30 chars, letters/numbers/underscore only.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $errors[] = 'Enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password must be 72 characters or fewer.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with that username or email already exists.';
        } else {
            // Generate a unique 12-char public ID (not sequential, not guessable)
            // bin2hex(random_bytes(6)) = 12 hex characters
            // Collision probability across millions of users is negligible.
            do {
                $public_id = bin2hex(random_bytes(6));
                $check = $pdo->prepare('SELECT id FROM users WHERE public_id = ?');
                $check->execute([$public_id]);
            } while ($check->fetch()); // Regenerate if collision (extremely rare)

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                'INSERT INTO users (public_id, username, email, password_hash, balance, created_at)
                 VALUES (?, ?, ?, ?, 100.00, NOW())'
            );
            $stmt->execute([$public_id, $username, $email, $hash]);

            $_SESSION['flash_success'] = 'Account created! Rs. 100 credited. Please log in.';
            header('Location: /login.php');
            exit();
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-auth-wrap">
    <div class="atm-auth-box">

        <div class="atm-auth-logo">
            <h1>SECURE<span>PAY</span></h1>
            <p class="tagline">NEW ACCOUNT REGISTRATION</p>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="atm-alert atm-alert-error">
                <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <div class="atm-panel">
            <div class="atm-panel-header">CREATE ACCOUNT</div>
            <div class="atm-panel-body">

                <form method="POST" action="/register.php" autocomplete="off">
                    <?= csrf_input() ?>

                    <div class="atm-form-group">
                        <label for="username">USERNAME</label>
                        <input type="text" id="username" name="username"
                               maxlength="30" pattern="[a-zA-Z0-9_]{3,30}"
                               placeholder="3–30 chars, letters/numbers/underscore"
                               value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                               required autocomplete="off">
                    </div>

                    <div class="atm-form-group">
                        <label for="email">EMAIL ADDRESS</label>
                        <input type="email" id="email" name="email"
                               maxlength="254" placeholder="user@example.com"
                               value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                               required autocomplete="off">
                    </div>

                    <div class="atm-form-group">
                        <label for="password">
                            PASSWORD
                            <span id="pw-strength" style="float:right;font-size:0.75rem"></span>
                        </label>
                        <div class="pw-wrap">
                            <input type="password" id="password" name="password"
                                   minlength="8" maxlength="72"
                                   placeholder="Min 8 chars, 1 uppercase, 1 number"
                                   required autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-target="password"
                                    aria-label="Show password">&#128065;</button>
                        </div>
                    </div>

                    <div class="atm-form-group">
                        <label for="confirm">CONFIRM PASSWORD</label>
                        <div class="pw-wrap">
                            <input type="password" id="confirm" name="confirm"
                                   minlength="8" maxlength="72"
                                   placeholder="Re-enter password"
                                   required autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-target="confirm"
                                    aria-label="Show password">&#128065;</button>
                        </div>
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-full">
                            CREATE ACCOUNT
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <p class="text-center text-muted mt-2" style="font-size:0.82rem">
            Already have an account? <a href="/login.php">Sign in</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>