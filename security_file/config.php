<?php

session_set_cookie_params([
    'lifetime' => 0,                             // Cookie expires when browser closes
    'path' => '/',                               // Cookie available to entire website
    'domain' => '',                              // Default current domain only
    'secure' => true,                         // Cookie sent ONLY over HTTPS
    'httponly' => true,                          // JavaScript CANNOT access the cookie.
    'samesite' => 'Strict'                       // Browser will NOT send cookie on cross-site requests.
]);

ini_set('session.use_strict_mode', 1);

session_start();

// Auto logout after 90 sec inactivity
$timeout = 90;

if (isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {

    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();
?>
