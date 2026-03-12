<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';

// ?id= is now the PUBLIC_ID (12-char hex) — not the internal integer id
$public_id = trim($_GET['id'] ?? '');

// Validate public_id format: exactly 12 hex chars
if (!preg_match('/^[a-f0-9]{12}$/', $public_id)) {
    header('Location: /search.php');
    exit();
}

// Fetch user by public_id
// Only select public-safe fields — no email, no balance, no password_hash
$stmt = $pdo->prepare(
    'SELECT public_id, username, full_name, bio, profile_image, created_at
     FROM users WHERE public_id = ? LIMIT 1'
);
$stmt->execute([$public_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: /search.php');
    exit();
}

// If viewing own profile, redirect to profile.php (edit view)
$stmt2 = $pdo->prepare('SELECT public_id FROM users WHERE id = ?');
$stmt2->execute([$_SESSION['user_id']]);
$self = $stmt2->fetch();
if ($self && $self['public_id'] === $public_id) {
    header('Location: /profile.php');
    exit();
}

$page_title = htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') . "'s Profile";
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">USER PROFILE</div>
    <div class="atm-panel-body">

        <div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">

            <!-- Avatar -->
            <div>
                <?php if ($profile['profile_image']): ?>
                    <img src="/serve_image.php?f=<?= urlencode($profile['profile_image']) ?>"
                         class="atm-avatar" alt="Profile image">
                <?php else: ?>
                    <div class="atm-avatar-placeholder">?</div>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div style="flex:1; min-width:180px;">
                <div style="font-size:1.4rem; color:var(--green); margin-bottom:6px;">
                    <?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?>
                </div>

                <?php if ($profile['full_name']): ?>
                    <div class="text-muted" style="font-size:0.85rem; margin-bottom:4px;">
                        <?= htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="text-muted" style="font-size:0.72rem; margin-top:8px;">
                    Member since <?= htmlspecialchars(date('M Y', strtotime($profile['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>

        <?php if ($profile['bio']): ?>
            <hr style="border-color:var(--border); margin:16px 0;">
            <div style="font-size:0.85rem; color:var(--text-main); line-height:1.7;">
                <?= nl2br(htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/transfer.php?to=<?= urlencode($profile['username']) ?>"
       class="btn btn-primary">
        SEND MONEY
    </a>
    <a href="/search.php" class="btn btn-secondary">BACK TO SEARCH</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>