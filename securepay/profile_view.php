<?php
/**
 * FILE: profile_view.php
 * PURPOSE: Display another user's profile (read-only).
 *
 * URL: /profile_view.php?id=5
 *
 * SECURITY MEASURES:
 *   1. auth_guard — must be logged in to view profiles
 *   2. User ID from URL validated as a positive integer
 *   3. User fetched by ID using prepared statement
 *   4. All output escaped with htmlspecialchars()
 *   5. Sensitive fields (password_hash, email) NOT fetched or displayed
 *   6. Viewing your own profile redirects to profile.php (edit page)
 *
 * NOTE ON IDOR:
 *   Viewing other profiles is INTENTIONAL here (the spec says users can
 *   see other profiles). IDOR would only be a problem if viewing granted
 *   edit access — which it does not. The SELECT only fetches public fields.
 */

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';

// ── VALIDATE USER ID FROM URL ─────────────────────────────────
// filter_var with FILTER_VALIDATE_INT ensures the value is a real integer.
// Without this, someone could pass ?id=1 UNION SELECT ... (SQL injection).
// Even though we use prepared statements (which already block injection),
// validating the type is a good additional layer.
$profile_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$profile_id) {
    // Invalid or missing ID — redirect home
    header('Location: /dashboard.php');
    exit();
}

// Viewing your own profile? Redirect to the edit page instead
if ((int)$profile_id === (int)$_SESSION['user_id']) {
    header('Location: /profile.php');
    exit();
}

// ── FETCH PUBLIC PROFILE DATA ────────────────────────────────
// We deliberately do NOT select: password_hash, email (private), balance (private)
// Only fetch fields that should be publicly visible.
$stmt = $pdo->prepare(
    'SELECT id, username, full_name, bio, profile_image, created_at
     FROM users
     WHERE id = ?'
);
$stmt->execute([$profile_id]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    $page_title = 'User Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="atm-alert atm-alert-error">⚠ User not found.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$page_title = 'Profile: ' . $profile['username'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">USER PROFILE</div>
    <div class="atm-panel-body">

        <div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <?php if ($profile['profile_image']): ?>
                    <img
                        src="/serve_image.php?f=<?= urlencode($profile['profile_image']) ?>"
                        alt="Profile image"
                        class="atm-avatar"
                    >
                <?php else: ?>
                    <div class="atm-avatar-placeholder">?</div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:1.6rem; color:var(--amber); font-family:var(--font-display)">
                    <?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?>
                </div>

                <?php if ($profile['full_name']): ?>
                    <div style="color:var(--text-main); font-size:0.9rem;">
                        <?= htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="text-muted mt-1" style="font-size:0.75rem">
                    USER ID: <?= htmlspecialchars((string)$profile['id'], ENT_QUOTES, 'UTF-8') ?>
                    &nbsp;|&nbsp;
                    MEMBER SINCE: <?= htmlspecialchars(
                        date('M Y', strtotime($profile['created_at'])),
                        ENT_QUOTES, 'UTF-8'
                    ) ?>
                </div>
            </div>
        </div>

        <?php if ($profile['bio']): ?>
            <hr class="atm-divider">
            <div>
                <div class="text-muted mb-1" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.1em">
                    BIOGRAPHY
                </div>
                <!--
                    nl2br() converts newlines to <br> tags so the bio
                    displays line breaks. htmlspecialchars() runs FIRST —
                    the output of htmlspecialchars() is safe HTML text,
                    then nl2br() adds <br> tags to that safe string.
                    Order matters: never nl2br() before htmlspecialchars()
                    or the <br> tags themselves get escaped.
                -->
                <div style="line-height:1.8; font-size:0.9rem;">
                    <?= nl2br(htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
        <?php endif; ?>

        <hr class="atm-divider">

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="/transfer.php?to=<?= urlencode((string)$profile['id']) ?>"
               class="btn btn-primary">
                ▶ SEND MONEY
            </a>
            <a href="/dashboard.php" class="btn btn-secondary">BACK</a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>