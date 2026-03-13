<?php
/**
 * FILE: config/db.php
 * PURPOSE: Creates and returns a PDO database connection.
 *
 * WHY PDO?
 *   PDO (PHP Data Objects) forces us to use "prepared statements" which
 *   means user input is NEVER directly glued into a SQL string.
 *   This is the #1 defence against SQL Injection.
 *
 * WHY NOT mysqli?
 *   PDO works with any database engine and its prepared statement API
 *   is cleaner and harder to misuse than raw mysqli.
 *
 * SECURITY: Credentials come from environment variables (set in .env via
 *   Docker). They are NEVER hardcoded in this file.
 */

// getenv() reads values that Docker injects from your .env file.
// If the env variable is missing we fall back to a safe default for
// local development only — never use defaults in production.
$host   = getenv('DB_HOST');       // Docker service name
$dbname = getenv('DB_NAME');
$user   = getenv('DB_USER');
$pass   = getenv('DB_PASS');              // Empty default forces .env to be set
$port   = getenv('DB_PORT');     

// DSN = Data Source Name. This tells PDO which driver and database to use.
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

// charset=utf8mb4 is critical:
//   - utf8mb4 supports ALL unicode characters including emoji
//   - Some older MySQL "utf8" configs had a bug where certain byte
//     sequences could bypass input filtering — utf8mb4 closes that gap

$options = [
    // ERRMODE_EXCEPTION: Any SQL error throws a PHP Exception instead of
    // silently failing. This means we catch errors rather than ignore them.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // FETCH_ASSOC: query results come back as ['column' => 'value'] arrays,
    // not numbered arrays. Cleaner and less error-prone to work with.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // EMULATE_PREPARES = false: Forces PDO to use TRUE prepared statements
    // in the MySQL driver rather than emulating them in PHP.
    // TRUE prepared statements are sent to MySQL in two separate steps:
    //   Step 1: Send the SQL template with placeholders
    //   Step 2: Send the data separately
    // This means MySQL itself parses the SQL before it ever sees the data —
    // making SQL injection structurally impossible, not just filtered.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // $pdo is a global variable. Every other file does:
    //   require_once __DIR__ . '/../config/db.php';
    // and then uses $pdo directly.
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // SECURITY: Never echo $e->getMessage() to the browser in production.
    // The real error message can reveal your database structure, server paths,
    // and credentials fragments — all useful to an attacker.
    // Log the real error server-side; show a generic message to the user.
    error_log('[DB ERROR] ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please try again later.');
}