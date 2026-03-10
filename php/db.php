<?php
// db.php — Database connection + shared helpers
// Include on every page: require_once __DIR__ . '/db.php';
// This file ONLY defines functions. It does NOT redirect or start sessions.

$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'transactiwar';
$db_user = getenv('DB_USER') ?: 'tw_user';
$db_pass = getenv('DB_PASS') ?: 'TW_StrongPass#2026';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $db_host, $db_name, $db_user, $db_pass;
        $retries = 5;
        while ($retries > 0) {
            try {
                $pdo = new PDO(
                    "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                    $db_user,
                    $db_pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
                break;
            } catch (PDOException $e) {
                $retries--;
                if ($retries === 0) {
                    error_log('DB connection failed: ' . $e->getMessage());
                    http_response_code(500);
                    die('Database connection failed. Please try again later.');
                }
                sleep(2);
            }
        }
    }
    return $pdo;
}

// Log page visit — required by spec
function logActivity(string $page, string $username = 'guest'): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'invalid';
    try {
        getDB()->prepare(
            "INSERT INTO activity_log (webpage, username, ip_address) VALUES (?, ?, ?)"
        )->execute([$page, $username, $ip]);
    } catch (PDOException $e) {
        error_log('logActivity failed: ' . $e->getMessage());
    }
}

// Generate CSRF token (once per session)
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF on POST — call at top of every POST handler
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}

// Redirect to login if not authenticated
function auth_required(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit();
    }
}

// Safe HTML output — always use on user data
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
