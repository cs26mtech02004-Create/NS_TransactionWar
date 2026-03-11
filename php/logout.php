<?php
session_start();
require_once __DIR__ . '/db.php';
logActivity('logout.php', $_SESSION['username'] ?? 'guest');
$_SESSION = [];
session_destroy();
header('Location: /login.php');
exit();
