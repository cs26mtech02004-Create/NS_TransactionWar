<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';

$results     = [];
$search_term = '';
$searched    = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['q'])) {

    $search_term = trim($_GET['q'] ?? '');
    $searched    = true;

    if (strlen($search_term) >= 1) {

        // Username-only LIKE search.
        // ID-based search removed — internal IDs are server-side only.
        // Users identify each other by username.
        // Own user IS included in results (removed the AND id != ? exclusion).
        // $safe_term  = addcslashes($search_term, '%_');
        $like_param = '%' . $search_term . '%';

        $stmt = $pdo->prepare(
            'SELECT public_id, username, full_name, profile_image
             FROM users
             WHERE username LIKE ?
             ORDER BY username ASC
             LIMIT 20'
        );
        $stmt->execute([$like_param]);
        $results = $stmt->fetchAll();
    }
}

$page_title = 'Search Users';
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">FIND USERS</div>
    <div class="atm-panel-body">
        <form method="GET" action="/search.php" autocomplete="off">
            <div style="display:flex; gap:10px; align-items:flex-end;">
                <div class="atm-form-group" style="flex:1; margin-bottom:0;">
                    <label for="q">SEARCH BY USERNAME</label>
                    <input type="text" id="q" name="q" maxlength="50"
                           placeholder="Type a username..."
                           value="<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="margin-bottom:0;">
                        SEARCH
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($searched): ?>
    <div class="atm-panel">
        <div class="atm-panel-header">
            RESULTS
            <?php if (!empty($results)): ?>
                <span style="font-size:0.78rem; color:var(--text-dim); margin-left:8px;">
                    (<?= count($results) ?>)
                </span>
            <?php endif; ?>
        </div>
        <div class="atm-panel-body" style="padding:0;">

            <?php if (empty($results)): ?>
                <p class="text-muted" style="padding:20px; font-size:0.85rem;">
                    No users found for "<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>".
                </p>
            <?php else: ?>
                <table class="atm-table">
                    <thead>
                        <tr>
                            <th>USER</th>
                            <th>FULL NAME</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $user):
                            $is_self = ($user['username'] === $_SESSION['username']);
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <?php if ($user['profile_image']): ?>
                                            <img src="/serve_image.php?f=<?= urlencode($user['profile_image']) ?>"
                                                 style="width:30px;height:30px;object-fit:cover;border:1px solid var(--border-bright);"
                                                 alt="">
                                        <?php else: ?>
                                            <div style="width:30px;height:30px;background:var(--input-bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:0.75rem;">?</div>
                                        <?php endif; ?>
                                        <div>
                                            <div><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($is_self): ?>
                                                    <span style="font-size:0.72rem; color:var(--amber-dim);"> (you)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="muted">
                                    <?= htmlspecialchars($user['full_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <a href="/profile_view.php?id=<?= urlencode($user['public_id']) ?>"
                                       class="btn btn-secondary"
                                       style="padding:4px 10px; font-size:0.78rem;">
                                        VIEW
                                    </a>
                                    <?php if (!$is_self): ?>
                                    &nbsp;
                                    <a href="/transfer.php?to=<?= urlencode($user['username']) ?>"
                                       class="btn btn-primary"
                                       style="padding:4px 10px; font-size:0.78rem;">
                                        SEND
                                    </a>
                                    <?php endif; ?>
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