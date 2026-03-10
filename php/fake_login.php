<?php
// fake_login.php — FOR TESTING ONLY. DELETE BEFORE SUBMISSION.
// Visit this page first to simulate a login, then test Task 3 pages.

session_start();

$users = [
    1 => 'alice',
    2 => 'bob',
    3 => 'charlie',
    4 => 'dave',
    5 => 'eve',
];

// Switch user via ?as=2
$as = isset($_GET['as']) ? (int)$_GET['as'] : 1;
if (!isset($users[$as])) $as = 1;

$_SESSION['user_id']  = $as;
$_SESSION['username'] = $users[$as];
// CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html>
<head>
  <title>Test Login</title>
  <style>
    body { font-family: sans-serif; background: #0f1117; color: #e2e8f0; padding: 40px; }
    a    { color: #60a5fa; margin-right: 12px; }
    .box { background: #1e2636; border: 1px solid #2a3650; border-radius: 10px; padding: 24px; max-width: 480px; }
    h2   { margin-bottom: 16px; }
    .links { margin-top: 20px; display: flex; flex-direction: column; gap: 8px; }
    .btn { display:inline-block; padding:8px 18px; background:#3b82f6; color:#fff; border-radius:6px; text-decoration:none; font-size:14px; }
    .switch { margin-top:16px; font-size:13px; color:#64748b; }
  </style>
</head>
<body>
<div class="box">
  <h2>🔐 Test Login</h2>
  <p>Logged in as: <strong style="color:#60a5fa"><?= $users[$as] ?></strong> (user_id = <?= $as ?>)</p>
  <div class="links">
    <a class="btn" href="/search.php">🔍 Search Users</a>
    <a class="btn" href="/transfer.php">💸 Send Money</a>
    <a class="btn" href="/history.php">📜 Transaction History</a>
  </div>
  <div class="switch">
    Switch user:
    <?php foreach ($users as $id => $name): ?>
      <a href="?as=<?= $id ?>"><?= $name ?></a>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
