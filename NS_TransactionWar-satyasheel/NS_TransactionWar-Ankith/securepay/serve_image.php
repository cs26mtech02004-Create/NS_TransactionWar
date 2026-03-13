<?php
/**
 * FILE: serve_image.php
 * PURPOSE: Serve profile images from private storage through a secure PHP proxy.
 *
 * WHY A PROXY?
 *   Images are stored in /var/www/private/uploads/ which Apache cannot serve.
 *   To display an image, the browser requests:
 *     /serve_image.php?f=abc123def456.jpg
 *   This script reads the file and streams it to the browser.
 *
 * SECURITY CHECKS IN THIS FILE:
 *   1. User must be logged in (images are not public)
 *   2. Filename validated: hex characters + extension only (no path traversal)
 *   3. basename() strips any path components from the filename
 *   4. File existence verified before reading
 *   5. Correct Content-Type header sent based on real MIME type
 *   6. No PHP execution possible (files are images, not scripts)
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/security_headers.php';

send_security_headers();

// ── AUTH CHECK ────────────────────────────────────────────────
// Profile images are only visible to logged-in users.
// Unauthenticated users get a 403.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

// ── VALIDATE THE FILENAME PARAMETER ──────────────────────────
$filename = $_GET['f'] ?? '';

// Allow ONLY: 32 hex characters, a dot, and 2-4 letter extension.
// This regex rejects:
//   - Path traversal:  ../../../etc/passwd
//   - Null bytes:      file.php\0.jpg
//   - Double ext:      shell.php.jpg (would need exactly 32 hex chars before dot)
//   - Absolute paths:  /etc/passwd
//   - Any other trick
if (!preg_match('/^[a-f0-9]{32}\.[a-z]{2,4}$/', $filename)) {
    http_response_code(400);
    exit('Invalid filename.');
}

// basename() as an additional safety net — strips any directory components
// that somehow slipped through the regex (defence in depth).
$safe_filename = basename($filename);
$filepath      = '/var/www/private/uploads/' . $safe_filename;

// ── FILE EXISTS CHECK ─────────────────────────────────────────
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('Image not found.');
}

// ── DETERMINE CONTENT TYPE ───────────────────────────────────
// Read the actual MIME type from the file bytes (not the extension).
// This ensures we send the correct Content-Type header.
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($filepath);

// Whitelist the MIME type — only serve actual images
$allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_mime, true)) {
    http_response_code(403);
    exit('Invalid file type.');
}

// ── SERVE THE FILE ────────────────────────────────────────────
// Tell the browser this is an image (not HTML, not a script).
header('Content-Type: ' . $mime_type);

// Cache the image for 1 hour (reduces server load on repeated page views).
// Images change rarely — caching is safe here.
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($filepath));

// X-Content-Type-Options: nosniff — browser must respect the Content-Type
// we sent and not try to "sniff" the type itself.
header('X-Content-Type-Options: nosniff');

// readfile() streams the file directly to the output buffer efficiently.
// It does not load the entire file into PHP memory first.
readfile($filepath);
exit();