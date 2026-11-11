<?php

/**
 * Reads a setting from the .env file in the project root.
 * Secrets such as the database password live there instead of in the code,
 * so they never end up in the Git repository.
 */
function envValue(string $key, $default = null)
{
    static $vars = null;

    if ($vars === null) {
        $file = __DIR__ . '/../../.env';
        $vars = is_file($file) ? (parse_ini_file($file) ?: []) : [];
    }

    return $vars[$key] ?? $default;
}

/**
 * Opens a MySQLi connection using the values from .env.
 * On failure the real reason is written to the server's error log and the
 * visitor only sees a generic message, so no credentials or paths leak.
 *
 * Pages call it with no argument. The API passes false so the exception
 * reaches its own handler, which answers in JSON instead of plain text.
 */
function getDbConnection(bool $handleFailure = true): mysqli
{
    // Make MySQLi throw exceptions instead of silently returning false.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(
            envValue('DB_HOST', '127.0.0.1'),
            envValue('DB_USERNAME', 'root'),
            envValue('DB_PASSWORD', ''),
            envValue('DB_DATABASE', 'enrollment_system_min')
        );
        $conn->set_charset('utf8mb4');

        return $conn;
    } catch (mysqli_sql_exception $e) {
        if (!$handleFailure) {
            throw $e;
        }
        error_log('[DB] Connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Database connection failed. Please try again later.');
    }
}
