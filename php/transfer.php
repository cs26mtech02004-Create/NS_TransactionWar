<?php
// TASK 3 — Money Transfer
session_start();
require_once __DIR__ . '/db.php';
auth_required();
logActivity('transfer.php', $_SESSION['username']);

$pageTitle = 'Send Money';
$pdo       = getDB();
$error     = '';
$prefillId = isset($_GET['to'])   ? (int)$_GET['to']   : '';
$prefillNm = isset($_GET['name']) ? $_GET['name']       : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $amount_raw  = trim($_POST['amount'] ?? '');
    $comment     = trim($_POST['comment'] ?? '');
    $sender_id   = (int)$_SESSION['user_id'];

    if (!is_numeric($amount_raw) || (float)$amount_raw <= 0)
        $error = 'Amount must be a positive number.';
    elseif ((float)$amount_raw > 99999.99)
        $error = 'Max transfer is ₹99,999.99.';
    elseif ($receiver_id <= 0)
        $error = 'Enter a valid recipient user ID.';
    elseif ($receiver_id === $sender_id)
        $error = 'You cannot send money to yourself.';
    else {
        $amount = round((float)$amount_raw, 2);
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $s->execute([$sender_id]);
            $sender = $s->fetch();
            if (!$sender) throw new RuntimeException('Sender not found.');
            if ($sender['balance'] < $amount)
                throw new RuntimeException('Insufficient balance. You have ₹'.number_format($sender['balance'],2).'.');
            $r = $pdo->prepare("SELECT id, username FROM users WHERE id = ? FOR UPDATE");
            $r->execute([$receiver_id]);
            $receiver = $r->fetch();
            if (!$receiver) throw new RuntimeException('Recipient not found.');

            $pdo->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$amount,$sender_id]);
            $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$amount,$receiver_id]);
            $pdo->prepare("INSERT INTO transactions(sender_id,receiver_id,amount,comment) VALUES(?,?,?,?)")
                ->execute([$sender_id,$receiver_id,$amount,$comment ?: null]);
            $pdo->commit();

            $_SESSION['flash_success'] = '✅ ₹'.number_format($amount,2).' sent to '.$receiver['username'].'!';
            header('Location: /history.php'); exit();
        } catch (RuntimeException $e) {
            $pdo->rollBack(); $error = $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack(); error_log($e->getMessage()); $error = 'Transfer failed. Try again.';
        }
    }
}

$recv = null;
if ($prefillId > 0) {
    $s = $pdo->prepare("SELECT id,username FROM users WHERE id=?");
    $s->execute([$prefillId]); $recv = $s->fetch();
}
$bal = $pdo->prepare("SELECT balance FROM users WHERE id=?");
$bal->execute([$_SESSION['user_id']]); $balance = $bal->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>💸 Send Money</h1>
  <p class="subtitle">Transfer funds securely to another user</p>
</div>

<div class="transfer-layout">
  <div class="card transfer-card">
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <div class="balance-badge">Your balance: <strong>₹<?= number_format($balance,2) ?></strong></div>

    <form method="POST" action="/transfer.php" id="transfer-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label>Recipient User ID</label>
        <div class="receiver-row">
          <input type="number" id="receiver_id" name="receiver_id" min="1"
                 placeholder="Enter user ID"
                 value="<?= $prefillId ?: (int)($_POST['receiver_id'] ?? '') ?>"
                 required class="input-field">
          <button type="button" class="btn btn-outline" onclick="lookupUser()">Lookup</button>
        </div>
        <div id="receiver-preview" class="receiver-preview <?= $recv ? 'visible' : '' ?>">
          <span>👤</span>
          <span id="preview-name"><?= $recv ? h($recv['username']) : '' ?></span>
          <span id="preview-id" class="preview-id"><?= $recv ? '#'.(int)$recv['id'] : '' ?></span>
        </div>
        <div id="lookup-error" class="lookup-error"></div>
      </div>
      <div class="form-group">
        <label>Amount (₹)</label>
        <input type="number" name="amount" min="0.01" max="99999.99" step="0.01"
               placeholder="0.00" value="<?= h($_POST['amount'] ?? '') ?>"
               required class="input-field input-amount">
      </div>
      <div class="form-group">
        <label>Comment <span class="optional">(optional — visible to receiver)</span></label>
        <textarea name="comment" maxlength="500" placeholder="Add a note…"
                  class="input-field input-textarea"><?= h($_POST['comment'] ?? '') ?></textarea>
        <small class="hint"><span id="char-left">500</span> characters remaining</small>
      </div>
      <button type="submit" class="btn btn-primary btn-full">💸 Send Money</button>
    </form>
  </div>

  <div class="transfer-sidebar">
    <div class="card tip-card">
      <h3>💡 Tips</h3>
      <ul>
        <li>Use <a href="/search.php">Search</a> to find user IDs</li>
        <li>Transfers <strong>cannot be reversed</strong></li>
        <li>Comment is only visible to the receiver</li>
      </ul>
    </div>
    <a href="/search.php"  class="btn btn-outline btn-full mt-8">🔍 Search Users</a>
    <a href="/history.php" class="btn btn-outline btn-full mt-8">📜 View History</a>
  </div>
</div>

<script>
document.querySelector('textarea[name=comment]').addEventListener('input',function(){
  document.getElementById('char-left').textContent = 500 - this.value.length;
});
async function lookupUser() {
  const rid = document.getElementById('receiver_id').value.trim();
  const prev = document.getElementById('receiver-preview');
  const err  = document.getElementById('lookup-error');
  err.textContent = '';
  if (!rid || isNaN(rid) || parseInt(rid)<=0) { err.textContent='Enter a valid user ID.'; return; }
  try {
    const data = await fetch('/lookup_user.php?id='+encodeURIComponent(rid)).then(r=>r.json());
    if (data.found) {
      document.getElementById('preview-name').textContent = data.username;
      document.getElementById('preview-id').textContent   = '#'+data.id;
      prev.classList.add('visible');
    } else { prev.classList.remove('visible'); err.textContent='User not found.'; }
  } catch(e) { err.textContent='Lookup failed.'; }
}
document.getElementById('transfer-form').addEventListener('submit',function(e){
  const amt  = parseFloat(document.querySelector('input[name=amount]').value);
  const name = document.getElementById('preview-name').textContent || 'this user';
  if(isNaN(amt)||amt<=0){e.preventDefault();return;}
  if(!confirm('Send ₹'+amt.toFixed(2)+' to '+name+'?\nThis cannot be reversed.')) e.preventDefault();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
