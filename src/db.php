<?php 
ob_start(); // Start Output Buffering
//Secure Session Parameters 
session_set_cookie_params([
    'lifetime' => '0',
    'path'=>'/',
    'httponly'=> true, //Prevents JS from reading session cookies
    'secure'=> false,
    'samesite' => 'Strict' //BLocks CSRF at the browser level
]);
// We load credetials fromt he environment variables
$host = 'db';
$db = getenv('DB_NAME');
$user = 'root';
$password = getenv('DB_PASSWORD');
$charset = 'utf8mb4';

// Data Source NAme (DSN) - This is the address 
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

//Security Options -

$options = [ 
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]; 

try{
    $pdo =new PDO($dsn,$user,$password, $options);
}catch(\PDOException $e){
    //Log the error internally, don't show the user the deatails - dsiplayed at the server
    error_log($e->getMessage());
    exit('A database error occured. Please try again later.');
}


//CSRF Helpers
function generateCsrfToken(){
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token){
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$token);
}
?>

