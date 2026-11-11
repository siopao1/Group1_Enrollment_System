<?php

/**
 * Ends the signed-in session.
 *
 * Only accepts POST with the session's CSRF token (form field "csrf" or the
 * X-CSRF-Token header), so another website cannot sign a user out with a
 * plain link or image.
 */

require __DIR__ . '/../backend/config/database.php';
require __DIR__ . '/../backend/auth/Auth.php';
require __DIR__ . '/../backend/auth/SsoToken.php';

Auth::start();

$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Sign out must be sent as a POST request.');
}

if (!Auth::verifyCsrf($token)) {
    Auth::sendCsrfFailedStatus();
    exit('Invalid or missing CSRF token. Refresh the page and try again.');
}

$user = Auth::user();
Auth::logout();

if ($user === null) {
    header('Location: login.php');
    exit;
}

// Single sign-on: also end the session in the Laravel module. A one-time
// logout code goes along, so another website cannot log anyone out this way;
// the module then sends the browser back to login.php.
$token = SsoToken::issue(getDbConnection(), (int) $user['id'], 'logout');
$laravel = rtrim((string) envValue('LARAVEL_URL', 'http://localhost:8001'), '/');
header('Location: ' . $laravel . '/sso?token=' . $token);
exit;
