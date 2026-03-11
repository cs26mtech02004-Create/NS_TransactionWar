<?php
/**
 * FILE: profile.php
 * PURPOSE: Let the logged-in user view and edit their own profile.
 *
 * WHAT CAN BE EDITED:
 *   - Full name
 *   - Email address
 *   - Biography (long text, up to 5000 chars)
 *   - Profile image (secure upload via upload_handler.php)
 *
 * WHAT CANNOT BE EDITED:
 *   - Username (permanent, as per spec)
 *   - Balance (only changes via transfers)
 *
 * SECURITY MEASURES:
 *   1. auth_guard — only logged-in users
 *   2. CSRF token on form
 *   3. All edits scoped to $_SESSION['user_id'] — IDOR impossible
 *      (the WHERE clause always uses the session ID, never a form field)
 *   4. Email uniqueness re-checked before update
 *   5. All output escaped with htmlspecialchars()
 *   6. Image upload delegated to upload_handler.php (all checks there)
 */

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/upload_handler.php';

$errors  = [];
$success = '';

// Fetch current user data fresh from DB
$stmt = $pdo->prepare(
    'SELECT id, username, email, full_name, bio, profile_image, balance
     FROM users WHERE id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    destroy_session();
    header('Location: /login.php');
    exit();
}

// ── POST: HANDLE PROFILE UPDATE ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_token();

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $bio       = trim($_POST['bio']       ?? '');

    // ── VALIDATE FULL NAME ────────────────────────────────────
    if (strlen($full_name) > 100) {
        $errors[] = 'Full name must be 100 characters or fewer.';
    }

    // ── VALIDATE EMAIL ────────────────────────────────────────
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 254) {
        $errors[] = 'Email address is too long.';
    }

    // ── VALIDATE BIO ──────────────────────────────────────────
    if (strlen($bio) > 5000) {
        $errors[] = 'Biography must be 5000 characters or fewer.';
    }

    // ── CHECK EMAIL UNIQUENESS ────────────────────────────────
    // Make sure no OTHER user has this email.
    // The WHERE excludes the current user's own record.
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1'
        );
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'That email address is already in use by another account.';
        }
    }

    // ── HANDLE IMAGE UPLOAD (if a file was submitted) ─────────
    $new_image_filename = $user['profile_image']; // Keep existing by default

    if (!empty($_FILES['profile_image']['name'])) {
        $upload_result = handle_profile_upload(
            $_FILES['profile_image'],
            $user['profile_image']  // Pass old filename so it gets deleted
        );
        if ($upload_result['success']) {
            $new_image_filename = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }

    // ── SAVE TO DATABASE ──────────────────────────────────────
    if (empty($errors)) {
        // IDOR PREVENTION: The WHERE clause uses $_SESSION['user_id']
        // exclusively. There is no user-supplied ID in this query.
        // No matter what an attacker sends in the form, they can only
        // ever update their own row.
        $stmt = $pdo->prepare(
            'UPDATE users
             SET full_name = ?, email = ?, bio = ?, profile_image = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $full_name ?: null,           // Store NULL if empty string
            $email,
            $bio ?: null,
            $new_image_filename,
            $_SESSION['user_id']          // Always from session — never from form
        ]);

        // Refresh local $user array so the page shows updated values
        $user['full_name']     = $full_name;
        $user['email']         = $email;
        $user['bio']           = $bio;
        $user['profile_image'] = $new_image_filename;

        $success = 'Profile updated successfully.';
    }
}

$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="atm-alert atm-alert-error">
        ⚠ <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endforeach; ?>

<?php if ($success): ?>
    <div class="atm-alert atm-alert-success">
        ✓ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="atm-panel mb-3">
    <div class="atm-panel-header">ACCOUNT PROFILE</div>
    <div class="atm-panel-body">

        <!-- CURRENT PROFILE SUMMARY (read-only top section) -->
        <div style="display:flex; gap:20px; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap;">
            <div>
                <?php if ($user['profile_image']): ?>
                    <!--
                        Image is served through serve_image.php proxy.
                        The browser never gets a direct path to the file.
                        ?f= parameter is the server-generated hex filename.
                    -->
                    <img
                        src="/serve_image.php?f=<?= urlencode($user['profile_image']) ?>"
                        alt="Profile image"
                        class="atm-avatar"
                    >
                <?php else: ?>
                    <div class="atm-avatar-placeholder">?</div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:1.4rem; color:var(--amber); font-family:var(--font-display)">
                    <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="text-muted" style="font-size:0.78rem">
                    USER ID: <?= htmlspecialchars((string)$user['id'], ENT_QUOTES, 'UTF-8') ?>
                    &nbsp;|&nbsp;
                    BALANCE: Rs. <?= htmlspecialchars(number_format((float)$user['balance'], 2), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    USERNAME CANNOT BE CHANGED
                </div>
            </div>
        </div>

        <hr class="atm-divider">

        <!-- EDIT FORM -->
        <form method="POST" action="/profile.php"
              enctype="multipart/form-data"
              autocomplete="off">

            <?= csrf_input() ?>

            <!-- enctype="multipart/form-data" is required for file uploads.
                 Without it, $_FILES will be empty on the server. -->

            <div class="atm-form-group">
                <label for="full_name">FULL NAME</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="100"
                    placeholder="Your full name (optional)"
                    value="<?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="atm-form-group">
                <label for="email">EMAIL ADDRESS</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="254"
                    value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="atm-form-group">
                <label for="bio">
                    BIOGRAPHY
                    <span id="bio-counter" style="float:right; font-size:0.75rem; color:var(--text-dim)"></span>
                </label>
                <textarea
                    id="bio"
                    name="bio"
                    maxlength="5000"
                    placeholder="Tell others about yourself..."
                    rows="6"
                ><?= htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <!--
                    IMPORTANT: htmlspecialchars() on the textarea content.
                    If a user saved <script>alert(1)</script> as their bio,
                    without escaping it would execute when the form loads.
                    Escaping turns it into harmless text inside the textarea.
                -->
            </div>

            <div class="atm-form-group">
                <label for="profile_image">PROFILE IMAGE</label>
                <input
                    type="file"
                    id="profile_image"
                    name="profile_image"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    style="color:var(--text-main); font-family:var(--font-mono)"
                >
                <div class="text-muted mt-1" style="font-size:0.75rem">
                    JPG, PNG, GIF or WebP. Max 2MB. Leave blank to keep current image.
                </div>
            </div>

            <div class="mt-2" style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary">▶ SAVE CHANGES</button>
                <a href="/dashboard.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>