<?php
session_start();
require_once __DIR__ . '/db.php';
auth_required();
logActivity('history.php', $_SESSION['username']);

$pageTitle = 'Transaction History';
$pdo       = getDB();
$uid       = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        t.id, t.amount, t.comment, t.created_at, t.status,
        s.id AS sender_id,   s.username AS sender,
        r.id AS receiver_id, r.username AS receiver,
        CASE WHEN t.sender_id = ? THEN 'sent' ELSE 'received' END AS direction
    FROM transactions t
    JOIN users s ON s.id = t.sender_id
    JOIN users r ON r.id = t.receiver_id
    WHERE t.sender_id = ? OR t.receiver_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$uid, $uid, $uid]);
$transactions = $stmt->fetchAll();

$totalSent = $totalReceived = 0;
foreach ($transactions as $t) {
    if ($t['direction'] === 'sent')     $totalSent     += $t['amount'];
    if ($t['direction'] === 'received') $totalReceived += $t['amount'];
}

$b = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$b->execute([$uid]);
$balance = $b->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>📜 Transaction History</h1>
  <p class="subtitle">All your sent and received transfers</p>
</div>

<div class="stats-row">
  <div class="stat-card stat-balance">
    <span class="stat-label">Current Balance</span>
    <span class="stat-value">₹<?= number_format($balance, 2) ?></span>
  </div>
  <div class="stat-card stat-sent">
    <span class="stat-label">Total Sent</span>
    <span class="stat-value text-danger">−₹<?= number_format($totalSent, 2) ?></span>
  </div>
  <div class="stat-card stat-received">
    <span class="stat-label">Total Received</span>
    <span class="stat-value text-success">+₹<?= number_format($totalReceived, 2) ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Total Transactions</span>
    <span class="stat-value"><?= count($transactions) ?></span>
  </div>
</div>

<div class="filter-bar">
  <button class="filter-btn active" onclick="filterTable('all',this)">All</button>
  <button class="filter-btn" onclick="filterTable('sent',this)">💸 Sent</button>
  <button class="filter-btn" onclick="filterTable('received',this)">📥 Received</button>
</div>

<?php if (empty($transactions)): ?>
  <div class="card empty-state">
    <p>No transactions yet. <a href="/transfer.php">Send money</a> to get started!</p>
  </div>
<?php else: ?>
  <div class="card table-card">
    <table class="data-table" id="txn-table">
      <thead>
        <tr>
          <th>#</th><th>Date & Time</th><th>From</th><th>To</th>
          <th>Amount</th><th>Comment</th><th>Type</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr class="txn-row" data-dir="<?= $t['direction'] ?>">
          <td class="text-muted"><?= (int)$t['id'] ?></td>
          <td><?= h(date('d M Y, h:i A', strtotime($t['created_at']))) ?></td>
          <td><?= (int)$t['sender_id'] === $uid ? '<strong class="text-self">You</strong>' : h($t['sender']) ?></td>
          <td><?= (int)$t['receiver_id'] === $uid ? '<strong class="text-self">You</strong>' : h($t['receiver']) ?></td>
          <td class="amount-cell <?= $t['direction'] === 'sent' ? 'text-danger' : 'text-success' ?>">
            <?= $t['direction'] === 'sent' ? '−' : '+' ?>₹<?= number_format($t['amount'], 2) ?>
          </td>
          <td class="comment-cell">
            <?= $t['comment'] ? h($t['comment']) : '<span class="text-muted">—</span>' ?>
          </td>
          <td>
            <span class="badge <?= $t['direction'] === 'sent' ? 'badge-sent' : 'badge-received' ?>">
              <?= ucfirst($t['direction']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
function filterTable(dir, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.txn-row').forEach(row => {
    row.style.display = (dir === 'all' || row.dataset.dir === dir) ? '' : 'none';
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
