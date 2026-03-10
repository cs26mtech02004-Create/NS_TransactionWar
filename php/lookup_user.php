<?php
// lookup_user.php — AJAX endpoint, returns JSON user info
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['found' => false]);
    exit();
}

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['found' => false]); exit(); }

$stmt = getDB()->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

echo json_encode($user
    ? ['found' => true, 'id' => (int)$user['id'], 'username' => $user['username']]
    : ['found' => false]
);
