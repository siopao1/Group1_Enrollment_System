<?php

/**
 * Router for PHP's built-in server, which ignores .htaccess files.
 *
 *   php -S localhost:8000 -t public public/router.php
 *
 * Requests under /api/ go to the API router; everything else (pages, CSS,
 * JavaScript) is served normally.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/api(/|$)#', $path) === 1) {
    require __DIR__ . '/api/index.php';
    return true;
}

return false;
