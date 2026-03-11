<?php
require_once __DIR__ . '/db.php';
$accounts = [
    ['alice',   'alice@transactiwar.local'],
    ['bob',     'bob@transactiwar.local'],
    ['charlie', 'charlie@transactiwar.local'],
    ['dave',    'dave@transactiwar.local'],
    ['eve',     'eve@transactiwar.local'],
];
$stmt = getDB()->prepare("INSERT IGNORE INTO users (username,email,password_hash,balance) VALUES(?,?,?,100.00)");
foreach ($accounts as [$u, $e]) {
    $hash = password_hash('Password@123', PASSWORD_BCRYPT, ['cost'=>12]);
    $stmt->execute([$u, $e, $hash]);
    echo ($stmt->rowCount() > 0 ? "Created" : "Skipped") . ": $u\n";
}
echo "Done.\n";
