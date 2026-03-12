<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit();
}

$error   = '';
$success = '';

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

function check_rate_limit(PDO $pdo, string $ip): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ?
           AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() >= 5;
}

function record_failed_attempt(PDO $pdo, string $ip): void {
    $pdo->prepare('INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())')
        ->execute([$ip]);
    if (rand(1, 10) === 1) {
        $pdo->prepare('DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)')
            ->execute();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_token();

    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (check_rate_limit($pdo, $client_ip)) {
        $error = 'Too many failed attempts. Please wait 15 minutes.';
    } else {

        $identifier = trim($_POST['identifier'] ?? '');
        $password   =       $_POST['password']   ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter your username/email and password.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, username, password_hash FROM users
                 WHERE username = ? OR email = ? LIMIT 1'
            );
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            $dummy = '$2y$12$invalidhashfortimingneutralizationpurposesonlyXXXXXXXXXXX';
            $correct = password_verify($password, $user ? $user['password_hash'] : $dummy);

            if ($user && $correct) {
                regenerate_session();
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_ip'] = $client_ip;

                $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
                unset($_SESSION['redirect_after_login']);
                if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
                    $redirect = '/dashboard.php';
                }
                header('Location: ' . $redirect);
                exit();
            } else {
                record_failed_attempt($pdo, $client_ip);
                $error = 'Invalid credentials. Please try again.';
            }
        }
    }
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-auth-wrap">
    <div class="atm-auth-box">

        <div class="atm-auth-logo">
            <h1>SECURE<span>PAY</span></h1>
            <p class="tagline">AUTHORISED ACCESS ONLY</p>
        </div>

        <?php if ($success): ?>
            <div class="atm-alert atm-alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="atm-alert atm-alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="atm-panel">
            <div class="atm-panel-header">SIGN IN</div>
            <div class="atm-panel-body">

                <form method="POST" action="/login.php" autocomplete="off">
                    <?= csrf_input() ?>

                    <div class="atm-form-group">
                        <label for="identifier">USERNAME OR EMAIL</label>
                        <input type="text" id="identifier" name="identifier"
                               maxlength="254" placeholder="Enter username or email"
                               value="<?= htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required autocomplete="off">
                    </div>

                    <div class="atm-form-group">
                        <label for="password">PASSWORD</label>
                        <div class="pw-wrap">
                            <input type="password" id="password" name="password"
                                   maxlength="72" placeholder="Enter password"
                                   required autocomplete="current-password">
                            <button type="button" class="pw-toggle" data-target="password"
                                    aria-label="Show password">&#128065;</button>
                        </div>
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-full">
                            SIGN IN
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <p class="text-center text-muted mt-2" style="font-size:0.82rem">
            New user? <a href="/register.php">Create an account</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>