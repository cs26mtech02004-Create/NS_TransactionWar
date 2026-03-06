<?php
require 'security_file/db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    if (empty($username) || empty($password) || empty($email)) {
        $error = "All fields are required.";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM MAJOR WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            if ($checkStmt->rowCount() > 0) {
                $error = "Username or Email is already taken.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $sql = "INSERT INTO MAJOR (username, passwd, email) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$username, $hashedPassword, $email])) {
                    $success = "Registration successful! You've been credited with ₹100.";
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "A system error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Join Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./CSS/register.css">

</head>
<body>

<div class="grid-lines"></div>
<div class="corner corner--tl"></div>
<div class="corner corner--br"></div>

<div class="wrapper">

    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <polygon points="24,4 44,14 44,34 24,44 4,34 4,14" stroke="#c9a84c" stroke-width="1" fill="none" opacity="0.6"/>
                <polygon points="24,10 38,18 38,30 24,38 10,30 10,18" stroke="#c9a84c" stroke-width="0.5" fill="none" opacity="0.3"/>
                <circle cx="24" cy="24" r="5" fill="#c9a84c" opacity="0.9"/>
            </svg>
        </div>
        <div class="brand-title">Bannking system</div>
        <div class="brand-sub">Member Portal</div>
    </div>

    <div class="card">

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card-header">
            <div class="bonus-badge">New member bonus — ₹100 credit</div>
            <div class="card-title">Create Account</div>
            <div class="card-desc">Begin your journey</div>
            <div class="divider"></div>
        </div>

        <form method="post" autocomplete="off">

            <div class="field">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <input type="text" id="username" name="username"
                           placeholder="your_handle"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email"
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-10 7L2 7"/>
                    </svg>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••••"
                           required>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
            </div>

            <button type="submit" class="btn">
                <span>Register &amp; Claim Bonus</span>
            </button>

        </form>
    </div>

    <div class="footer-text">
        Already a member? <a href="login.php">Sign in here</a>
    </div>

</div>

</body>
</html>
