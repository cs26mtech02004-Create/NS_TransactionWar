<?php
session_start();
require_once __DIR__ . '/db.php';
auth_required();
logActivity('search.php', $_SESSION['username']);

$pageTitle = 'Search Users';
$results   = [];
$query     = trim($_GET['q'] ?? '');
$error     = '';

if ($query !== '') {
    $pdo    = getDB();
    $like   = '%' . $query . '%';
    $numId  = is_numeric($query) ? (int)$query : -1;
    $stmt   = $pdo->prepare("
        SELECT id, username, email
        FROM users
        WHERE username LIKE ? OR id = ?
        ORDER BY username
        LIMIT 20
    ");
    $stmt->execute([$like, $numId]);
    $results = $stmt->fetchAll();
    if (empty($results)) {
        $error = 'No users found matching "' . h($query) . '".';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>🔍 Search Users</h1>
  <p class="subtitle">Find users by username or user ID to send money</p>
</div>

<div class="card">
  <form method="GET" action="/search.php" class="search-form">
    <div class="input-group">
      <input type="text" name="q" value="<?= h($query) ?>"
             placeholder="Enter username or user ID…"
             autocomplete="off" maxlength="100" class="input-field input-search">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </form>
</div>

<?php if ($error && $query !== ''): ?>
  <div class="alert alert-warning"><?= $error ?></div>
<?php endif; ?>

<?php if (!empty($results)): ?>
<div class="card">
  <div class="card-title">Results for "<?= h($query) ?>"</div>
  <table class="data-table">
    <thead>
      <tr><th>User ID</th><th>Username</th><th>Email</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($results as $u): ?>
        <?php $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']); ?>
        <tr <?= $isSelf ? 'class="row-self"' : '' ?>>
          <td><span class="uid-badge">#<?= (int)$u['id'] ?></span></td>
          <td><strong><?= h($u['username']) ?></strong><?= $isSelf ? ' <span class="self-tag">(You)</span>' : '' ?></td>
          <td><?= h($u['email']) ?></td>
          <td>
            <?php if (!$isSelf): ?>
              <a href="/transfer.php?to=<?= (int)$u['id'] ?>&name=<?= urlencode($u['username']) ?>"
                 class="btn btn-sm btn-accent">💸 Send Money</a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
