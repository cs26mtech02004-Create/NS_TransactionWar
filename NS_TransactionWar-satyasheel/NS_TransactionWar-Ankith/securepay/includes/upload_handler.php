<?php
/**
 * FILE: includes/upload_handler.php
 * PURPOSE: Handle profile image uploads securely.
 *
 * SECURITY MEASURES:
 *   1. MIME type checked via finfo (reads actual file bytes, not extension)
 *   2. File extension whitelisted independently (double check)
 *   3. Server-generated random filename — user filename completely discarded
 *   4. Files stored in /var/www/private/uploads — OUTSIDE Apache webroot
 *   5. File size hard-limited to 2MB
 *   6. Old image deleted when user uploads a new one
 *
 * HOW TO USE:
 *   require_once __DIR__ . '/upload_handler.php';
 *   $result = handle_profile_upload($_FILES['profile_image'], $old_filename);
 *   if ($result['success']) { $new_filename = $result['filename']; }
 *   else { $error = $result['error']; }
 */

// Storage path is OUTSIDE webroot — Apache cannot serve files from here.
// Even if someone uploaded a PHP file, the server would never execute it
// because it's not inside /var/www/html/.
define('UPLOAD_DIR', '/var/www/private/uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB in bytes

// Whitelist of allowed MIME types mapped to their valid extensions.
// BOTH the MIME type AND the extension must match — two independent checks.
const ALLOWED_TYPES = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/gif'  => ['gif'],
    'image/webp' => ['webp'],
];

/**
 * handle_profile_upload(array $file, ?string $old_filename): array
 *
 * @param array       $file         The $_FILES['field_name'] array from PHP
 * @param string|null $old_filename The user's current image filename (to delete on success)
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function handle_profile_upload(array $file, ?string $old_filename): array {

    // ── CHECK 1: Was a file actually uploaded? ────────────────
    // UPLOAD_ERR_OK = 0 means no error during upload.
    // Other error codes indicate partial upload, size exceeded, etc.
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $msg = $upload_errors[$file['error']] ?? 'Unknown upload error.';
        return ['success' => false, 'filename' => null, 'error' => $msg];
    }

    // ── CHECK 2: File size ────────────────────────────────────
    // Check the ACTUAL size from the temp file, not $_FILES['size'].
    // $_FILES['size'] comes from the HTTP request and can be forged.
    // filesize() reads the real size from the filesystem — cannot be faked.
    $actual_size = filesize($file['tmp_name']);
    if ($actual_size === false || $actual_size > MAX_FILE_SIZE) {
        return ['success' => false, 'filename' => null,
                'error' => 'File is too large. Maximum size is 2MB.'];
    }
    if ($actual_size === 0) {
        return ['success' => false, 'filename' => null, 'error' => 'File is empty.'];
    }

    // ── CHECK 3: MIME type via finfo (reads magic bytes) ──────
    // finfo reads the first few bytes of the file (the "magic bytes")
    // which identify the actual file format — independent of the filename.
    // A PHP file renamed to photo.jpg will NOT have JPEG magic bytes.
    //
    // Magic bytes examples:
    //   JPEG: FF D8 FF
    //   PNG:  89 50 4E 47 0D 0A 1A 0A
    //   GIF:  47 49 46 38
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime_type, ALLOWED_TYPES)) {
        return ['success' => false, 'filename' => null,
                'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.'];
    }

    // ── CHECK 4: File extension whitelist ─────────────────────
    // Extract extension from the original filename (lowercased).
    // pathinfo() safely parses filenames — it won't be tricked by
    // filenames like "shell.php.jpg" (extension would be "jpg").
    $original_name = strtolower($file['name']);
    $extension     = pathinfo($original_name, PATHINFO_EXTENSION);

    // Check that the extension is in the allowed list for this MIME type
    if (!in_array($extension, ALLOWED_TYPES[$mime_type], true)) {
        return ['success' => false, 'filename' => null,
                'error' => 'File extension does not match file type.'];
    }

    // ── CHECK 5: Verify it's actually a valid image ───────────
    // getimagesize() attempts to parse the file as an image and returns
    // its dimensions. If the file is not a real image (e.g., a PHP script
    // with JPEG magic bytes prepended), this will fail or return false.
    if (!getimagesize($file['tmp_name'])) {
        return ['success' => false, 'filename' => null,
                'error' => 'File does not appear to be a valid image.'];
    }

    // ── ALL CHECKS PASSED: Generate safe filename ─────────────
    // bin2hex(random_bytes(16)) generates 32 hex characters of randomness.
    // The user's original filename is COMPLETELY DISCARDED.
    //
    // WHY discard the filename?
    //   1. Path traversal: filename "../../config/db.php" could overwrite files
    //   2. Double extension: "shell.php.jpg" could confuse some servers
    //   3. Null byte: "shell.php\0.jpg" could truncate in C functions
    //   4. Unicode: unusual Unicode chars can cause filesystem issues
    // A random hex filename has NONE of these issues.
    $new_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination  = UPLOAD_DIR . $new_filename;

    // ── MOVE FILE FROM TEMP TO PERMANENT STORAGE ──────────────
    // move_uploaded_file() is the ONLY safe way to move uploaded files.
    // It verifies the file is a legitimate HTTP upload (prevents attacks
    // where an attacker tricks PHP into moving arbitrary files).
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'filename' => null,
                'error' => 'Failed to save image. Please try again.'];
    }

    // Set restrictive permissions on the saved file.
    // 0640 = owner read/write, group read, others nothing.
    // The file can never be executed as a script.
    chmod($destination, 0640);

    // ── DELETE OLD IMAGE (if any) ─────────────────────────────
    // Clean up the previous profile image to prevent disk bloat.
    // Only delete if it exists and is a real file (not a directory).
    if ($old_filename) {
        $old_path = UPLOAD_DIR . basename($old_filename);
        // basename() strips any path components from stored filename —
        // prevents path traversal if DB value was somehow tampered with
        if (file_exists($old_path) && is_file($old_path)) {
            unlink($old_path);
        }
    }

    return ['success' => true, 'filename' => $new_filename, 'error' => null];
}