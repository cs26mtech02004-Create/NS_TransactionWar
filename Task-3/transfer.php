<?php
// transfer.php — Money Transfer (Task 3b)
// GET  → Show the transfer form (optionally pre-filled from search.php)
// POST → Process the transfer

session_start();
require_once __DIR__ . '/db.php';

auth_required();
logActivity('transfer.php', $_SESSION['username']);

$pageTitle  = 'Send Money';
$pdo        = getDB();
$error      = '';
$prefillId  = isset($_GET['to'])   ? (int)$_GET['to']          : '';
$prefillName= isset($_GET['name']) ? h($_GET['name'])           : '';

// ============================================================
// POST — Process the transfer
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    csrf_verify();

    // 2. Collect & sanitize inputs
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $amount_raw  = trim($_POST['amount'] ?? '');
    $comment     = trim($_POST['comment'] ?? '');
    $sender_id   = (int)$_SESSION['user_id'];

    // 3. Validate amount
    if (!is_numeric($amount_raw) || (float)$amount_raw <= 0) {
        $error = 'Amount must be a positive number.';
    } elseif ((float)$amount_raw > 99999.99) {
        $error = 'Amount too large.';
    } elseif ($receiver_id <= 0) {
        $error = 'Please select a valid recipient.';
    } elseif ($receiver_id === $sender_id) {
        $error = 'You cannot send money to yourself.';
    } else {
        $amount = round((float)$amount_raw, 2);

        try {
            $pdo->beginTransaction();

            // 4. Lock & read sender balance (FOR UPDATE prevents race conditions)
            $stmt = $pdo->prepare(
                "SELECT id, username, balance FROM users WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$sender_id]);
            $sender = $stmt->fetch();

            if (!$sender) {
                throw new RuntimeException('Sender account not found.');
            }
            if ($sender['balance'] < $amount) {
                throw new RuntimeException(
                    'Insufficient balance. You have ₹' . number_format($sender['balance'], 2) . '.'
                );
            }

            // 5. Check receiver exists (also locked)
            $stmt = $pdo->prepare(
                "SELECT id, username FROM users WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$receiver_id]);
            $receiver = $stmt->fetch();

            if (!$receiver) {
                throw new RuntimeException('Recipient not found. Please check the user ID.');
            }

            // 6. Deduct from sender
            $pdo->prepare(
                "UPDATE users SET balance = balance - ? WHERE id = ?"
            )->execute([$amount, $sender_id]);

            // 7. Credit receiver
            $pdo->prepare(
                "UPDATE users SET balance = balance + ? WHERE id = ?"
            )->execute([$amount, $receiver_id]);

            // 8. Record transaction
            $pdo->prepare(
                "INSERT INTO transactions (sender_id, receiver_id, amount, comment)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $sender_id,
                $receiver_id,
                $amount,
                $comment !== '' ? $comment : null
            ]);

            $pdo->commit();

            // 9. Flash message and redirect (PRG pattern — prevents double submit on refresh)
            $_SESSION['flash_success'] =
                '✅ ₹' . number_format($amount, 2) .
                ' sent to ' . $receiver['username'] . ' successfully!';
            header('Location: history.php');
            exit();

        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Transfer PDO error: ' . $e->getMessage());
            $error = 'Transfer failed due to a database error. Please try again.';
        }
    }
}

// ============================================================
// GET — Fetch receiver info if pre-filled from search
// ============================================================
$receiver_preview = null;
if ($prefillId > 0) {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$prefillId]);
    $receiver_preview = $stmt->fetch();
}

// Fetch current sender balance to show on form
$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$myBalance = $stmt->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>💸 Send Money</h1>
  <p class="subtitle">Transfer funds to another user securely</p>
</div>

