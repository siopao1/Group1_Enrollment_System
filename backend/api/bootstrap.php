<?php

declare(strict_types=1);

/**
 * Loaded once by public/api/index.php before any route runs.
 * Sets up error handling so that every failure — including PHP warnings and
 * database errors — becomes a JSON response with a generic message, while
 * the real details go only to the server's error log.
 */

ini_set('display_errors', '0'); // never print PHP errors into a JSON response

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/ApiAuth.php';

/** One shared database connection per request. */
function db(): mysqli
{
    static $conn = null;

    return $conn ??= getDbConnection(false);
}

// PHP warnings and notices become exceptions, so they reach the handler below.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[API] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

    $message = $e instanceof mysqli_sql_exception ? 'A database error occurred.' : 'Internal server error.';
    $debug = (string) envValue('APP_DEBUG', '0') === '1';

    // With APP_DEBUG=1 (development only) the real message is included to help debugging.
    Response::error($message, 500, $debug ? ['debug' => [$e->getMessage()]] : null);
});
