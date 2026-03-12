<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/transfer_handler.php';

$error   = '';
$success = '';

$stmt = $pdo->prepare('SELECT username, balance FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$sender = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_token();

    $result = process_transfer($pdo, (int)$_SESSION['user_id'], $_POST);

    if ($result['success']) {
        $success = 'Sent Rs. ' . number_format($result['amount'], 2) .
                   ' to ' . htmlspecialchars($result['receiver_username'], ENT_QUOTES, 'UTF-8') .
                   '. Transaction #' . $result['tx_id'];

        $stmt = $pdo->prepare('SELECT username, balance FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $sender = $stmt->fetch();
    } else {
        $error = $result['error'];
    }
}

// Prefill from search page ?to=username
$prefill_username = htmlspecialchars(trim($_GET['to'] ?? ''), ENT_QUOTES, 'UTF-8');

$page_title = 'Transfer Funds';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
    <div class="atm-alert atm-alert-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="atm-alert atm-alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">AVAILABLE BALANCE</div>
    <div class="atm-panel-body">
        <div class="atm-balance">
            <span class="atm-currency">Rs.</span><?= number_format((float)$sender['balance'], 2) ?>
        </div>
        <div class="text-muted mt-1" style="font-size:0.75rem">
            Account: <?= htmlspecialchars($sender['username'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
</div>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">SEND MONEY</div>
    <div class="atm-panel-body">

        <form method="POST" action="/transfer.php" id="transfer-form" autocomplete="off">
            <?= csrf_input() ?>

            <div class="atm-form-group">
                <label for="to_username">RECIPIENT USERNAME</label>
                <input type="text" id="to_username" name="to_username"
                       maxlength="30" placeholder="Enter recipient's username"
                       value="<?= $prefill_username ?>"
                       required autocomplete="off">
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    Find users via <a href="/search.php">Search</a>
                </div>
            </div>

            <div class="atm-form-group">
                <label for="amount">AMOUNT (Rs.)</label>
                <input type="number" id="amount" name="amount"
                       min="0.01" max="10000" step="0.01"
                       placeholder="0.00" required>
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    Min Rs. 0.01 &nbsp;|&nbsp; Max Rs. 10,000
                </div>
            </div>

            <div class="atm-form-group">
                <label for="comment">
                    NOTE (OPTIONAL)
                    <span style="float:right; color:var(--text-dim); font-size:0.75rem" id="comment-count">500</span>
                </label>
                <textarea id="comment" name="comment" maxlength="500" rows="3"
                          placeholder="Add a note for the recipient..."></textarea>
            </div>

            <div class="mt-2" style="display:flex; gap:10px;">
                <button type="button" id="transfer-trigger" class="btn btn-primary">
                    REVIEW TRANSFER
                </button>
                <a href="/dashboard.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>
    </div>
</div>

<div class="atm-panel">
    <div class="atm-panel-body" style="padding:12px 18px;">
        <div class="text-muted" style="font-size:0.78rem; line-height:2;">
            Transfers are final and cannot be reversed.<br>
            Verify the recipient username before confirming.
        </div>
    </div>
</div>

<!-- Custom confirm modal — replaces browser window.confirm() -->
<div id="transfer-modal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">CONFIRM TRANSFER</div>
        <div class="modal-body">
            <div class="modal-row">
                <span class="modal-label">RECIPIENT</span>
                <span class="modal-value" id="modal-recipient"></span>
            </div>
            <div class="modal-row">
                <span class="modal-label">AMOUNT</span>
                <span class="modal-value text-green" id="modal-amount"></span>
            </div>
            <hr style="border-color:var(--border); margin:14px 0;">
            <p style="font-size:0.78rem; color:var(--text-dim);">
                This transaction cannot be reversed.
            </p>
        </div>
        <div class="modal-footer">
            <button id="modal-confirm" class="btn btn-primary">CONFIRM</button>
            <button id="modal-cancel"  class="btn btn-secondary">CANCEL</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>