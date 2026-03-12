<?php
/**
 * FILE: includes/transfer_handler.php
 * Accepts recipient by USERNAME (not numeric ID).
 * Internal integer IDs never leave the server.
 */

function process_transfer(PDO $pdo, int $sender_id, array $post): array {

    $to_username = trim($post['to_username'] ?? '');
    $amount_raw  = $post['amount'] ?? '';
    $comment     = trim($post['comment'] ?? '');

    // ── VALIDATE RECIPIENT USERNAME ───────────────────────────
    if (empty($to_username)) {
        return ['success' => false, 'error' => 'Recipient username is required.'];
    }

    // Resolve username to internal ID — never accept an ID from the form
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$to_username]);
    $receiver = $stmt->fetch();

    if (!$receiver) {
        return ['success' => false, 'error' => 'User "' . htmlspecialchars($to_username, ENT_QUOTES, 'UTF-8') . '" not found.'];
    }

    $receiver_id = (int)$receiver['id'];

    if ($receiver_id === $sender_id) {
        return ['success' => false, 'error' => 'You cannot transfer money to yourself.'];
    }

    // ── VALIDATE AMOUNT ───────────────────────────────────────
    $amount = filter_var($amount_raw, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0) {
        return ['success' => false, 'error' => 'Amount must be a positive number.'];
    }
    if ($amount < 0.01) {
        return ['success' => false, 'error' => 'Minimum transfer amount is Rs. 0.01.'];
    }
    if ($amount > 10000) {
        return ['success' => false, 'error' => 'Maximum transfer amount is Rs. 10,000.'];
    }
    $amount = round($amount, 2);

    // ── VALIDATE COMMENT ──────────────────────────────────────
    $comment = $comment === '' ? null : substr($comment, 0, 500);

    // ── ATOMIC TRANSFER ───────────────────────────────────────
    try {
        $pdo->beginTransaction();

        // Lock sender row — prevents race condition / double-spend
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$sender_id]);
        $sender = $stmt->fetch();

        if (!$sender) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Sender account not found.'];
        }

        if ((float)$sender['balance'] < $amount) {
            $pdo->rollBack();
            return ['success' => false,
                    'error' => 'Insufficient balance. You have Rs. ' .
                               number_format((float)$sender['balance'], 2) . ' available.'];
        }

        // Deduct from sender
        $stmt = $pdo->prepare(
            'UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?'
        );
        $stmt->execute([$amount, $sender_id, $amount]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Transfer failed. Please try again.'];
        }

        // Credit receiver
        $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([$amount, $receiver_id]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Transfer failed. Please try again.'];
        }

        // Record transaction
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (sender_id, receiver_id, amount, comment, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$sender_id, $receiver_id, $amount, $comment]);
        $tx_id = $pdo->lastInsertId();

        $pdo->commit();

        return [
            'success'           => true,
            'tx_id'             => $tx_id,
            'receiver_username' => $receiver['username'],
            'amount'            => $amount,
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[TRANSFER ERROR] ' . $e->getMessage());
        return ['success' => false, 'error' => 'A database error occurred. Please try again.'];
    }
}