<?php
require_once 'db.php';
session_start();

//Gate Keeper
if(!isset($_SESSION['user_id'])){
    //If no session exsts. redirect them bakc to login
    header("Location: login.php");
    exit;
}

echo "<h1>Welcome to Dashboard! </h1>";
echo "<p>Your User ID is: ". htmlspecialchars($_SESSION['user_id']). "</p>";
echo "<a href='logout.php'>Logout</a>";
?>