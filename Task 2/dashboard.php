<?php

// config.php must always be first — it starts the session with all security settings
require 'security_file/config.php';
require 'security_file/auth.php';       // Redirects to login.php if not authenticated
require 'security_file/db.php';         // PDO via $pdo
require 'security_file/csrf_token.php'; // generateCSRFToken(), verifyCSRFToken(), rotateCSRFToken()

generateCSRFToken();

// ── NEW: Fetch profile from users table (Part 2) ──────────────────────────────
$profileStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$profileStmt->execute(['id' => $_SESSION['user_id']]);
$profile = $profileStmt->fetch();

$transferError   = '';
$transferSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF token — check return value, do NOT ignore it
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token. Please refresh the page and try again.");
    }

    $sender_id         = $_SESSION['user_id'];
    $receiver_username = trim($_POST['receiver'] ?? '');
    $amount            = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    // 2. Input validation
    if (empty($receiver_username) || $amount === false || $amount === null) {
        $transferError = "Invalid input. Please fill in all fields.";

    } elseif ($amount <= 0) {
        $transferError = "Amount must be a positive number.";

    } elseif ($amount > 1000000) {
        $transferError = "Amount exceeds the maximum transfer limit.";

    } else {

        try {

            // 3. Start transaction — using PDO (consistent with db.php)
            $pdo->beginTransaction();

            // Lock sender row to prevent race conditions
            $stmt = $pdo->prepare(
                "SELECT id, username, balance FROM MAJOR WHERE id = :id FOR UPDATE"
            );
            $stmt->execute(['id' => $sender_id]);
            $sender = $stmt->fetch();

            if (!$sender) {
                throw new Exception("Sender account not found.");
            }

            if ((float) $sender['balance'] < $amount) {
                throw new Exception("Insufficient balance.");
            }

            // Lock receiver row
            $stmt = $pdo->prepare(
                "SELECT id, balance FROM MAJOR WHERE username = :username FOR UPDATE"
            );
            $stmt->execute(['username' => $receiver_username]);
            $receiver = $stmt->fetch();

            if (!$receiver) {
                throw new Exception("Recipient not found.");
            }

            if ($receiver['id'] === $sender_id) {
                throw new Exception("You cannot transfer money to yourself.");
            }

            // Deduct from sender
            $pdo->prepare(
                "UPDATE MAJOR SET balance = balance - :amount WHERE id = :id"
            )->execute(['amount' => $amount, 'id' => $sender_id]);

            // Credit receiver
            $pdo->prepare(
                "UPDATE MAJOR SET balance = balance + :amount WHERE id = :id"
            )->execute(['amount' => $amount, 'id' => $receiver['id']]);

            // 4. Log the transaction (uncomment once the transactions table exists)
            // $pdo->prepare(
            //     "INSERT INTO transactions (sender_id, receiver_id, amount, created_at)
            //      VALUES (:sender_id, :receiver_id, :amount, NOW())"
            // )->execute([
            //     'sender_id'   => $sender_id,
            //     'receiver_id' => $receiver['id'],
            //     'amount'      => $amount
            // ]);

            $pdo->commit();

            // Rotate CSRF token after successful POST
            //rotateCSRFToken();

            $transferSuccess = "Successfully transferred Rs. " . number_format($amount, 2)
                             . " to " . htmlspecialchars($receiver_username, ENT_QUOTES, 'UTF-8') . ".";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $safeMessages = [
                "Insufficient balance.",
                "Recipient not found.",
                "You cannot transfer money to yourself.",
                "Sender account not found.",
            ];

            if (in_array($e->getMessage(), $safeMessages, true)) {
                $transferError = $e->getMessage();
            } else {
                error_log("Transfer error: " . $e->getMessage());
                $transferError = "Transfer failed due to a system error. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/app.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="app-nav">
    <a href="dashboard.php" class="nav-logo">
        <div class="logo-mark"></div>
        <span class="logo-name">Banking System</span>
    </a>
    <div class="nav-user">
        <?php if (!empty($profile['profile_image'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($profile['profile_image']); ?>" class="nav-avatar" alt="">
        <?php else: ?>
            <div class="nav-avatar-placeholder"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
        <?php endif; ?>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="nav-active">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="edit_profile.php">Profile</a></li>
            <li><a href="logout.php" class="btn-nav-logout">Logout</a></li>
            
        </ul>
    </div>
</nav>

<div class="page-wrap">

    <!-- Session / CSRF info bar -->
    <div class="session-info">
        <strong>Session</strong> &nbsp;
        <code><?php echo substr(session_id(), 0, 16); ?>…</code>
        <span class="token-badge">HttpOnly · SameSite=Strict</span>
        &nbsp;&nbsp;
        <strong>CSRF</strong> &nbsp;
        <code><?php echo substr($_SESSION['csrf_token'], 0, 16); ?>…</code>
        <span class="token-badge">active</span>
    </div>

    <!-- Page header -->
    <div class="page-eyebrow">Overview</div>
    <h1 class="page-title">Welcome back, <em><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></em></h1>

    <!-- Profile card (Part 2) -->
    <?php if ($profile): ?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header-label">Profile</div>
        <div class="profile-hero">
            <div class="profile-avatar-wrap">
                <?php if (!empty($profile['profile_image'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($profile['profile_image']); ?>" class="profile-avatar" alt="">
                <?php else: ?>
                    <div class="profile-avatar-placeholder"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="profile-username"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($profile['email'] ?? ''); ?></div>
                <?php if (!empty($profile['bio'])): ?>
                    <div class="bio-box"><?php echo htmlspecialchars($profile['bio']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="divider"></div>
        <div class="action-row">
            <a href="edit_profile.php" class="btn"><span>Edit Profile</span></a>
            <a href="users.php" class="btn btn-ghost"><span>View All Users</span></a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transfer Money card (your original logic, restyled) -->
    <div class="card">
        <div class="card-header-label">Transfer Money</div>

        <?php if (!empty($transferError)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($transferError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($transferSuccess)): ?>
            <div class="alert alert-success"><?php echo $transferSuccess; ?></div>
        <?php endif; ?>

        <form method="POST" action="dashboard.php" autocomplete="off">

            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:24px;">

                <div class="form-field">
                    <label>Recipient Username</label>
                    <div class="input-wrap">
                        <input type="text" name="receiver" placeholder="their_username" maxlength="50" required>
                        <div class="input-line"></div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Amount (Rs.)</label>
                    <div class="input-wrap">
                        <input type="number" name="amount" placeholder="0.00"
                               min="0.01" max="1000000" step="0.01" required>
                        <div class="input-line"></div>
                    </div>
                </div>

            </div>

            <div class="divider"></div>
            <button type="submit" class="btn"><span>Send Money →</span></button>

        
    </div>

</div>
</body>
</html>
