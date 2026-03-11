<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['found'=>false]); exit(); }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['found'=>false]); exit(); }
$s = getDB()->prepare("SELECT id,username FROM users WHERE id=?");
$s->execute([$id]);
$u = $s->fetch();
echo json_encode($u ? ['found'=>true,'id'=>(int)$u['id'],'username'=>$u['username']] : ['found'=>false]);
