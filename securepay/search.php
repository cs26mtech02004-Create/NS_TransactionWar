<?php
/**
 * FILE: search.php
 * PURPOSE: Search for other users by username or user ID.
 *
 * SECURITY MEASURES:
 *   1. auth_guard — logged in only
 *   2. PDO prepared statements on ALL search queries
 *   3. LIKE wildcard characters (%, _) in user input are escaped
 *      so the user cannot craft wildcard patterns that dump all users
 *   4. Results limited to 20 rows (prevents full table dumps)
 *   5. Sensitive fields (password_hash, email, balance) never selected
 *   6. All output escaped with htmlspecialchars()
 *
 * NOTE ON LIKE INJECTION:
 *   PDO prepared statements prevent SQL injection (attacker cannot break
 *   out of the query structure). But with LIKE, an attacker can still
 *   abuse the wildcard characters % and _ to do things like:
 *     Search: "%"  → matches ALL usernames (full table scan)
 *     Search: "_a" → matches any username where 2nd char is 'a'
 *   We escape these characters so they are treated as literals:
 *     % → \%    _ → \_
 *   This means searching for "%" finds users literally named "%", not all users.
 */

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';

$results     = [];
$search_term = '';
$searched    = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['q'])) {

    $search_term = trim($_GET['q'] ?? '');
    $searched    = true;

    if (strlen($search_term) < 1) {
        // Nothing to search
        $results = [];

    } elseif (ctype_digit($search_term)) {
        // ── SEARCH BY USER ID ─────────────────────────────────
        // If the input is all digits, treat it as an ID lookup (exact match)
        $stmt = $pdo->prepare(
            'SELECT id, username, full_name, profile_image
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([(int)$search_term]);
        $results = $stmt->fetchAll();

    } else {
        // ── SEARCH BY USERNAME (LIKE) ─────────────────────────
        // Escape LIKE special characters in user input before binding.
        // addcslashes($str, '%_') adds a backslash before % and _.
        // In MySQL, the backslash acts as an escape character in LIKE.
        $safe_term = addcslashes($search_term, '%_');

        // Wrap with % for "contains" search
        $like_param = '%' . $safe_term . '%';

        // Exclude the searching user from results (no point showing yourself)
        // Limit to 20 results to prevent large data dumps
        $stmt = $pdo->prepare(
            'SELECT id, username, full_name, profile_image
             FROM users
             WHERE username LIKE ?
               AND id != ?
             ORDER BY username ASC
             LIMIT 20'
        );
        $stmt->execute([$like_param, $_SESSION['user_id']]);
        $results = $stmt->fetchAll();
    }
}

$page_title = 'Search Users';
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">USER SEARCH</div>
    <div class="atm-panel-body">

        <form method="GET" action="/search.php" autocomplete="off">
            <!--
                Search uses GET (not POST) intentionally:
                - Results are bookmarkable/shareable
                - The back button works correctly
                - No CSRF needed (GET requests do not change server state)
                Search results are read-only — no money moves, no data changes.
            -->
            <div style="display:flex; gap:10px; align-items:flex-end;">
                <div class="atm-form-group" style="flex:1; margin-bottom:0;">
                    <label for="q">SEARCH BY USERNAME OR USER ID</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        maxlength="50"
                        placeholder="Enter username or numeric ID..."
                        value="<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="margin-bottom:0;">
                        ▶ SEARCH
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

<!-- RESULTS -->
<?php if ($searched): ?>
    <div class="atm-panel">
        <div class="atm-panel-header">
            SEARCH RESULTS
            <?php if (!empty($results)): ?>
                <span style="font-size:0.8rem; color:var(--text-dim); margin-left:10px;">
                    (<?= count($results) ?> found)
                </span>
            <?php endif; ?>
        </div>
        <div class="atm-panel-body" style="padding:0;">

            <?php if (empty($results)): ?>
                <p class="text-muted" style="padding:20px; font-size:0.85rem;">
                    No users found matching
                    "<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>".
                </p>

            <?php else: ?>
                <table class="atm-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>USERNAME</th>
                            <th>FULL NAME</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $user): ?>
                            <tr>
                                <td class="muted">
                                    #<?= htmlspecialchars((string)$user['id'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <?php if ($user['profile_image']): ?>
                                            <img
                                                src="/serve_image.php?f=<?= urlencode($user['profile_image']) ?>"
                                                style="width:28px;height:28px;object-fit:cover;border:1px solid var(--border-bright);"
                                                alt=""
                                            >
                                        <?php else: ?>
                                            <div style="width:28px;height:28px;background:var(--input-bg);border:1px solid var(--border-bright);display:flex;align-items:center;justify-content:center;font-size:0.7rem;color:var(--text-dim);">?</div>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                </td>
                                <td class="muted">
                                    <?= htmlspecialchars($user['full_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <a href="/profile_view.php?id=<?= (int)$user['id'] ?>"
                                       class="btn btn-secondary"
                                       style="padding:4px 12px; font-size:0.78rem;">
                                        VIEW
                                    </a>
                                    &nbsp;
                                    <a href="/transfer.php?to=<?= (int)$user['id'] ?>"
                                       class="btn btn-primary"
                                       style="padding:4px 12px; font-size:0.78rem;">
                                        SEND
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>