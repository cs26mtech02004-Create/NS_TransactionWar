<?php
// TASK 1 — User Registration
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: /history.php'); exit(); }
require_once __DIR__ . '/db.php';
logActivity('register.php', 'guest');

$errors = [];
$username = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Validate
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username))
        $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore only).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $pdo = getDB();
        // Check unique
        $s = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $s->execute([$username, $email]);
        if ($s->fetch()) {
            $errors[] = 'Username or email already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("INSERT INTO users (username,email,password_hash,balance) VALUES(?,?,?,100.00)")
                ->execute([$username, $email, $hash]);
            $_SESSION['flash_success'] = 'Account created! You have been credited ₹100. Please login.';
            header('Location: /login.php'); exit();
        }
    }
}

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>TransactiWar — Register</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body class="auth-body">
<div class="auth-box">
  <div class="auth-logo">⚔️ TransactiWar</div>
  <h1 class="auth-title">Create Account</h1>
  <p class="auth-sub">New users get ₹100 on registration</p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/register.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?= h($username) ?>" class="input-field" required maxlength="50" placeholder="e.g. rohit26">
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= h($email) ?>" class="input-field" required placeholder="you@example.com">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" class="input-field" required minlength="8" placeholder="Min. 8 characters">
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm" class="input-field" required placeholder="Repeat password">
    </div>
    <button type="submit" class="btn btn-primary btn-full">Create Account</button>
  </form>
  <p class="auth-footer">Already have an account? <a href="/login.php">Login</a></p>
</div>
</body>
</html>
