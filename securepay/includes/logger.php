<?php
/**
 * FILE: includes/logger.php
 * PURPOSE: Central activity logging function.
 *
 * LOGS FOUR THINGS PER ENTRY (as per spec):
 *   - Webpage  (which URL was accessed)
 *   - Username (who accessed it, or GUEST if not logged in)
 *   - Timestamp
 *   - Client IP Address
 *
 * TWO STORAGE BACKENDS (defence in depth):
 *   1. Flat file: /var/www/private/logs/activity.log  (outside webroot)
 *   2. Database: activity_log table (searchable, filterable by admin)
 *
 * SECURITY MEASURES:
 *   1. Log file stored OUTSIDE webroot — cannot be accessed via browser URL
 *   2. Log injection prevention — newlines and pipe chars stripped from
 *      all user-controlled values before writing
 *   3. REMOTE_ADDR used for IP — not X-Forwarded-For (which can be forged)
 *   4. Log file size checked — rotated at 10MB to prevent disk exhaustion
 *   5. All values sanitised before writing to both file and DB
 *
 * HOW TO USE:
 *   Add this ONE line at the top of every page (after session setup):
 *     require_once __DIR__ . '/../includes/logger.php';
 *     log_activity();
 *
 *   That's it. The function reads the session and server variables itself.
 */

define('LOG_FILE',     '/var/www/private/logs/activity.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB before rotation

/**
 * sanitise_log_value(string $value): string
 *
 * Strips characters that could corrupt the log format or inject fake entries.
 *
 * LOG INJECTION EXPLAINED:
 *   Our log format is pipe-delimited:
 *     [2024-01-15 10:30:00] | user | /dashboard.php | 192.168.1.1
 *
 *   If a username contains a newline, an attacker could inject a fake entry:
 *     Username: "admin\n[2024-01-15] | admin | /transfer.php | 1.2.3.4"
 *
 *   This would write TWO lines to the log — the real one and the fake one.
 *   The fix: strip ALL newlines and pipe characters from logged values.
 */
function sanitise_log_value(string $value): string {
    // Remove carriage return, newline, vertical tab, form feed
    $value = str_replace(["\r", "\n", "\v", "\f"], '', $value);
    // Remove pipe characters (our delimiter) and null bytes
    $value = str_replace(['|', "\0"], '', $value);
    // Trim whitespace and limit length to prevent oversized log entries
    return substr(trim($value), 0, 255);
}

/**
 * get_client_ip(): string
 *
 * Returns the real client IP address for logging purposes.
 *
 * WHY NOT X-Forwarded-For?
 *   X-Forwarded-For is an HTTP header that anyone can set to any value.
 *   An attacker can send:
 *     X-Forwarded-For: 127.0.0.1
 *   to make their requests appear to come from localhost and bypass
 *   IP-based rate limiting.
 *
 *   REMOTE_ADDR is set by the web server based on the actual TCP connection
 *   — it cannot be forged by the client.
 *
 *   EXCEPTION: If your app runs behind a trusted reverse proxy (e.g., Nginx
 *   in the same Docker network), the proxy's IP will appear in REMOTE_ADDR
 *   and the real client IP will be in X-Forwarded-For. In that case:
 *     - Only trust X-Forwarded-For if REMOTE_ADDR matches your proxy's IP
 *     - Whitelist the proxy IP explicitly — never trust blindly
 *   Our Docker setup has no reverse proxy, so REMOTE_ADDR is always correct.
 */
function get_client_ip(): string {
    // REMOTE_ADDR = actual TCP connection IP = cannot be forged
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Validate it looks like a real IP (IPv4 or IPv6)
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

/**
 * rotate_log_if_needed(): void
 *
 * If the log file exceeds LOG_MAX_SIZE, rename it to activity.log.bak
 * and start a fresh activity.log.
 *
 * WHY ROTATE?
 *   An attacker flooding the site with requests fills the disk.
 *   Full disk = application crashes, logs lost, potential DoS.
 *   Rotation caps the log size and keeps the system healthy.
 *
 * NOTE: In production, use logrotate (Linux system tool) instead.
 * This PHP implementation is a simple fallback.
 */
function rotate_log_if_needed(): void {
    if (!file_exists(LOG_FILE)) return;

    $size = filesize(LOG_FILE);
    if ($size !== false && $size >= LOG_MAX_SIZE) {
        $bak = LOG_FILE . '.bak';
        // If a .bak already exists, remove it first
        if (file_exists($bak)) {
            unlink($bak);
        }
        rename(LOG_FILE, $bak);
    }
}

/**
 * log_activity(?PDO $pdo = null): void
 *
 * Main logging function. Call at the top of every page.
 * Writes to both the flat file and the database.
 *
 * @param PDO|null $pdo  Pass the PDO connection for DB logging.
 *                       If null, only file logging occurs.
 */
function log_activity(?PDO $pdo = null): void {

    // ── COLLECT VALUES ────────────────────────────────────────
    $username  = $_SESSION['username'] ?? 'GUEST';
    $page      = $_SERVER['REQUEST_URI'] ?? '/unknown';
    $ip        = get_client_ip();
    $timestamp = date('Y-m-d H:i:s');

    // ── SANITISE ALL VALUES ───────────────────────────────────
    // sanitise_log_value() strips newlines and pipe chars from every
    // field that could contain user-controlled data.
    // Even 'page' (REQUEST_URI) is sanitised — a crafted URL could
    // contain newlines in some server configurations.
    $safe_username  = sanitise_log_value($username);
    $safe_page      = sanitise_log_value($page);
    $safe_ip        = sanitise_log_value($ip);       // Already validated above, but belt+braces
    $safe_timestamp = sanitise_log_value($timestamp); // Server-generated, but consistent practice

    // ── FILE LOGGING ──────────────────────────────────────────
    rotate_log_if_needed();

    // Pipe-delimited format: easy to parse with awk/grep
    // [TIMESTAMP] | USERNAME | PAGE | IP
    $log_line = "[{$safe_timestamp}] | {$safe_username} | {$safe_page} | {$safe_ip}" . PHP_EOL;

    // FILE_APPEND: adds to end of file (does not overwrite)
    // LOCK_EX: acquires exclusive lock before writing (prevents
    // garbled output if two requests write simultaneously)
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }
    file_put_contents(LOG_FILE, $log_line, FILE_APPEND | LOCK_EX);

    // ── DATABASE LOGGING ──────────────────────────────────────
    // DB logging is secondary — if it fails, we don't crash the page.
    // The file log is the primary record.
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO activity_log (username, page, ip_address, logged_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$safe_username, $safe_page, $safe_ip]);
        } catch (PDOException $e) {
            // DB logging failure must never crash the page.
            // Log the error to PHP error log (server-side only).
            error_log('[LOGGER DB ERROR] ' . $e->getMessage());
        }
    }
}