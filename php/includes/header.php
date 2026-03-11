<?php // includes/header.php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>TransactiWar — <?= h($pageTitle ?? 'Home') ?></title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="navbar">
  <a class="brand" href="/history.php">⚔️ TransactiWar</a>
  <div class="nav-links">
    <a href="/search.php">🔍 Search</a>
    <a href="/transfer.php">💸 Send</a>
    <a href="/history.php">📜 History</a>
    <a href="/profile.php">👤 <?= h($_SESSION['username'] ?? '') ?></a>
    <a href="/logout.php" class="btn-logout">Logout</a>
  </div>
</nav>
<main class="container">
<?php foreach (['flash_success'=>'alert-success','flash_error'=>'alert-error'] as $k=>$cls): ?>
  <?php if (!empty($_SESSION[$k])): ?>
    <div class="alert <?= $cls ?>"><?= h($_SESSION[$k]) ?></div>
    <?php unset($_SESSION[$k]); ?>
  <?php endif; ?>
<?php endforeach; ?>
