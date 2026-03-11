<?php
// TASK 1 — Login
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: /history.php'); exit(); }
require_once __DIR__ . '/db.php';
logActivity('login.php', 'guest');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = getDB()->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID on login (session fixation protection)
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            logActivity('login.php', $user['username']);
            header('Location: /history.php'); exit();
        } else {
            // Generic error — don't reveal if username exists
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>TransactiWar — Login</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body class="auth-body">
<div class="auth-box">
  <div class="auth-logo">⚔️ TransactiWar</div>
  <h1 class="auth-title">Login</h1>
  <p class="auth-sub">Battle for Security, Compete for Supremacy</p>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/login.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" class="input-field" required autofocus placeholder="Your username">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" class="input-field" required placeholder="Your password">
    </div>
    <button type="submit" class="btn btn-primary btn-full">Login</button>
  </form>
  <p class="auth-footer">No account? <a href="/register.php">Register here</a></p>
</div>
</body>
</html>
