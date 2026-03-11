<?php
/**
 * FILE: admin_logs.php
 * PURPOSE: View activity logs. Accessible ONLY after admin login.
 *
 * SECURITY MEASURES:
 *   1. Admin session check ($is_admin) — redirects to admin_login.php if not set
 *   2. Admin session is SEPARATE from user session
 *      (a regular logged-in user cannot access this page)
 *   3. All log output escaped with htmlspecialchars()
 *      (log entries contain user-controlled data — XSS via log display)
 *   4. DB query uses prepared statements + admin-only WHERE scoping
 *   5. Page/filter params validated before use in queries
 *   6. CSRF-protected admin logout
 *
 * TWO VIEWS:
 *   - Database view: filterable, searchable, paginated (primary)
 *   - Raw file view: shows last 200 lines of activity.log (secondary)
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security_headers.php';

send_security_headers();

// ── ADMIN AUTH GUARD ──────────────────────────────────────────
// This is NOT the same as auth_guard.php (which checks $_SESSION['user_id']).
// This checks $_SESSION['is_admin'] — a completely separate flag.
// A regular user who is logged in still cannot access this page.
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: /admin_login.php');
    exit();
}

// ── ADMIN LOGOUT ──────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $token = $_GET['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!empty($stored) && hash_equals($stored, $token)) {
        // Destroy admin session completely
        $_SESSION = [];
        session_destroy();
    }
    header('Location: /admin_login.php');
    exit();
}

// ── PAGINATION & FILTERS ──────────────────────────────────────
define('ADMIN_PER_PAGE', 50);

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'default' => 1]
]);

// Filter inputs — all validated/sanitised before use in queries
$filter_username = trim($_GET['username'] ?? '');
$filter_ip       = trim($_GET['ip']       ?? '');
$filter_page_url = trim($_GET['pageurl']  ?? '');

// Validate IP filter if provided
if ($filter_ip && !filter_var($filter_ip, FILTER_VALIDATE_IP)) {
    $filter_ip = ''; // Invalid IP — ignore filter
}

// Build WHERE clause dynamically — safely
// We build an array of conditions and bind params separately
// Never concatenate user input directly into SQL
$where_parts  = [];
$bind_params  = [];

if ($filter_username !== '') {
    $where_parts[] = 'username LIKE ?';
    $bind_params[]  = '%' . addcslashes($filter_username, '%_') . '%';
}
if ($filter_ip !== '') {
    $where_parts[] = 'ip_address = ?';
    $bind_params[]  = $filter_ip;
}
if ($filter_page_url !== '') {
    $where_parts[] = 'page LIKE ?';
    $bind_params[]  = '%' . addcslashes($filter_page_url, '%_') . '%';
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Count total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log {$where_sql}");
$count_stmt->execute($bind_params);
$total       = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * ADMIN_PER_PAGE;

// Fetch log entries
$log_stmt = $pdo->prepare(
    "SELECT id, username, page, ip_address, logged_at
     FROM activity_log
     {$where_sql}
     ORDER BY logged_at DESC
     LIMIT ? OFFSET ?"
);
// Merge filter params with LIMIT/OFFSET
$log_stmt->execute(array_merge($bind_params, [ADMIN_PER_PAGE, $offset]));
$log_entries = $log_stmt->fetchAll();

// ── STATS SUMMARY ─────────────────────────────────────────────
$stats_stmt = $pdo->query(
    'SELECT
        COUNT(*) AS total_events,
        COUNT(DISTINCT username) AS unique_users,
        COUNT(DISTINCT ip_address) AS unique_ips,
        COUNT(CASE WHEN logged_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) AS last_hour
     FROM activity_log'
);
$stats = $stats_stmt->fetch();

// Generate CSRF token for admin logout link
$csrf_token = generate_csrf_token();

$page_title = 'Admin — Activity Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <title>Admin Logs — SecurePay</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        /* Admin panel uses amber accent instead of green */
        .admin-header { border-bottom-color: var(--amber); }
        .admin-header .atm-logo { color: var(--amber); text-shadow: 0 0 16px rgba(255,179,0,0.7); }
        .stat-box {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            padding: 14px 18px;
            flex: 1;
            min-width: 140px;
        }
        .stat-value { font-family: var(--font-display); font-size: 2rem; color: var(--amber); }
        .stat-label { font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.1em; }
    </style>
