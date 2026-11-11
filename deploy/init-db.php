<?php

declare(strict_types=1);

/**
 * Run when the main app's container starts (deploy/start-main.sh).
 * Applies backend/database/schema.sql, which only creates what is missing:
 * the whole database on the first start, new tables after an update.
 *
 * Settings come from the environment: DB_HOST, DB_PORT, DB_USERNAME,
 * DB_PASSWORD, DB_DATABASE.
 */

$root = getenv('APP_ROOT') ?: '/var/www/html'; // the app's folder inside the container
require $root . '/backend/config/database.php';

$name = (string) envValue('DB_DATABASE', 'enrollment_system_min');
if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fwrite(STDERR, "init-db: invalid DB_DATABASE\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// The database service may still be starting: try for about a minute.
$conn = null;
for ($try = 1; $try <= 20 && $conn === null; $try++) {
    try {
        $conn = new mysqli(
            (string) envValue('DB_HOST', '127.0.0.1'),
            (string) envValue('DB_USERNAME', 'root'),
            (string) envValue('DB_PASSWORD', ''),
            '',
            (int) envValue('DB_PORT', 3306)
        );
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "init-db: waiting for the database ({$try}/20): {$e->getMessage()}\n");
        sleep(3);
    }
}
if ($conn === null) {
    fwrite(STDERR, "init-db: could not connect to the database\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

// schema.sql only creates what is missing (CREATE ... IF NOT EXISTS, INSERT
// IGNORE), so it runs on every start: a new database is created, and tables
// added in later versions (e.g. sso_tokens) appear in an existing one.
echo "init-db: applying schema.sql to {$name}\n";
$schema = file_get_contents($root . '/backend/database/schema.sql');
$conn->multi_query(str_replace('enrollment_system_min', $name, $schema));
do {
    $conn->store_result();
} while ($conn->more_results() && $conn->next_result());
echo "init-db: done\n";
