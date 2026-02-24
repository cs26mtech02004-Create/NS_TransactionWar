<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

session_destroy();
header("Location: login.php");
exit();