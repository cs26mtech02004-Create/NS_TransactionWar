<?php

require 'security_file/config.php';        // session + cookie security
require 'security_file/db.php';            // PDO connection
require 'security_file/csrf_token.php';    // CSRF functions

$error = '';

generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF Token");
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } 
    // else if($_SESSION["username"] = $username and (time()- $_SESSION["LAST_ACTIVITY"] < 90)){
    //     $error = " you are already login";
        
    // }
    else {

        
        $stmt = $pdo->prepare("SELECT id, username, passwd, failed_attempts, account_locked_until 
                               FROM MAJOR
                               WHERE username = :username ");

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if ($user) {

            
            if (!empty($user['account_locked_until']) && 
                strtotime($user['account_locked_until']) > time()) {

                $error = "Account locked. Try after ".$user['account_locked_until'];

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

                $error = "Invalid password.".$failed." failed attempts ";
            }

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet"> -->
    <link rel="stylesheet" href="./CSS/login.css">
</head>
<body>

    <div class="bg-grid"></div>
    <div class="bg-orb"></div>

    <div class="wrapper">

        <!-- Left panel -->
        <div class="left">
            <div class="logo">
                <div class="logo-mark"></div>
                <span class="logo-name">Brahamgupta Project</span>
            </div>

            <div class="left-content">
                <h1>Welcome to<br><em>Banking</em><br>System</h1>
                <p>Secure, instantaneous transfers with the discretion your wealth deserves.</p>
            </div>

            <div class="left-footer">
                <div class="stat-row">
                    <div class="stat">
                        <span class="stat-num">256</span>
                        <span class="stat-label">Bit Encryption</span>
                    </div>
                    <div class="stat">
                        <span class="stat-num">99.9</span>
                        <span class="stat-label">% Uptime</span>
                    </div>
                </div>
                <span>© 2026 Financial. All rights reserved.</span>
            </div>
        </div>

        <!-- Right panel -->
        <div class="right">
            <div class="form-header">
                <div class="eyebrow">Secure Access</div>
                <h2>Welcome</h2>
            </div>

            <!-- Error message — toggle .visible via PHP when $error is set -->
            <div class="error-msg visible" id="error-msg">
                <p><?php echo $error ?></p> 
            </div>

            <form method="POST" action="login.php" autocomplete="off">

                <!-- CSRF token (PHP) -->
                <input type="hidden" name="csrf_token" value="<?php  echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username"
                               placeholder="your_username" maxlength="50" required>
                        <div class="input-line"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="••••••••••" required>
                        <div class="input-line"></div>
                    </div>
                </div>

                <!-- <div class="form-footer">
                    <a href="#">Forgot password?</a>
                </div> -->

                <button type="submit" class="btn">
                    <span>Authenticate →</span>
                </button>

            </form>

            <div class="register-link">
                No account? <a href="register.php">Request access</a>
            </div>
        </div>

    </div>

    <script>
        // Hide error if no error is present — in production this is controlled by PHP
        // Remove this block and use PHP to conditionally add .visible to #error-msg
        // document.getElementById('error-msg').classList.remove('visible');
    </script>

</body>
</html>
