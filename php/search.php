<?php
// TASK 3 — Search Users
session_start();
require_once __DIR__ . '/db.php';
auth_required();
logActivity('search.php', $_SESSION['username']);

$pageTitle = 'Search Users';
$results   = [];
$query     = trim($_GET['q'] ?? '');

if ($query !== '') {
    $like  = '%' . $query . '%';
    $numId = is_numeric($query) ? (int)$query : -1;
    $stmt  = getDB()->prepare("
        SELECT id, username, email, profile_image
        FROM users
        WHERE username LIKE ? OR id = ?
        ORDER BY username LIMIT 20
    ");
    $stmt->execute([$like, $numId]);
    $results = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>🔍 Search Users</h1>
  <p class="subtitle">Find users by username or user ID</p>
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

<?php if ($query !== '' && empty($results)): ?>
  <div class="alert alert-warning">No users found for "<?= h($query) ?>".</div>
<?php endif; ?>

<?php if (!empty($results)): ?>
<div class="card">
  <div class="card-title">Results</div>
  <table class="data-table">
    <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($results as $u): ?>
        <?php $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']); ?>
        <tr>
          <td><span class="uid-badge">#<?= (int)$u['id'] ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if ($u['profile_image']): ?>
                <img src="/uploads/profiles/<?= h($u['profile_image']) ?>" class="avatar-tiny">
              <?php else: ?>
                <div class="avatar-tiny-placeholder"><?= strtoupper(substr($u['username'],0,1)) ?></div>
              <?php endif; ?>
              <strong><?= h($u['username']) ?></strong>
              <?= $isSelf ? '<span class="self-tag">(You)</span>' : '' ?>
            </div>
          </td>
          <td><?= h($u['email']) ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="/view_profile.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline">View Profile</a>
            <?php if (!$isSelf): ?>
              <a href="/transfer.php?to=<?= (int)$u['id'] ?>&name=<?= urlencode($u['username']) ?>"
                 class="btn btn-sm btn-accent">💸 Send Money</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
