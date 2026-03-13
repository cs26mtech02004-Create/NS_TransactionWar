<?php
/**
 * FILE: transaction_history.php
 * PURPOSE: Display the logged-in user's full transaction history.
 *
 * SECURITY MEASURES:
 *   1. auth_guard — logged in only
 *   2. WHERE clause always filters by $_SESSION['user_id'] — users can
 *      ONLY see their own transactions, never anyone else's
 *   3. Page number validated as positive integer (no negative page attacks)
 *   4. All output escaped with htmlspecialchars()
 *   5. No sensitive data from other users exposed
 *      (shows other party's username only — not their balance or email)
 *
 * IDOR NOTE:
 *   There is no ?id= parameter here. The history is always for the
 *   logged-in user. An attacker cannot view another user's transactions
 *   by changing a URL parameter — there is no such parameter.
 */

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';

// ── PAGINATION ────────────────────────────────────────────────
// Show 15 transactions per page.
// Page number validated: must be a positive integer, defaults to 1.
define('PER_PAGE', 15);

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'default' => 1]
]);
$offset = ($page - 1) * PER_PAGE;

// ── COUNT TOTAL TRANSACTIONS ──────────────────────────────────
// For pagination: how many pages are there?
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM transactions
     WHERE sender_id = ? OR receiver_id = ?'
);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$total = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total / PER_PAGE));

// Clamp page to valid range
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * PER_PAGE; }

// ── FETCH TRANSACTIONS ────────────────────────────────────────
// Join with users table to get usernames of both parties.
// Only fetch what we need — no balance, no password_hash.
// ORDER BY created_at DESC: most recent first.
// LIMIT + OFFSET: fetch only the current page's rows.
$stmt = $pdo->prepare(
    'SELECT
        t.id,
        t.sender_id,
        t.receiver_id,
        t.amount,
        t.comment,
        t.created_at,
        s.username AS sender_name,
        r.username AS receiver_name
     FROM transactions t
     JOIN users s ON t.sender_id   = s.id
     JOIN users r ON t.receiver_id = r.id
     WHERE t.sender_id = ? OR t.receiver_id = ?
     ORDER BY t.created_at DESC
     LIMIT ? OFFSET ?'
);
// LIMIT and OFFSET are bound as integers to prevent injection
$stmt->execute([
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    PER_PAGE,
    $offset
]);
$transactions = $stmt->fetchAll();

// Fetch current user's balance for display
$stmt = $pdo->prepare('SELECT balance, username FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

$page_title = 'Transaction History';
require_once __DIR__ . '/includes/header.php';
?>

<!-- SUMMARY BAR -->
<div class="atm-panel mb-3">
    <div class="atm-panel-header">ACCOUNT STATEMENT</div>
    <div class="atm-panel-body">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div>
                <div class="atm-balance-label">CURRENT BALANCE</div>
                <div class="atm-balance" style="font-size:2rem;">
                    <span class="atm-currency">Rs.</span>
                    <?= htmlspecialchars(number_format((float)$me['balance'], 2), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="text-muted" style="font-size:0.78rem; text-align:right; align-self:flex-end;">
                TOTAL TRANSACTIONS: <?= $total ?><br>
                PAGE <?= $page ?> OF <?= $total_pages ?>
            </div>
        </div>
    </div>
</div>

<!-- TRANSACTIONS TABLE -->
<div class="atm-panel mb-3">
    <div class="atm-panel-header">TRANSACTION LOG</div>
    <div class="atm-panel-body" style="padding:0;">

        <?php if (empty($transactions)): ?>
            <p class="text-muted" style="padding:20px; font-size:0.85rem;">
                No transactions yet.
            </p>
        <?php else: ?>
            <table class="atm-table">
                <thead>
                    <tr>
                        <th>TX #</th>
                        <th>DATE / TIME</th>
                        <th>DESCRIPTION</th>
                        <th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx):
                        $is_sender  = ((int)$tx['sender_id'] === (int)$_SESSION['user_id']);
                        $other      = $is_sender ? $tx['receiver_name'] : $tx['sender_name'];
                        $direction  = $is_sender ? 'SENT TO' : 'RECEIVED FROM';
                        $cls        = $is_sender ? 'debit' : 'credit';
                        $prefix     = $is_sender ? '-' : '+';
                    ?>
                        <tr>
                            <td class="muted" style="font-size:0.78rem;">
                                #<?= htmlspecialchars((string)$tx['id'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="muted" style="font-size:0.8rem; white-space:nowrap;">
                                <?= htmlspecialchars(
                                    date('d M Y', strtotime($tx['created_at'])),
                                    ENT_QUOTES, 'UTF-8'
                                ) ?><br>
                                <span style="font-size:0.72rem;">
                                <?= htmlspecialchars(
                                    date('H:i:s', strtotime($tx['created_at'])),
                                    ENT_QUOTES, 'UTF-8'
                                ) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size:0.78rem; color:var(--text-dim);">
                                    <?= $direction ?>
                                </span>
                                <strong>
                                    <?= htmlspecialchars($other, ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <?php if ($tx['comment']): ?>
                                    <br>
                                    <span style="font-size:0.78rem; color:var(--text-dim);">
                                        ↳ <?= htmlspecialchars($tx['comment'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $cls ?>" style="white-space:nowrap; font-weight:bold;">
                                <?= $prefix ?>Rs.
                                <?= htmlspecialchars(number_format((float)$tx['amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<!-- PAGINATION -->
<?php if ($total_pages > 1): ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary"
               style="padding:6px 14px; font-size:0.82rem;">◀ PREV</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?page=<?= $p ?>"
               class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
               style="padding:6px 12px; font-size:0.82rem; min-width:36px; text-align:center;">
                <?= $p ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary"
               style="padding:6px 14px; font-size:0.82rem;">NEXT ▶</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="mt-2">
    <a href="/dashboard.php" class="btn btn-secondary">← BACK TO DASHBOARD</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>