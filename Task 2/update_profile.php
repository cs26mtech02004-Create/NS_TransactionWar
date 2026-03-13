<?php
require 'security_file/config.php';
require 'security_file/auth.php';
require 'security_file/db.php';
require 'security_file/csrf_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// CSRF check
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Invalid CSRF token. Please refresh and try again.");
}

$id       = $_SESSION['user_id'];
$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email']    ?? '');
$bio      = trim($_POST['bio']      ?? '');

// Validate email
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: edit_profile.php?error=invalid_email");
    exit();
}

// Check email not taken by another user
if (!empty($email)) {
    $check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $check->execute(['email' => $email, 'id' => $id]);
    if ($check->fetch()) {
        header("Location: edit_profile.php?error=email_taken");
        exit();
    }
}

// Handle image upload
$imageFilename = null;
if (!empty($_FILES['profile_image']['name'])) {

    $file     = $_FILES['profile_image'];
    $maxSize  = 2 * 1024 * 1024; // 2MB

    // Check file size
    if ($file['size'] > $maxSize) {
        header("Location: edit_profile.php?error=file_too_large");
        exit();
    }

    // Check real MIME type (not browser-supplied)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($mime, $allowedMime) || !in_array($ext, $allowedExt)) {
        header("Location: edit_profile.php?error=invalid_file");
        exit();
    }

    // Confirm it is a real image
    if (!@getimagesize($file['tmp_name'])) {
        header("Location: edit_profile.php?error=invalid_file");
        exit();
    }

    $imageFilename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination   = 'uploads/' . $imageFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        header("Location: edit_profile.php?error=upload_failed");
        exit();
    }
}

// Update DB
if ($imageFilename) {
    $stmt = $pdo->prepare(
        "UPDATE users SET fullname = :fullname, email = :email, bio = :bio, profile_image = :img WHERE id = :id"
    );
    $stmt->execute([
        'fullname' => $fullname,
        'email'    => $email,
        'bio'      => $bio,
        'img'      => $imageFilename,
        'id'       => $id,
    ]);
} else {
    $stmt = $pdo->prepare(
        "UPDATE users SET fullname = :fullname, email = :email, bio = :bio WHERE id = :id"
    );
    $stmt->execute([
        'fullname' => $fullname,
        'email'    => $email,
        'bio'      => $bio,
        'id'       => $id,
    ]);
}

header("Location: dashboard.php?success=profile_updated");
exit();
?>
