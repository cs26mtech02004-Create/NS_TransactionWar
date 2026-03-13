<?php
require 'security_file/config.php';
require 'security_file/auth.php';
require 'security_file/db.php';
require 'security_file/csrf_token.php';

generateCSRFToken();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
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
        <li><a href="edit_profile.php" class="nav-active">Profile</a></li>
    </ul>
</nav>

<div class="page-wrap">

    <div class="page-eyebrow">Account</div>
    <h1 class="page-title"><em>Edit</em> Profile</h1>

    <div class="card">
        <div class="card-header-label">Personal Details</div>

        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-grid">

                <div class="form-field">
                    <label>Username</label>
                    <div class="input-wrap">
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                    <span class="field-hint">Username cannot be changed.</span>
                </div>

                <div class="form-field">
                    <label>Full Name</label>
                    <div class="input-wrap">
                        <input type="text" name="fullname" placeholder="Your full name"
                               value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>">
                        <div class="input-line"></div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Email Address</label>
                    <div class="input-wrap">
                        <input type="email" name="email" placeholder="you@example.com"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        <div class="input-line"></div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Biography</label>
                    <div class="input-wrap">
                        <textarea name="bio" rows="6" placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <div class="input-line"></div>
                    </div>
                    <span class="field-hint">Up to 50,000 characters supported.</span>
                </div>

                <div class="form-field">
                    <label>Profile Image</label>
                    <label class="file-label">
                        <span class="file-icon">↑</span>
                        <span id="file-text">Choose image (JPEG, PNG, GIF — max 2MB)</span>
                        <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp"
                               onchange="document.getElementById('file-text').textContent = this.files[0]?.name || 'Choose image'">
                    </label>
                    <?php if ($user['profile_image']): ?>
                        <div style="margin-top:12px; display:flex; align-items:center; gap:12px;">
                            <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                 style="width:60px;height:60px;border-radius:50%;border:1px solid var(--gold-dim);object-fit:cover;">
                            <span class="field-hint">Current photo</span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <div class="divider"></div>

            <div class="action-row">
                <button type="submit" class="btn"><span>Save Changes</span></button>
                <a href="dashboard.php" class="btn btn-ghost"><span>Cancel</span></a>
            </div>

        </form>
    </div>

</div>
</body>
</html>
