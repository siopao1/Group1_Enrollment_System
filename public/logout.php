<?php

/**
 * Ends the signed-in session.
 *
 * Only accepts POST with the session's CSRF token (form field "csrf" or the
 * X-CSRF-Token header), so another website cannot sign a user out with a
 * plain link or image.
 */

require __DIR__ . '/../backend/auth/Auth.php';

Auth::start();

$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Sign out must be sent as a POST request.');
}

if (!Auth::verifyCsrf($token)) {
    http_response_code(419);
    exit('Invalid or missing CSRF token. Refresh the page and try again.');
}

Auth::logout();

header('Location: login.php');
exit;
