<?php

namespace Tests;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Base class for the module's tests.
 *
 * The tests never touch the real database. They run against a separate
 * database (DB_DATABASE in phpunit.xml, "enrollment_system_test") that is
 * rebuilt once per test run from the main app's schema.sql plus this
 * module's migrations, exactly like a real installation. Every test then runs
 * inside a transaction that is rolled back afterwards (DatabaseTransactions),
 * so tests cannot affect each other.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public const TERM_YEAR = '2026–2027';

    public const TERM_SEM = '1st Semester';

    private static bool $databaseBuilt = false;

    protected function setUp(): void
    {
        if (!self::$databaseBuilt) {
            self::buildTestDatabase();
            self::$databaseBuilt = true;
        }

        parent::setUp();
    }

    /** Drops and recreates the test database: main schema, then the module's migrations. */
    private static function buildTestDatabase(): void
    {
        $name = getenv('DB_DATABASE');
        if (!$name || !str_ends_with($name, '_test')) {
            throw new RuntimeException('Refusing to rebuild a database whose name does not end in _test.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new \mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', '', (int) (getenv('DB_PORT') ?: 3306));
        $db->set_charset('utf8mb4');
        $db->query("DROP DATABASE IF EXISTS `$name`");

        // The main app's schema, pointed at the test database.
        $schema = file_get_contents(dirname(__DIR__, 2) . '/backend/database/schema.sql');
        $db->multi_query(str_replace('enrollment_system_min', $name, $schema));
        do {
            $db->store_result();
        } while ($db->more_results() && $db->next_result());
        $db->close();

        // This module's tables (subjects, course_offerings, enrollments, enrollment_offering).
        $artisan = escapeshellarg(dirname(__DIR__) . '/artisan');
        exec(escapeshellarg(PHP_BINARY) . " $artisan migrate --force --no-interaction 2>&1", $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("Migrations failed:\n" . implode("\n", $output));
        }
    }

    // ------------------------------------------------------------- test data

    protected function user(string $role = 'registrar', string $password = 'Secret@12345'): User
    {
        return User::create([
            'username'      => $role . '_' . uniqid(),
            'full_name'     => ucfirst($role) . ' Tester',
            'email'         => uniqid() . '@example.com',
            // Hashed the same way the main app's register.php does.
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
        ]);
    }

    protected function student(array $attributes = []): Student
    {
        static $next = 10000;

        return Student::create($attributes + [
            'student_id'  => '2097-' . $next++,
            'first_name'  => 'Test',
            'last_name'   => 'Student',
            'email'       => uniqid() . '@example.com',
            'program_id'  => Program::where('name', 'BS Information Technology')->value('id'),
            'year_level'  => '1st Year',
            'academic_year' => self::TERM_YEAR,
            'status'      => 'Active',
        ]);
    }

    protected function subject(array $attributes = []): Subject
    {
        static $next = 100;

        return Subject::create($attributes + [
            'code'       => 'TS ' . $next++,
            'name'       => 'Test Subject',
            'units'      => 3,
            'department' => 'Information Technology',
            'status'     => 'Active',
        ]);
    }

    protected function offering(array $attributes = []): CourseOffering
    {
        return CourseOffering::create($attributes + [
            'subject_id'    => $attributes['subject_id'] ?? $this->subject()->id,
            'section'       => 'A',
            'semester'      => self::TERM_SEM,
            'academic_year' => self::TERM_YEAR,
            'slots'         => 40,
            'status'        => 'Open',
        ]);
    }

    /** An existing enrollment, created directly (not through the form). */
    protected function enrollment(Student $student, array $offerings, string $status = 'Active'): Enrollment
    {
        $enrollment = Enrollment::create([
            'student_id'    => $student->student_id,
            'semester'      => self::TERM_SEM,
            'academic_year' => self::TERM_YEAR,
            'status'        => $status,
            'enrolled_at'   => now()->toDateString(),
        ]);
        $enrollment->offerings()->attach(array_map(fn (CourseOffering $o) => $o->id, $offerings));

        return $enrollment;
    }

    /** Submits the "Enroll Student" form. */
    protected function enrollForm(User $as, Student $student, array $offerings, array $extra = [])
    {
        return $this->actingAs($as)->from(route('enrollments.create'))->post(route('enrollments.store'), $extra + [
            'student_id'    => $student->student_id,
            'semester'      => self::TERM_SEM,
            'academic_year' => self::TERM_YEAR,
            'status'        => 'Active',
            'offerings'     => array_map(fn (CourseOffering $o) => $o->id, $offerings),
        ]);
    }
}
