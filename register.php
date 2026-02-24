<?php
require 'security_file/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];

    if (empty($username) || empty($password) || empty($email)) {
        echo "All fields are required.";
        
    } else {

        try {

            $checkStmt = $pdo->prepare("SELECT id FROM MAJOR WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);

            if ($checkStmt->rowCount() > 0) {
                die("Error: Username or Email is already taken.");
            }


            // 2. Hash password using a secure cryptography library function 
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);


            $sql = "INSERT INTO MAJOR (username, passwd, email) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$username, $hashedPassword, $email])) {

                echo "Registration successful! You have been credited with Rs. 100.";
            }
            echo "checking done\n";
        } catch (PDOException $e) {
            // Avoid leaking DB details in production
            error_log($e->getMessage());
            die("A system error occurred. Please try again later.");
        }
    }
}
?>


<html>

<head>
    <title>Register</title>
</head>

<body>
    <h2>Register here</h2>
    <form method="post">
        Username: <input type="text" name="username" required><br><br>
        Email: <input type="email" name="email" required><br><br>

        Password: <input type="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
</body>

</html>