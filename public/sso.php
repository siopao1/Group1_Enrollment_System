<?php

/**
 * Single sign-on: Laravel module → main app.
 *
 * The module sends the browser here with a one-time code (see SsoToken):
 *   purpose 'login'  → sign the same user in here and open #/<to>
 *   purpose 'logout' → end this app's session too (logout in the module)
 * An unknown, expired or used code just leads to the sign-in page.
 */

require __DIR__ . '/../backend/config/database.php';
require __DIR__ . '/../backend/auth/Auth.php';
require __DIR__ . '/../backend/auth/SsoToken.php';

Auth::start();
header('Cache-Control: no-store');

// A screen of this app, e.g. "dashboard" or "students".
$to = (string) ($_GET['to'] ?? 'dashboard');
if (preg_match('/^[a-z-]+$/', $to) !== 1) {
    $to = 'dashboard';
}

$conn = getDbConnection();
$code = SsoToken::consume($conn, (string) ($_GET['token'] ?? ''));

if ($code === null) {
    header('Location: login.php');
    exit;
}

if ($code['purpose'] === 'logout') {
    $user = Auth::user();
    if ($user !== null && (int) $user['id'] === $code['user_id']) {
        Auth::logout();
    }
    header('Location: login.php');
    exit;
}

if (!Auth::loginById($conn, $code['user_id'])) {
    header('Location: login.php');
    exit;
}

header('Location: index.php#/' . $to);
exit;