</head>
<body>
<div class="atm-screen">

    <header class="atm-header admin-header">
        <div class="atm-logo" style="color:var(--amber);">
            SECURE<span style="color:var(--green)">PAY</span>
            <span style="font-size:0.9rem; color:var(--amber-dim); margin-left:10px;">ADMIN</span>
        </div>
        <nav class="atm-nav">
            <span class="nav-user" style="color:var(--amber-dim);">
                [ <?= htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8') ?> ]
            </span>
            <a href="/admin_logs.php" style="color:var(--amber);">LOGS</a>
            <a href="/admin_logs.php?logout=1&csrf_token=<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
               class="nav-logout"
               onclick="return confirm('Log out of admin panel?')">
                LOGOUT
            </a>
        </nav>
    </header>

    <div class="atm-statusbar">
        <span>&gt; ACTIVITY LOG VIEWER</span>
        <span>ADMIN SESSION ACTIVE <span class="status-blink">●</span> &nbsp;|&nbsp; <?= date('d-M-Y H:i') ?></span>
    </div>

    <main class="atm-main">

        <!-- STATS ROW -->
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
            <div class="stat-box">
                <div class="stat-value"><?= number_format($stats['total_events']) ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= number_format($stats['unique_users']) ?></div>
                <div class="stat-label">Unique Users</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= number_format($stats['unique_ips']) ?></div>
                <div class="stat-label">Unique IPs</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color:var(--green);"><?= number_format($stats['last_hour']) ?></div>
                <div class="stat-label">Last Hour</div>
            </div>
        </div>

        <!-- FILTER FORM -->
        <div class="atm-panel mb-3">
            <div class="atm-panel-header" style="color:var(--amber);">FILTER LOGS</div>
            <div class="atm-panel-body">
                <!--
                    Filter form uses GET — bookmarkable, no CSRF needed
                    (read-only operation, does not change any server state)
                -->
                <form method="GET" action="/admin_logs.php">
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                        <div class="atm-form-group" style="flex:1; min-width:160px; margin-bottom:0;">
                            <label>USERNAME</label>
                            <input type="text" name="username" maxlength="50"
                                value="<?= htmlspecialchars($filter_username, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Filter by username">
                        </div>
                        <div class="atm-form-group" style="flex:1; min-width:160px; margin-bottom:0;">
                            <label>IP ADDRESS</label>
                            <input type="text" name="ip" maxlength="45"
                                value="<?= htmlspecialchars($filter_ip, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Exact IP match">
                        </div>
                        <div class="atm-form-group" style="flex:1; min-width:160px; margin-bottom:0;">
                            <label>PAGE URL</label>
                            <input type="text" name="pageurl" maxlength="100"
                                value="<?= htmlspecialchars($filter_page_url, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Filter by page">
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="submit" class="btn btn-secondary"
                                    style="border-color:var(--amber-dim); color:var(--amber);">
                                ▶ FILTER
                            </button>
                            <a href="/admin_logs.php" class="btn btn-secondary">CLEAR</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- LOG TABLE -->
        <div class="atm-panel mb-3">
            <div class="atm-panel-header" style="color:var(--amber);">
                ACTIVITY LOG
                <span style="font-size:0.8rem; color:var(--text-dim); margin-left:10px;">
                    (<?= number_format($total) ?> entries — page <?= $page ?> of <?= $total_pages ?>)
                </span>
            </div>
            <div class="atm-panel-body" style="padding:0; overflow-x:auto;">

                <?php if (empty($log_entries)): ?>
                    <p class="text-muted" style="padding:20px; font-size:0.85rem;">
                        No log entries found.
                    </p>
                <?php else: ?>
                    <table class="atm-table" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>TIMESTAMP</th>
                                <th>USERNAME</th>
                                <th>PAGE</th>
                                <th>IP ADDRESS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($log_entries as $entry): ?>
                                <tr>
                                    <td class="muted"><?= htmlspecialchars((string)$entry['id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="muted" style="white-space:nowrap;">
                                        <?= htmlspecialchars($entry['logged_at'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <!--
                                            XSS VIA LOG DISPLAY:
                                            Log entries contain user-controlled data (usernames, URLs).
                                            Without htmlspecialchars(), a username like
                                            <script>alert(1)</script> would execute here.
                                            htmlspecialchars() neutralises this completely.
                                        -->
                                        <?php if ($entry['username'] !== 'GUEST'): ?>
                                            <span style="color:var(--green);">
                                                <?= htmlspecialchars($entry['username'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="muted">GUEST</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.78rem; word-break:break-all;">
                                        <?= htmlspecialchars($entry['page'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td style="font-family:var(--font-mono); color:var(--amber-dim);">
                                        <?= htmlspecialchars($entry['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&username=<?= urlencode($filter_username) ?>&ip=<?= urlencode($filter_ip) ?>&pageurl=<?= urlencode($filter_page_url) ?>"
                       class="btn btn-secondary" style="padding:6px 14px; font-size:0.82rem;">◀ PREV</a>
                <?php endif; ?>
                <?php
                // Show a window of pages around the current page
                $start = max(1, $page - 3);
                $end   = min($total_pages, $page + 3);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a href="?page=<?= $p ?>&username=<?= urlencode($filter_username) ?>&ip=<?= urlencode($filter_ip) ?>&pageurl=<?= urlencode($filter_page_url) ?>"
                       class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
                       style="padding:6px 12px; font-size:0.82rem;">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&username=<?= urlencode($filter_username) ?>&ip=<?= urlencode($filter_ip) ?>&pageurl=<?= urlencode($filter_page_url) ?>"
                       class="btn btn-secondary" style="padding:6px 14px; font-size:0.82rem;">NEXT ▶</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- RAW LOG FILE VIEWER (last 200 lines) -->
        <div class="atm-panel">
            <div class="atm-panel-header" style="color:var(--amber);">RAW LOG FILE (LAST 200 LINES)</div>
            <div class="atm-panel-body">
                <pre style="font-size:0.72rem; color:var(--text-dim); overflow-x:auto; max-height:400px; overflow-y:auto; line-height:1.5;">
<?php
$log_file = '/var/www/private/logs/activity.log';
if (file_exists($log_file)) {
    // Read only the last 200 lines — never load the entire file into memory
    $lines = [];
    $fp    = new SplFileObject($log_file, 'r');
    $fp->seek(PHP_INT_MAX); // Seek to end to count lines
    $total_lines = $fp->key();
    $start_line  = max(0, $total_lines - 200);
    $fp->seek($start_line);
    while (!$fp->eof()) {
        $line = $fp->fgets();
        if ($line !== false && $line !== '') {
            // htmlspecialchars on every line — log contains user-controlled data
            echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        }
    }
    unset($fp);
} else {
    echo 'Log file not found or empty.';
}
?>
                </pre>
            </div>
        </div>

    </main>

    <footer class="atm-footer" style="border-top-color:var(--amber-dim);">
        <span>SECUREPAY ADMIN TERMINAL</span>
        <span>ALL ACTIONS LOGGED &nbsp;|&nbsp; UNAUTHORISED ACCESS PROHIBITED</span>
    </footer>

</div>
<script src="/assets/script.js"></script>
</body>
</html>