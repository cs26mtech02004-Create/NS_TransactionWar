<?php

// config.php must always be first — it starts the session with all security settings
require 'security_file/config.php';
require 'security_file/auth.php';       // Redirects to login.php if not authenticated
require 'security_file/db.php';         // PDO via $pdo
require 'security_file/csrf_token.php'; // generateCSRFToken(), verifyCSRFToken(), rotateCSRFToken()



$transferError   = '';
$transferSuccess = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -----------------------
    // 1. Verify CSRF token — check return value, do NOT ignore it
    // -----------------------
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token. Please refresh the page and try again.");
    }

    $sender_id         = $_SESSION['user_id'];
    $receiver_username = trim($_POST['receiver'] ?? '');
    $amount            = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    // -----------------------
    // 2. Input validation
    // -----------------------
    if (empty($receiver_username) || $amount === false || $amount === null) {
        $transferError = "Invalid input. Please fill in all fields.";

    } elseif ($amount <= 0) {
        $transferError = "Amount must be a positive number.";

    } elseif ($amount > 1000000) {
        $transferError = "Amount exceeds the maximum transfer limit.";

    } else {

        try {

            // -----------------------
            // 3. Start transaction — using PDO (consistent with db.php)
            // -----------------------
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

            if ( $receiver['id'] === $sender_id) {
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

            // -----------------------
            // 4. Log the transaction (uncomment once the transactions table exists)
            // -----------------------
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

            // Show the business-logic errors to the user; log unexpected ones
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
    <title>Dashboard</title>
</head>
<body>

    <!-- XSS fix: always escape output with htmlspecialchars -->
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?> 🎉</h2>
    <a href="logout.php">Logout</a>

    <hr>

    <h3>Transfer Money</h3>

    <?php if (!empty($transferError)) : ?>
        <p style="color:red;"><?php echo htmlspecialchars($transferError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (!empty($transferSuccess)) : ?>
        <p style="color:green;"><?php echo $transferSuccess; /* already escaped above */ ?></p>
    <?php endif; ?>

    <!-- Fixed form: method="POST", action is a URL, CSRF token included -->
    <form method="POST" action="dashboard.php" autocomplete="off">

        <input type="hidden" name="csrf_token"
               value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <label for="receiver">Recipient Username:</label><br>
        <input type="text" id="receiver" name="receiver" maxlength="50" required><br><br>
        
        <label for="amount">Amount (Rs.):</label><br>
        <input type="number" id="amount" name="amount" min="0.01" max="1000000"
               step="0.01" required><br><br>

        <button type="submit">Send Money</button>

    </form>

</body>
</html>