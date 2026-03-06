<?php
// lookup_user.php — AJAX endpoint called by transfer.php JS
// Returns JSON: { found: true, id: 3, username: "alice" }
//           or: { found: false }

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Must be logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['found' => false, 'error' => 'Unauthorized']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['found' => false]);
    exit();
}

$stmt = getDB()->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if ($user) {
    echo json_encode([
        'found'    => true,
        'id'       => (int)$user['id'],
        'username' => $user['username'],   // safe — only used in JS textContent, not innerHTML
    ]);
} else {
    echo json_encode(['found' => false]);
}
