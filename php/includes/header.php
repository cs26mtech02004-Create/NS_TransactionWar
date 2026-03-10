<?php
// includes/header.php — shared nav, include after session_start() + auth_required()
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TransactiWar — <?= h($pageTitle ?? 'Dashboard') ?></title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="navbar">
  <a class="brand" href="/history.php">⚔️ TransactiWar</a>
  <div class="nav-links">
    <a href="/search.php">🔍 Search</a>
    <a href="/transfer.php">💸 Send Money</a>
    <a href="/history.php">📜 History</a>
    <span class="nav-user">👤 <?= h($_SESSION['username']) ?></span>
  </div>
</nav>
<main class="container">
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success"><?= h($_SESSION['flash_success']) ?></div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-error"><?= h($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
