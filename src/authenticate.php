<?php
require_once 'db.php';
session_start();

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    die("invalid request.");
}

//CSRF Guard
if(!verifyCsrfToken($_POST['csrf_token'])){
    die("Security Error:CSRF token invlaid.");
}

$username= substr(trim($_POST['username'] ?? ''), 0, 50); // Keep it short;
$password=$_POST['password'];

//Use prepared statements to avoid SQL Injection

$stmt = $pdo->prepare("SELECT id,password_hash FROM users WHERE username = :username");
$stmt->execute(['username'=>$username]);
$user = $stmt->fetch();

//Password Verification 
if($user && password_verify($password, $user['password_hash'])){
    //Prevent Session Fixation: Generate new id on login
    session_regenerate_id(true);
    $_SESSION['user_id']=$user['id'];
    header("Location: dashboard.php");
    exit;
}else{
    sleep(1);
    exit("Invalid Usern or password");
}
?>