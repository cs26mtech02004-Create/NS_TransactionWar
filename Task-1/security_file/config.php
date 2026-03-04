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

// Auto logout after 15 min activity
$timeout = 90;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)
) {

    $_SESSION = [];// empty session variable

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();

    header("Location: login.php");
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();
?>