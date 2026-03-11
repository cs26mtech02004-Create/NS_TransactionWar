<?php
require_once 'db.php';
session_start();
?>

<form action="authenticate.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>"> 
    <label>Username:</label>
    <input type="text" name="username" required>
    <label>Password:</label>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
</form>