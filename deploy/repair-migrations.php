<?php

/**
 * Run by deploy/start-laravel.sh before "php artisan migrate".
 *
 * MySQL cannot roll back CREATE TABLE, so a migration that fails half-way
 * (e.g. its foreign key points to a table that does not exist yet) leaves its
 * table behind without being recorded as done, and every later "migrate"
 * stops with "Table already exists". This removes such a leftover table, but
 * only when all three hold: the table exists, its migration is NOT recorded,
 * and the table is empty. Tables with data are never touched.
 */

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('migrations')) {
    exit(0); // nothing has been migrated yet
}

// The module's tables, children before parents (foreign keys).
$tables = [
    'enrollment_offering' => 'create_enrollment_offering_table',
    'enrollments'         => 'create_enrollments_table',
    'course_offerings'    => 'create_course_offerings_table',
    'subjects'            => 'create_subjects_table',
];

foreach ($tables as $table => $migration) {
    $recorded = DB::table('migrations')->where('migration', 'like', "%_{$migration}")->exists();
    if (Schema::hasTable($table) && !$recorded && DB::table($table)->count() === 0) {
        Schema::drop($table);
        echo "repair-migrations: removed the empty table {$table} left by a failed migration\n";
    }
}
