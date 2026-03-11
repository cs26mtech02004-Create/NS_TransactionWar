<?php
/**
 * FILE: includes/transfer_handler.php
 * PURPOSE: Core money transfer logic.
 *
 * THIS FILE IS THE MOST SECURITY-CRITICAL FILE IN THE APPLICATION.
 * Money moves here. Every possible attack on this function must be blocked.
 *
 * SECURITY MEASURES:
 *   1. Sender ID ALWAYS from $_SESSION — never from any user input
 *   2. Amount validated: positive decimal, within range, not from hidden field
 *   3. Receiver existence verified before any money moves
 *   4. Balance checked inside SELECT FOR UPDATE (locks the row)
 *   5. Entire operation wrapped in a DB transaction (atomic — all or nothing)
 *   6. Comment sanitised (length-limited, XSS escaped on output)
 *   7. Self-transfer blocked
 *
 * WHAT IS AN ATOMIC TRANSACTION?
 *   Two things must happen: deduct from sender, add to receiver.
 *   If step 1 succeeds but the server crashes before step 2, money vanishes.
 *   A DB transaction wraps both steps — if ANYTHING fails, BOTH are undone.
 *   Either both happen or neither happens. This is called atomicity.
 *
 * WHAT IS SELECT FOR UPDATE?
 *   Imagine two transfer requests arrive at the same millisecond for the
 *   same user. Both read the balance as Rs. 100. Both think Rs. 80 is fine.
 *   Both deduct Rs. 80. Balance ends up at Rs. -60. This is a race condition.
 *
 *   SELECT ... FOR UPDATE locks the row the moment it is read. The second
 *   concurrent request is forced to WAIT until the first transaction commits
 *   or rolls back. By then the balance is already updated and the second
 *   request reads the correct (lower) value.
 */

/**
 * process_transfer(PDO $pdo, int $sender_id, array $input): array
 *
 * @param PDO   $pdo       Active database connection
 * @param int   $sender_id MUST come from $_SESSION['user_id'] — never from input
 * @param array $input     Validated POST data: ['to_user_id', 'amount', 'comment']
 * @return array ['success' => bool, 'error' => string|null, 'tx_id' => int|null]
 */
