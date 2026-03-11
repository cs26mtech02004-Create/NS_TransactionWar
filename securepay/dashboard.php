<?php
/**
 * FILE: dashboard.php
 * PURPOSE: Home page after login. Shows balance, recent transactions, quick links.
 *
 * This is the first page the user sees after authenticating.
 * It is a PROTECTED PAGE — auth_guard.php redirects guests to login.
 */

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

// Fetch current user's data fresh from DB (don't rely on stale session data)
$stmt = $pdo->prepare(
    'SELECT id, username, email, balance, created_at FROM users WHERE id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If the user no longer exists in DB (deleted account), force logout
if (!$user) {
    destroy_session();
    header('Location: /login.php');
    exit();
}

// Fetch 5 most recent transactions (sent or received)
$stmt = $pdo->prepare(
    'SELECT t.*, 
            s.username AS sender_name, 
            r.username AS receiver_name
     FROM transactions t
     JOIN users s ON t.sender_id   = s.id
     JOIN users r ON t.receiver_id = r.id
     WHERE t.sender_id = ? OR t.receiver_id = ?
     ORDER BY t.created_at DESC
     LIMIT 5'
);
$stmt->execute([$user['id'], $user['id']]);
$recent_transactions = $stmt->fetchAll();

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Generate CSRF token for the logout link in nav (needed by logout.php)
$csrf_token = generate_csrf_token();
?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">ACCOUNT SUMMARY</div>
    <div class="atm-panel-body">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <div class="atm-balance-label">AVAILABLE BALANCE</div>
                <div class="atm-balance">
                    <span class="atm-currency">Rs.</span>
                    <!--
                        number_format() formats with 2 decimal places and comma thousands separator.
                        Output is just a number — no user input here, so XSS not a concern,
                        but htmlspecialchars is always a good habit.
                    -->
                    <?= htmlspecialchars(number_format((float)$user['balance'], 2), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    Account holder: <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
                    &nbsp;|&nbsp;
                    ID: <?= htmlspecialchars((string)$user['id'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="/transfer.php" class="btn btn-primary">▶ TRANSFER FUNDS</a>
                <a href="/transaction_history.php" class="btn btn-secondary">HISTORY</a>
            </div>
        </div>
    </div>
</div>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">RECENT TRANSACTIONS</div>
    <div class="atm-panel-body" style="padding:0">

        <?php if (empty($recent_transactions)): ?>
            <p class="text-muted" style="padding:20px; font-size:0.85rem">
                No transactions yet. Transfer funds to get started.
            </p>
        <?php else: ?>
            <table class="atm-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>DESCRIPTION</th>
                        <th>AMOUNT</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $tx): ?>
                        <?php
                        $is_sender = ($tx['sender_id'] == $user['id']);
                        $other_user = $is_sender
                            ? htmlspecialchars($tx['receiver_name'], ENT_QUOTES, 'UTF-8')
                            : htmlspecialchars($tx['sender_name'],   ENT_QUOTES, 'UTF-8');
                        $direction  = $is_sender ? 'SENT TO' : 'RECEIVED FROM';
                        $amount_cls = $is_sender ? 'debit' : 'credit';
                        $amount_pfx = $is_sender ? '-' : '+';
                        ?>
                        <tr>
                            <td class="muted">
                                <?= htmlspecialchars(
                                    date('d-M H:i', strtotime($tx['created_at'])),
                                    ENT_QUOTES, 'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= $direction ?> <strong><?= $other_user ?></strong>
                                <?php if (!empty($tx['comment'])): ?>
                                    <br>
                                    <span class="muted" style="font-size:0.78rem">
                                        ↳ <?= htmlspecialchars($tx['comment'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $amount_cls ?>">
                                <?= $amount_pfx ?>Rs.
                                <?= htmlspecialchars(number_format((float)$tx['amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="text-green" style="font-size:0.78rem">COMPLETED</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<div style="display:flex; gap:16px; flex-wrap:wrap;">
    <a href="/search.php"              class="btn btn-secondary">SEARCH USERS</a>
    <a href="/profile.php"             class="btn btn-secondary">EDIT PROFILE</a>
    <a href="/transaction_history.php" class="btn btn-secondary">FULL HISTORY</a>
    <!-- CSRF token appended to logout link — verified in logout.php -->
    <a href="/logout.php?csrf_token=<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-danger"
       onclick="return confirm('LOG OUT?\n\nYour session will be terminated.')">
        LOGOUT
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>