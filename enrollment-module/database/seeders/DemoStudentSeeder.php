<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo students for presentations, written to the main app's student tables
 * (students, student_personal_details, student_emergency_contacts).
 * They can be edited or deleted from the main app's Students screen.
 */
class DemoStudentSeeder extends Seeder
{
    public function run(): void
    {
        $programs = Program::pluck('id', 'name');

        // id, first, middle, last, program, year level, gender, birth date, emergency contact
        $students = [
            ['2026-00101', 'Juan', 'Santos', 'Dela Cruz', 'BS Information Technology', '1st Year', 'Male', '2007-03-14', ['Maria Dela Cruz', 'Mother']],
            ['2026-00102', 'Maria Clara', 'Lopez', 'Reyes', 'BS Computer Science', '1st Year', 'Female', '2007-08-02', ['Ramon Reyes', 'Father']],
            ['2026-00103', 'Jose Miguel', 'Cruz', 'Bautista', 'BS Information Technology', '2nd Year', 'Male', '2006-01-25', ['Elena Bautista', 'Mother']],
            ['2026-00104', 'Ana Lourdes', 'Ramos', 'Garcia', 'BS Information System', '2nd Year', 'Female', '2006-11-09', ['Pedro Garcia', 'Father']],
            ['2026-00105', 'Carlo', 'Diaz', 'Mendoza', 'BS Information Technology', '3rd Year', 'Male', '2005-06-30', ['Liza Mendoza', 'Mother']],
            ['2026-00106', 'Patricia Anne', 'Flores', 'Villanueva', 'BS Computer Science', '3rd Year', 'Female', '2005-02-17', ['Mario Villanueva', 'Father']],
            ['2026-00107', 'Mark Anthony', 'Perez', 'Ramos', 'BS Information Technology', '4th Year', 'Male', '2004-09-05', ['Gloria Ramos', 'Mother']],
            ['2026-00108', 'Kristine Joy', 'Morales', 'Aquino', 'BS Information System', '1st Year', 'Female', '2007-12-21', ['Ben Aquino', 'Father']],
        ];

        foreach ($students as $i => [$id, $first, $middle, $last, $program, $year, $gender, $dob, [$ecName, $ecRelation]]) {
            $n = $i + 1;
            $email = strtolower(str_replace(' ', '', $first)) . '.' . strtolower(str_replace(' ', '', $last)) . '@example.com';

            Student::updateOrCreate(['student_id' => $id], [
                'first_name'     => $first,
                'middle_name'    => $middle,
                'last_name'      => $last,
                'suffix'         => '',
                'email'          => $email,
                'contact_number' => sprintf('0917 100 %04d', $n),
                'address'        => 'Tarlac City, Tarlac',
                'program_id'     => $programs[$program],
                'year_level'     => $year,
                'academic_year'  => '2026–2027',
                'status'         => 'Active',
            ]);

            DB::table('student_personal_details')->updateOrInsert(
                ['student_id' => $id],
                ['date_of_birth' => $dob, 'gender' => $gender, 'civil_status' => 'Single']
            );
            DB::table('student_emergency_contacts')->updateOrInsert(
                ['student_id' => $id],
                ['name' => $ecName, 'relationship' => $ecRelation, 'number' => sprintf('0918 200 %04d', $n)]
            );
        }
    }
}
