<?php
require 'security_file/config.php';
require 'security_file/auth.php';
require 'security_file/db.php';
require 'security_file/csrf_token.php';

generateCSRFToken();

$stmt = $pdo->query("SELECT id, username, fullname, profile_image FROM users ORDER BY username ASC");
$allUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users</title>
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
        <li><a href="users.php" class="nav-active">Users</a></li>
        <li><a href="edit_profile.php">Profile</a></li>
    </ul>
</nav>

<div class="page-wrap">

    <div class="page-eyebrow">Directory</div>
    <h1 class="page-title">All <em>Users</em></h1>

    <div class="card">
        <div class="card-header-label">Registered Members</div>
        <table class="users-table">
            <thead>
                <tr>
                    <th>Avatar</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Profile</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allUsers as $u): ?>
                <tr>
                    <td>
                        <?php if ($u['profile_image']): ?>
                            <img src="uploads/<?php echo htmlspecialchars($u['profile_image']); ?>" alt="">
                        <?php else: ?>
                            <div class="table-avatar-placeholder"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['fullname'] ?? '—'); ?></td>
                    <td>
                        <a href="view_profile.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-ghost" style="padding:8px 16px;font-size:9px;">
                            <span>View →</span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