function process_transfer(PDO $pdo, int $sender_id, array $input): array {

    // ── STEP 1: Validate receiver ID ──────────────────────────
    $to_user_id = filter_var($input['to_user_id'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$to_user_id) {
        return ['success' => false, 'error' => 'Invalid recipient ID.', 'tx_id' => null];
    }

    // Block self-transfer
    if ((int)$to_user_id === $sender_id) {
        return ['success' => false, 'error' => 'You cannot transfer money to yourself.', 'tx_id' => null];
    }

    // ── STEP 2: Validate amount ───────────────────────────────
    // filter_var with FILTER_VALIDATE_FLOAT ensures it is a real number.
    // We then apply range checks:
    //   - Must be > 0 (no zero or negative transfers)
    //   - Must be <= 10000 per transaction (business rule)
    //   - Must have at most 2 decimal places (no Rs. 1.999 tricks)
    $amount_raw = $input['amount'] ?? '';
    $amount     = filter_var($amount_raw, FILTER_VALIDATE_FLOAT);

    if ($amount === false || $amount <= 0) {
        return ['success' => false, 'error' => 'Amount must be a positive number.', 'tx_id' => null];
    }
    if ($amount > 10000) {
        return ['success' => false, 'error' => 'Maximum transfer amount is Rs. 10,000.', 'tx_id' => null];
    }
    // Round to 2 decimal places to prevent floating point shenanigans
    $amount = round($amount, 2);
    if ($amount <= 0) {
        return ['success' => false, 'error' => 'Amount must be greater than zero.', 'tx_id' => null];
    }

    // ── STEP 3: Sanitise comment ──────────────────────────────
    // Comment is optional. Strip leading/trailing whitespace and enforce
    // max length. We store RAW text — not HTML-escaped. Escaping happens
    // at OUTPUT time (in the template). Storing escaped HTML in the DB
    // is wrong — it leads to double-escaping and broken display.
    $comment = trim($input['comment'] ?? '');
    if (strlen($comment) > 500) {
        return ['success' => false, 'error' => 'Comment must be 500 characters or fewer.', 'tx_id' => null];
    }
    $comment = $comment ?: null; // Store NULL if empty

    // ── STEP 4: Verify receiver exists ───────────────────────
    // Do this BEFORE starting the transaction to avoid unnecessary locking.
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$to_user_id]);
    $receiver = $stmt->fetch();

    if (!$receiver) {
        return ['success' => false, 'error' => 'Recipient user not found.', 'tx_id' => null];
    }

    // ── STEP 5: BEGIN ATOMIC TRANSACTION ─────────────────────
    // From this point, ALL database changes are provisional.
    // They only become permanent when we call $pdo->commit().
    // If anything goes wrong, $pdo->rollBack() undoes everything.
    try {
        $pdo->beginTransaction();

        // ── STEP 6: Lock sender row and check balance ─────────
        // SELECT ... FOR UPDATE:
        //   - Reads the current balance
        //   - Locks the sender's row so no other transaction can
        //     read OR write it until we commit or rollback
        //   - Prevents the race condition described at the top
        //
        // This lock is held for the duration of our transaction —
        // typically a few milliseconds. Other users are NOT affected;
        // only concurrent requests for THIS sender are serialised.
        $stmt = $pdo->prepare(
            'SELECT balance FROM users WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$sender_id]);
        $sender = $stmt->fetch();

        if (!$sender) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Sender account not found.', 'tx_id' => null];
        }

        $current_balance = (float) $sender['balance'];

        // ── STEP 7: Check sufficient balance ─────────────────
        // This check happens AFTER the lock. Even if two requests
        // arrive simultaneously, the second one waits for the first
        // to commit, then reads the UPDATED (lower) balance.
        if ($current_balance < $amount) {
            $pdo->rollBack();
            return [
                'success' => false,
                'error'   => 'Insufficient balance. You have Rs. ' .
                             number_format($current_balance, 2) . ' available.',
                'tx_id'   => null
            ];
        }

        // ── STEP 8: Deduct from sender ────────────────────────
        // Using arithmetic in SQL (balance - ?) rather than:
        //   SET balance = [PHP calculated value]
        // WHY? If two transactions run concurrently, SQL-level arithmetic
        // is applied to the CURRENT DB value at the moment of execution,
        // not a value that was read a moment ago in PHP (which could be stale).
        // Combined with FOR UPDATE, this is bulletproof against race conditions.
        $stmt = $pdo->prepare(
            'UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?'
        );
        $stmt->execute([$amount, $sender_id, $amount]);

        // Verify the deduction actually happened (the AND balance >= ? check
        // in the UPDATE provides a last-resort guard at DB level)
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Transfer failed. Please try again.', 'tx_id' => null];
        }

        // ── STEP 9: Add to receiver ───────────────────────────
        $stmt = $pdo->prepare(
            'UPDATE users SET balance = balance + ? WHERE id = ?'
        );
        $stmt->execute([$amount, $to_user_id]);

        if ($stmt->rowCount() !== 1) {
            // Receiver update failed — roll back everything
            // Sender's deduction is also undone
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Transfer failed. Please try again.', 'tx_id' => null];
        }

        // ── STEP 10: Record the transaction ───────────────────
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (sender_id, receiver_id, amount, comment, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$sender_id, $to_user_id, $amount, $comment]);
        $tx_id = (int) $pdo->lastInsertId();

        // ── STEP 11: COMMIT ───────────────────────────────────
        // All three operations (deduct, add, record) succeeded.
        // Make them permanent.
        $pdo->commit();

        return ['success' => true, 'error' => null, 'tx_id' => $tx_id,
                'receiver_username' => $receiver['username']];

    } catch (PDOException $e) {
        // Something unexpected happened at the DB level
        // Roll back ALL changes — no money moved, no record created
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log real error server-side, show generic message to user
        error_log('[TRANSFER ERROR] ' . $e->getMessage());
        return ['success' => false, 'error' => 'A database error occurred. Please try again.', 'tx_id' => null];
    }
}