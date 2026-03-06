<?php
// ============================================================
// db.php — Database connection + shared helpers
// Include this file at the top of every PHP page:
//   require_once __DIR__ . '/db.php';
// ============================================================

// --- Read env vars set by Docker (see docker-compose.yml) ---
$db_host = getenv('DB_HOST') ?: 'db';        // 'db' = docker service name
$db_name = getenv('DB_NAME') ?: 'transactiwar';
$db_user = getenv('DB_USER') ?: 'tw_user';
$db_pass = getenv('DB_PASS') ?: 'TW_StrongPass#2026';

// --- PDO connection (singleton pattern) ---
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $db_host, $db_name, $db_user, $db_pass;
        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,  // IMPORTANT: real prepared statements
                ]
            );
        } catch (PDOException $e) {
            // Never expose DB errors to the browser
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Service temporarily unavailable.');
        }
    }
    return $pdo;
}

// ============================================================
// logActivity() — Required by spec
// Call at the top of every protected page:
//   logActivity('transfer.php', $_SESSION['username'] ?? 'guest');
// ============================================================
function logActivity(string $page, string $username = 'guest'): void {
    // Get real IP (handles proxy / Docker NAT)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }

    // Sanitize IP just in case
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'invalid';

    try {
        $stmt = getDB()->prepare(
            "INSERT INTO activity_log (webpage, username, ip_address)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$page, $username, $ip]);
    } catch (PDOException $e) {
        error_log('logActivity failed: ' . $e->getMessage());
        // Non-fatal — don't kill the page if logging fails
    }
}

// ============================================================
// csrf_token() — Generate once per session, reuse after
// ============================================================
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ============================================================
// csrf_verify() — Call at top of every POST handler
// ============================================================
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
}

// ============================================================
// auth_required() — Redirect to login if not logged in
// ============================================================
function auth_required(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit();
    }
}

// ============================================================
// h() — Short alias for htmlspecialchars (use on ALL output)
// ============================================================
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
