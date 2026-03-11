<?php
require_once 'db.php';
session_start();


if($_SERVER["REQUEST_METHOD"]=== "POST"){

    //1. CSRF VALIDATION
// THE GATEKEEPER
if (!verifyCsrfToken($_POST['csrf_token'])) {
    die("Security Alert: Invalid form submission.");
}

    //2. Capture and Sanitize
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    //3. Strict Length & Format Validation
    if(strlen($username) < 3 || strlen($username) > 20){
        die("Error:USername must be between 3 and 20 characters");
    }
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))
        {
            die("Error:Invalid email format");
        }
    if(strlen($password)<8 || strlen($password)>72){
        die("Error : Password must be 8-72 characters long");
    }
    //4. Secure Password Hashing
    //Here we use the BCRYPT algorithm
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    //5. Database Insertion 
    try{
        //Inital credit of Rs. 100 as per project
        $sql = "INSERT INTO users (username,email,password_hash,balance)
            VALUES(:username, :email, :password, 100.00)" ;

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username'=>$username,
        'email'=>$email,'password'=>$hashed_password]);
    
    //Success: Invalidate the token so that it can't be used again
    unset($_SESSION['csrf_token']);
    echo "Registration successful!";
    }
    catch(PDOException $e)
    {
        error_log($e->getMessage()); // Logs the real error for DEV

        //Check for duplicate useranme or email

        if($e->getCode()==23000){
            die("Error: That username or email is already taken");
        }
        die("A system error occured. Please try again later");
    }
} else{
    header("Location:register.php");
    exit();
}