<div class="transfer-layout">

  <!-- Left: Form -->
  <div class="card transfer-card">

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="balance-badge">
      Your balance: <strong>₹<?= number_format($myBalance, 2) ?></strong>
    </div>

    <form method="POST" action="transfer.php" id="transfer-form" novalidate>
      <!-- CSRF Token -->
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- Recipient -->
      <div class="form-group">
        <label for="receiver_id">Recipient User ID</label>
        <div class="receiver-row">
          <input
            type="number"
            id="receiver_id"
            name="receiver_id"
            min="1"
            placeholder="Enter user ID"
            value="<?= $prefillId ?: (int)($_POST['receiver_id'] ?? '') ?>"
            required
            class="input-field"
          >
          <button type="button" class="btn btn-outline" onclick="lookupUser()">Lookup</button>
        </div>
        <!-- Preview after lookup or pre-fill -->
        <div id="receiver-preview" class="receiver-preview <?= $receiver_preview ? 'visible' : '' ?>">
          <?php if ($receiver_preview): ?>
            <span class="preview-icon">👤</span>
            <span id="preview-name"><?= h($receiver_preview['username']) ?></span>
            <span class="preview-id">#<?= (int)$receiver_preview['id'] ?></span>
          <?php else: ?>
            <span class="preview-icon">👤</span>
            <span id="preview-name"></span>
            <span class="preview-id" id="preview-id-span"></span>
          <?php endif; ?>
        </div>
        <div id="lookup-error" class="lookup-error"></div>
      </div>

      <!-- Amount -->
      <div class="form-group">
        <label for="amount">Amount (₹)</label>
        <input
          type="number"
          id="amount"
          name="amount"
          min="0.01"
          max="99999.99"
          step="0.01"
          placeholder="0.00"
          value="<?= h($_POST['amount'] ?? '') ?>"
          required
          class="input-field input-amount"
        >
        <small class="hint">Max single transfer: ₹99,999.99</small>
      </div>

      <!-- Comment (optional) -->
      <div class="form-group">
        <label for="comment">Comment <span class="optional">(optional — visible to receiver)</span></label>
        <textarea
          id="comment"
          name="comment"
          maxlength="500"
          placeholder="Add a note for the recipient…"
          class="input-field input-textarea"
        ><?= h($_POST['comment'] ?? '') ?></textarea>
        <small class="hint char-count"><span id="char-left">500</span> characters remaining</small>
      </div>

      <button type="submit" class="btn btn-primary btn-full" id="submit-btn">
        💸 Send Money
      </button>
    </form>
  </div>

  <!-- Right: Quick tips -->
  <div class="transfer-sidebar">
    <div class="card tip-card">
      <h3>💡 Tips</h3>
      <ul>
        <li>Use <a href="search.php">Search</a> to find user IDs by name</li>
        <li>Double-check the recipient before sending</li>
        <li>Transfers are instant and <strong>cannot be reversed</strong></li>
        <li>Your comment is visible only to the receiver</li>
      </ul>
    </div>
    <a href="search.php" class="btn btn-outline btn-full" style="margin-top:12px">
      🔍 Search for a User
    </a>
    <a href="history.php" class="btn btn-outline btn-full" style="margin-top:8px">
      📜 View Transaction History
    </a>
  </div>

</div>

<script>
// Live character counter for comment
const commentBox = document.getElementById('comment');
const charLeft   = document.getElementById('char-left');
commentBox.addEventListener('input', () => {
  charLeft.textContent = 500 - commentBox.value.length;
});

// AJAX lookup to verify receiver before submitting
async function lookupUser() {
  const rid  = document.getElementById('receiver_id').value.trim();
  const prev = document.getElementById('receiver-preview');
  const err  = document.getElementById('lookup-error');
  err.textContent = '';

  if (!rid || isNaN(rid) || parseInt(rid) <= 0) {
    err.textContent = 'Enter a valid numeric user ID first.';
    prev.classList.remove('visible');
    return;
  }

  try {
    const res  = await fetch(`lookup_user.php?id=${encodeURIComponent(rid)}`);
    const data = await res.json();
    if (data.found) {
      document.getElementById('preview-name').textContent = data.username;
      document.getElementById('preview-id-span').textContent = '#' + data.id;
      prev.classList.add('visible');
    } else {
      prev.classList.remove('visible');
      err.textContent = 'User not found.';
    }
  } catch (e) {
    err.textContent = 'Lookup failed. Please try again.';
  }
}

// Confirm before submit
document.getElementById('transfer-form').addEventListener('submit', function(e) {
  const amount = parseFloat(document.getElementById('amount').value);
  const name   = document.getElementById('preview-name').textContent || 'this user';
  if (!confirm(`Send ₹${amount.toFixed(2)} to ${name}? This cannot be reversed.`)) {
    e.preventDefault();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
