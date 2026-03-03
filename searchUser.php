<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require 'db.php';       // your PDO/MySQLi connection
logActivity($pdo, 'search_users.php', $_SESSION['username']);

$results = [];
$query   = trim($_GET['q'] ?? '');

if ($query !== '') {
    // Search by username OR numeric user ID
    $stmt = $pdo->prepare(
        "SELECT id, username, email FROM users
         WHERE username LIKE ? OR id = ?
         LIMIT 20"
    );
    $like = '%' . $query . '%';
    $id   = is_numeric($query) ? (int)$query : -1;
    $stmt->execute([$like, $id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!-- HTML: show search form + loop $results -->
 <form method="GET" action="search_users.php">
  <input type="text" name="q"
         placeholder="Search by username or user ID"
         value="<?= htmlspecialchars($query) ?>"
         required>
  <button type="submit">Search</button>
</form>

<!-- Results -->
<?php foreach ($results as $user): ?>
  <div>
    ID: <?= (int)$user['id'] ?> |
    <?= htmlspecialchars($user['username']) ?>
    <a href="transfer.php?to=<?= $user['id'] ?>">Send Money</a>
  </div>
<?php endforeach; ?>