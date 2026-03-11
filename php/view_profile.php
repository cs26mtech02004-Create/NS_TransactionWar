<?php
// TASK 2 — View another user's public profile
session_start();
require_once __DIR__ . '/db.php';
auth_required();
logActivity('view_profile.php', $_SESSION['username']);

$pdo    = getDB();
$uid    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($uid <= 0) { header('Location: /search.php'); exit(); }

// Redirect to own profile page if viewing self
if ($uid === (int)$_SESSION['user_id']) { header('Location: /profile.php'); exit(); }

$stmt = $pdo->prepare("SELECT id, username, bio, profile_image, created_at FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) { $_SESSION['flash_error'] = 'User not found.'; header('Location: /search.php'); exit(); }

$pageTitle = h($user['username']) . "'s Profile";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>👤 <?= h($user['username']) ?>'s Profile</h1>
</div>

<div class="profile-layout">
  <div class="card profile-avatar-card">
    <?php if ($user['profile_image']): ?>
      <img src="/uploads/profiles/<?= h($user['profile_image']) ?>" class="avatar-img" alt="Profile">
    <?php else: ?>
      <div class="avatar-placeholder"><?= strtoupper(substr($user['username'],0,1)) ?></div>
    <?php endif; ?>
    <div class="profile-username">@<?= h($user['username']) ?></div>
    <div class="profile-joined text-muted">Joined <?= date('d M Y', strtotime($user['created_at'])) ?></div>
    <a href="/transfer.php?to=<?= $user['id'] ?>&name=<?= urlencode($user['username']) ?>"
       class="btn btn-primary" style="margin-top:16px">💸 Send Money</a>
  </div>

  <div class="card">
    <div class="card-title">About</div>
    <?php if (!empty($user['bio'])): ?>
      <p style="white-space:pre-wrap;line-height:1.8"><?= h($user['bio']) ?></p>
    <?php else: ?>
      <p class="text-muted">This user hasn't written a bio yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
