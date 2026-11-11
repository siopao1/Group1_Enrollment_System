<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * php artisan db:seed
 *
 * Needs the main app's tables and demo accounts first:
 *   backend/database/schema.sql and backend/database/seed_users.php
 * Every seeder can be run again safely (updateOrCreate / sync).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubjectSeeder::class,
            CourseOfferingSeeder::class,
            DemoStudentSeeder::class,
            EnrollmentSeeder::class,
        ]);
    }
}
