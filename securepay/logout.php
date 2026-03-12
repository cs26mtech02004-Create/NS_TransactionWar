<?php
/**
 * FILE: logout.php
 * Accepts POST with csrf_token field.
 * The logout button in header.php is a <form method="POST"> — not a link.
 * This prevents CSRF-forced-logout and eliminates the stale-token problem
 * that plagued the old GET + ?csrf_token= approach.
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_SESSION['user_id'])) {
        $token  = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';

        if (empty($stored) || !hash_equals($stored, $token)) {
            http_response_code(403);
            die('Invalid logout request.');
        }
    }

    destroy_session();
    header('Location: /login.php');
    exit();
}

// GET request (someone typed /logout.php in the URL bar) — just redirect
if (isset($_SESSION['user_id'])) {
    // Redirect back — don't log out on GET (CSRF protection)
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit();