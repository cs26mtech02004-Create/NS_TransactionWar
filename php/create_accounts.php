<?php
// create_accounts.php — Auto-creates demo accounts (required by spec)
// Called automatically by entrypoint.sh on container start

require_once __DIR__ . '/db.php';

$accounts = [
    ['username' => 'alice',   'email' => 'alice@transactiwar.local',   'password' => 'Password@123'],
    ['username' => 'bob',     'email' => 'bob@transactiwar.local',     'password' => 'Password@123'],
    ['username' => 'charlie', 'email' => 'charlie@transactiwar.local', 'password' => 'Password@123'],
    ['username' => 'dave',    'email' => 'dave@transactiwar.local',    'password' => 'Password@123'],
    ['username' => 'eve',     'email' => 'eve@transactiwar.local',     'password' => 'Password@123'],
];

$pdo  = getDB();
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO users (username, email, password_hash, balance)
     VALUES (?, ?, ?, 100.00)"
);

foreach ($accounts as $acc) {
    try {
        $hash = password_hash($acc['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt->execute([$acc['username'], $acc['email'], $hash]);
        if ($stmt->rowCount() > 0) {
            echo "Created: {$acc['username']}\n";
        } else {
            echo "Skipped (exists): {$acc['username']}\n";
        }
    } catch (PDOException $e) {
        echo "Error for {$acc['username']}: {$e->getMessage()}\n";
    }
}
echo "Done.\n";
