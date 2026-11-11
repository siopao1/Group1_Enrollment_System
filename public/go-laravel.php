<?php

/**
 * Single sign-on: main app → Laravel module.
 *
 * The menu links to Laravel pages point here (go-laravel.php?to=/subjects).
 * For a signed-in user it creates a one-time code and sends the browser to
 * the module's /sso page, which signs the same user in there and opens the
 * page. Visitors who are not signed in go to login.php first and come back.
 */

require __DIR__ . '/../backend/config/database.php';
require __DIR__ . '/../backend/auth/Auth.php';
require __DIR__ . '/../backend/auth/SsoToken.php';

Auth::start();
header('Cache-Control: no-store');

// Only a path inside the module ("/subjects", "/enrollments/create"), never
// another website.
$to = (string) ($_GET['to'] ?? '/');
if (preg_match('#^/[A-Za-z0-9/_-]*$#', $to) !== 1 || str_starts_with($to, '//')) {
    $to = '/';
}

$user = Auth::user();
if ($user === null) {
    header('Location: login.php?next=' . rawurlencode('go-laravel.php?to=' . $to));
    exit;
}

$token = SsoToken::issue(getDbConnection(), (int) $user['id'], 'login');
$laravel = rtrim((string) envValue('LARAVEL_URL', 'http://localhost:8001'), '/');

header('Location: ' . $laravel . '/sso?token=' . $token . '&to=' . rawurlencode($to));
exit;
