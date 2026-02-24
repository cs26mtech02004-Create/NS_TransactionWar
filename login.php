<?php

require 'security_file/config.php';        // session + cookie security
require 'security_file/db.php';            // PDO connection
require 'security_file/csrf_token.php';    // CSRF functions

$error = '';

generateCSRFToken();
echo "hiii";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
echo "ggg";
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF Token");
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } else {

        
        $stmt = $pdo->prepare("SELECT id, username, passwd, failed_attempts, account_locked_until 
                               FROM MAJOR
                               WHERE username = :username ");

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if ($user) {

            
            if (!empty($user['account_locked_until']) && 
                strtotime($user['account_locked_until']) > time()) {

                $error = "Account locked. Try again later.";

            } 
            elseif (password_verify($password, $user['passwd'])) {
                // Reset failed attempts
                $reset = $pdo->prepare("UPDATE MAJOR
                                        SET failed_attempts = 0, account_locked_until = NULL 
                                        WHERE id = :id");
                                         
                $reset->execute(['id' => $user['id']]);
                

                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['LAST_ACTIVITY'] = time();

                header("Location: dashboard.php");
                exit();
            } 
            else {

                // Increase failed attempts
                $failed = $user['failed_attempts'] + 1;

                if ($failed >= 3) {
                    $lockTime = date("Y-m-d H:i:s", time() + 86400); // 24 hours lock
                    $update = $pdo->prepare("UPDATE MAJOR
                                             SET failed_attempts = :failed, 
                                                 account_locked_until = :lock 
                                             WHERE id = :id");

                    $update->execute([
                        'failed' => $failed,
                        'lock'   => $lockTime,
                        'id'     => $user['id']
                    ]);
                } else {
                    $update = $pdo->prepare("UPDATE MAJOR 
                                             SET failed_attempts = :failed 
                                             WHERE id = :id");

                    $update->execute([
                        'failed' => $failed,
                        'id'     => $user['id']
                    ]);
                }

                $error = "Invalid username or password.";
            }

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>




<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>

<h2>Login</h2>

<?php if (!empty($error)) : ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST" autocomplete="off">

    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <label>Username:</label><br>
    <input type="text" name="username" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Login</button>

</form>

</body>
</html>

