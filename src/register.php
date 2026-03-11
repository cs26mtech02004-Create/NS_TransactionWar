<?php // Load config and session settings first
require_once 'db.php';
//Start the session
session_start();?>
<!DOCTYPE html>
<html>
<head>
    <title>Secre Registration</title>
</head>
<body>
    <h2>Register Account</h2>
    <form action="process_registration.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div>
            <label>Username:</label>
            <input type="text" name="username" maxlength="20" required>
        </div>
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Password (min 8 chars) :</label>
            <input type="password" name="password" minlength="8" required>
        </div>
        <button type="submit">Create Account</button>
    </form>
</body>
</html>