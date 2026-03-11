<?php
/**
 * FILE: transfer.php
 * PURPOSE: Transfer money to another user.
 *
 * SECURITY MEASURES:
 *   1. auth_guard — logged in users only
 *   2. CSRF token verified on POST
 *   3. Sender ID taken from $_SESSION — NEVER from form input
 *   4. All validation delegated to transfer_handler.php
 *   5. All output escaped with htmlspecialchars()
 *   6. Pre-fills recipient if ?to= param given (from profile_view.php)
 *      but the value is validated as integer before use
 */

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/transfer_handler.php';

$error   = '';
$success = '';

// Fetch sender's current balance to display on form
$stmt = $pdo->prepare('SELECT username, balance FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$sender = $stmt->fetch();

// ── POST: PROCESS TRANSFER ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check first — before touching any input
    verify_csrf_token();

    // Call transfer handler.
    // CRITICAL: First argument is $_SESSION['user_id'] — hardcoded from
    // the session. The form has NO sender_id field. Even if an attacker
    // injects a sender_id into the POST body, it is completely ignored here.
    $result = process_transfer($pdo, (int)$_SESSION['user_id'], $_POST);

    if ($result['success']) {
        $success = 'Transfer successful! Rs. ' .
                   number_format((float)$_POST['amount'], 2) .
                   ' sent to ' .
                   htmlspecialchars($result['receiver_username'], ENT_QUOTES, 'UTF-8') .
                   '. Transaction ID: #' . $result['tx_id'];

        // Refresh sender balance after transfer
        $stmt = $pdo->prepare('SELECT username, balance FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $sender = $stmt->fetch();

    } else {
        $error = $result['error'];
    }
}

// Pre-fill recipient from ?to= query parameter (from profile_view.php "Send Money" button)
// Validate as positive integer — never echo raw query params
$prefill_to = filter_var($_GET['to'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

$page_title = 'Transfer Funds';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
    <div class="atm-alert atm-alert-error">
        ⚠ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="atm-alert atm-alert-success">
        ✓ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- BALANCE DISPLAY -->
<div class="atm-panel mb-3">
    <div class="atm-panel-header">AVAILABLE BALANCE</div>
    <div class="atm-panel-body">
        <div class="atm-balance">
            <span class="atm-currency">Rs.</span>
            <?= htmlspecialchars(number_format((float)$sender['balance'], 2), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="text-muted mt-1" style="font-size:0.75rem">
            Account: <?= htmlspecialchars($sender['username'], ENT_QUOTES, 'UTF-8') ?>
            &nbsp;|&nbsp; ID: <?= htmlspecialchars((string)$_SESSION['user_id'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
</div>

<!-- TRANSFER FORM -->
<div class="atm-panel mb-3">
    <div class="atm-panel-header">INITIATE TRANSFER</div>
    <div class="atm-panel-body">

        <form method="POST" action="/transfer.php" id="transfer-form" autocomplete="off">

            <?= csrf_input() ?>

            <!--
                NOTE: There is NO hidden sender_id field.
                The sender is always determined server-side from the session.
                Adding a sender_id field here would be a security hole —
                an attacker could change it to steal from another account.
            -->

            <div class="atm-form-group">
                <label for="to_user_id">RECIPIENT USER ID</label>
                <input
                    type="number"
                    id="to_user_id"
                    name="to_user_id"
                    min="1"
                    placeholder="Enter recipient's user ID"
                    value="<?= $prefill_to ? htmlspecialchars((string)$prefill_to, ENT_QUOTES, 'UTF-8') : '' ?>"
                    required
                >
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    Find a user ID via the <a href="/search.php">SEARCH</a> page.
                </div>
            </div>

            <div class="atm-form-group">
                <label for="amount">AMOUNT (Rs.)</label>
                <input
                    type="number"
                    id="amount"
                    name="amount"
                    min="0.01"
                    max="10000"
                    step="0.01"
                    placeholder="0.00"
                    required
                >
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    Minimum Rs. 0.01 &nbsp;|&nbsp; Maximum Rs. 10,000 per transaction
                </div>
            </div>

            <div class="atm-form-group">
                <label for="comment">
                    COMMENT (OPTIONAL)
                    <span style="float:right; color:var(--text-dim); font-size:0.75rem">
                        Max 500 chars
                    </span>
                </label>
                <textarea
                    id="comment"
                    name="comment"
                    maxlength="500"
                    rows="3"
                    placeholder="Add a note for the recipient..."
                ></textarea>
            </div>

            <div class="mt-2" style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">▶ CONFIRM TRANSFER</button>
                <a href="/dashboard.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

    </div>
</div>

<!-- WARNING BOX -->
<div class="atm-panel">
    <div class="atm-panel-body" style="padding:14px 18px;">
        <div class="text-muted" style="font-size:0.78rem; line-height:1.8;">
            ⚠ &nbsp; TRANSFERS ARE FINAL AND CANNOT BE REVERSED<br>
            ⚠ &nbsp; VERIFY RECIPIENT ID BEFORE CONFIRMING<br>
            ⚠ &nbsp; MAXIMUM Rs. 10,000 PER TRANSACTION
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>