<?php
require 'security_file/config.php';
require 'security_file/auth.php';
require 'security_file/db.php';
require 'security_file/csrf_token.php';

generateCSRFToken();

$profileId = (int)($_GET['id'] ?? 0);
if ($profileId <= 0) {
    header("Location: users.php");
    exit();
}

$stmt = $pdo->prepare("SELECT id, username, fullname, bio, profile_image FROM users WHERE id = :id");
$stmt->execute(['id' => $profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    header("Location: users.php");
    exit();
}

$isOwnProfile = ($profile['id'] === (int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile['username']); ?>'s Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/app.css">
</head>
<body>

<nav class="app-nav">
    <a href="dashboard.php" class="nav-logo">
        <div class="logo-mark"></div>
        <span class="logo-name">Banking System</span>
    </a>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="users.php">Users</a></li>
        <li><a href="edit_profile.php">Profile</a></li>
    </ul>
</nav>

<div class="page-wrap">

    <div class="page-eyebrow">Member Profile</div>

    <div class="card profile-card">

        <?php if ($profile['profile_image']): ?>
            <img src="uploads/<?php echo htmlspecialchars($profile['profile_image']); ?>"
                 class="profile-avatar" style="width:120px;height:120px;margin:0 auto 24px;" alt="">
        <?php else: ?>
            <div class="profile-avatar-placeholder"
                 style="width:120px;height:120px;margin:0 auto 24px;font-size:44px;">
                <?php echo strtoupper(substr($profile['username'], 0, 1)); ?>
            </div>
        <?php endif; ?>

        <div class="profile-username"><?php echo htmlspecialchars($profile['username']); ?></div>

        <?php if ($profile['fullname']): ?>
            <div class="profile-email"><?php echo htmlspecialchars($profile['fullname']); ?></div>
        <?php endif; ?>

        <?php if ($profile['bio']): ?>
            <div class="bio-box" style="text-align:left; margin-top:24px;">
                <?php echo htmlspecialchars($profile['bio']); ?>
            </div>
        <?php else: ?>
            <p style="color:var(--muted);font-size:11px;margin-top:16px;font-style:italic;">No bio yet.</p>
        <?php endif; ?>

        <div class="divider"></div>

        <div class="action-row" style="justify-content:center;">
            <?php if ($isOwnProfile): ?>
                <a href="edit_profile.php" class="btn"><span>Edit My Profile</span></a>
            <?php endif; ?>
            <a href="users.php" class="btn btn-ghost"><span>← Back to Users</span></a>
        </div>

    </div>

</div>
</body>
</html>
