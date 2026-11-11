<?php

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 1st Semester 2026–2027 enrollments for seven demo students.
 * 2026-00108 is left unenrolled so it can be enrolled live during a demo,
 * and IT 202-B (3 slots) ends up full.
 */
class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $semester = '1st Semester';
        $year = '2026–2027';
        $registrarId = User::where('username', 'registrar')->value('id');

        // student id => [status, [subject code => section]]
        $plan = [
            '2026-00101' => ['Active', ['CC 101' => 'A', 'CC 102' => 'A', 'GE 101' => 'A', 'GE 102' => 'A', 'PE 101' => 'A']],
            '2026-00102' => ['Dropped', ['CC 101' => 'B', 'CC 102' => 'B', 'GE 101' => 'A']],
            '2026-00103' => ['Active', ['IT 201' => 'A', 'IT 203' => 'A', 'CS 201' => 'A', 'IS 201' => 'A']],
            '2026-00104' => ['Pending', ['IT 201' => 'A', 'IS 201' => 'A', 'CS 201' => 'A']],
            '2026-00105' => ['Active', ['IT 202' => 'B', 'IT 301' => 'A', 'IS 201' => 'A']],
            '2026-00106' => ['Confirmed', ['IT 202' => 'B', 'CS 201' => 'A', 'IT 203' => 'A']],
            '2026-00107' => ['Active', ['IT 202' => 'B', 'IT 301' => 'A', 'IS 201' => 'A']],
        ];

        foreach ($plan as $studentId => [$status, $sections]) {
            $enrollment = Enrollment::updateOrCreate(
                ['student_id' => $studentId, 'semester' => $semester, 'academic_year' => $year],
                ['status' => $status, 'enrolled_at' => '2026-08-10', 'enrolled_by' => $registrarId]
            );

            $offeringIds = [];
            foreach ($sections as $code => $section) {
                $offeringIds[] = CourseOffering::where('subject_id', Subject::where('code', $code)->value('id'))
                    ->where('section', $section)
                    ->forTerm($semester, $year)
                    ->value('id');
            }

            // sync(): the pivot ends up with exactly these offerings.
            $enrollment->offerings()->sync($offeringIds);
        }
    }
}
