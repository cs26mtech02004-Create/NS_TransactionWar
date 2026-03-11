<?php
// TASK 2 — Profile Management (own profile)
session_start();
require_once __DIR__ . '/db.php';
auth_required();
logActivity('profile.php', $_SESSION['username']);

$pdo    = getDB();
$uid    = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

// ── POST: update profile ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $bio   = trim($_POST['bio']   ?? '');
    $newpass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';

    // Check email not taken by another user
    $s = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $s->execute([$email, $uid]);
    if ($s->fetch()) $errors[] = 'Email already in use by another account.';

    // Password change (optional)
    if ($newpass !== '') {
        if (strlen($newpass) < 8)
            $errors[] = 'New password must be at least 8 characters.';
        elseif ($newpass !== $confirm)
            $errors[] = 'Passwords do not match.';
    }

    // Profile image upload
    $imagePath = $user['profile_image'];
    if (!empty($_FILES['profile_image']['name'])) {
        $file     = $_FILES['profile_image'];
        $allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
        $maxSize  = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowed))
            $errors[] = 'Profile image must be JPG, PNG, GIF or WEBP.';
        elseif ($file['size'] > $maxSize)
            $errors[] = 'Profile image must be under 2MB.';
        else {
            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename  = 'profile_' . $uid . '_' . time() . '.' . strtolower($ext);
            $uploadDir = __DIR__ . '/uploads/profiles/';
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                // Delete old image
                if ($imagePath && file_exists($uploadDir . $imagePath))
                    unlink($uploadDir . $imagePath);
                $imagePath = $filename;
            } else {
                $errors[] = 'Failed to upload image. Try again.';
            }
        }
    }

    if (empty($errors)) {
        $hash = !empty($newpass) ? password_hash($newpass, PASSWORD_BCRYPT, ['cost'=>12]) : $user['password_hash'];
        $pdo->prepare("UPDATE users SET email=?, bio=?, password_hash=?, profile_image=? WHERE id=?")
            ->execute([$email, $bio, $hash, $imagePath, $uid]);
        $_SESSION['flash_success'] = 'Profile updated successfully!';
        header('Location: /profile.php'); exit();
    }

    // Re-fetch after failed update
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>👤 My Profile</h1>
  <p class="subtitle">Update your personal details</p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="profile-layout">
  <!-- Left: Avatar -->
  <div class="card profile-avatar-card">
    <?php if ($user['profile_image']): ?>
      <img src="/uploads/profiles/<?= h($user['profile_image']) ?>" class="avatar-img" alt="Profile">
    <?php else: ?>
      <div class="avatar-placeholder"><?= strtoupper(substr($user['username'],0,1)) ?></div>
    <?php endif; ?>
    <div class="profile-username">@<?= h($user['username']) ?></div>
    <div class="profile-balance">Balance: <strong>₹<?= number_format($user['balance'],2) ?></strong></div>
    <div class="profile-joined text-muted">Joined <?= date('d M Y', strtotime($user['created_at'])) ?></div>
  </div>

  <!-- Right: Edit form -->
  <div class="card">
    <form method="POST" action="/profile.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label>Username <span class="optional">(cannot be changed)</span></label>
        <input type="text" value="<?= h($user['username']) ?>" class="input-field" disabled>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= h($user['email']) ?>" class="input-field" required>
      </div>
      <div class="form-group">
        <label>Biography</label>
        <textarea name="bio" class="input-field input-textarea" maxlength="2000"
                  placeholder="Tell others about yourself…"><?= h($user['bio'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Profile Image <span class="optional">(JPG/PNG/GIF/WEBP, max 2MB)</span></label>
        <input type="file" name="profile_image" accept="image/*" class="input-field">
      </div>
      <hr class="divider">
      <div class="form-group">
        <label>New Password <span class="optional">(leave blank to keep current)</span></label>
        <input type="password" name="new_password" class="input-field" minlength="8" placeholder="Min. 8 characters">
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" class="input-field" placeholder="Repeat new password">
      </div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
