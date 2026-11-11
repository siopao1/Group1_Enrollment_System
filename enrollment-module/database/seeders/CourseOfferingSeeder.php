<?php

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/** Sections for the 2026–2027 academic year. IT 202-B is small so it fills up. */
class CourseOfferingSeeder extends Seeder
{
    public function run(): void
    {
        $year = '2026–2027';

        // subject code, section, semester, instructor, room, schedule, slots
        $offerings = [
            ['GE 101', 'A', '1st Semester', 'Prof. L. Navarro', 'GE-201', 'MWF 7:30–8:30 AM', 40],
            ['GE 102', 'A', '1st Semester', 'Prof. R. Santiago', 'GE-202', 'TTh 9:00–10:30 AM', 40],
            ['PE 101', 'A', '1st Semester', 'Coach M. Dizon', 'Gym', 'Sat 8:00–10:00 AM', 50],
            ['CC 101', 'A', '1st Semester', 'Prof. J. Villareal', 'CCS-Lab 1', 'MWF 9:00–10:00 AM', 35],
            ['CC 101', 'B', '1st Semester', 'Prof. J. Villareal', 'CCS-Lab 1', 'MWF 1:00–2:00 PM', 35],
            ['CC 102', 'A', '1st Semester', 'Prof. A. Castillo', 'CCS-Lab 2', 'TTh 1:00–2:30 PM', 35],
            ['CC 102', 'B', '1st Semester', 'Prof. A. Castillo', 'CCS-Lab 2', 'TTh 3:00–4:30 PM', 35],
            ['CS 201', 'A', '1st Semester', 'Prof. E. Manalo', 'CCS-301', 'MWF 10:00–11:00 AM', 40],
            ['IS 201', 'A', '1st Semester', 'Prof. G. Pascual', 'CCS-302', 'TTh 10:30 AM–12:00 PM', 40],
            ['IT 201', 'A', '1st Semester', 'Prof. D. Torres', 'CCS-Lab 3', 'MWF 2:00–3:00 PM', 35],
            ['IT 202', 'A', '1st Semester', 'Prof. L. Dignos', 'CCS-Lab 4', 'TTh 7:30–9:00 AM', 35],
            ['IT 202', 'B', '1st Semester', 'Prof. L. Dignos', 'CCS-Lab 4', 'TTh 4:30–6:00 PM', 3],
            ['IT 203', 'A', '1st Semester', 'Prof. A. Barcelona', 'CCS-Lab 3', 'TTh 1:00–2:30 PM', 35],
            ['IT 301', 'A', '1st Semester', 'Prof. S. Ocampo', 'Network Lab', 'MWF 3:00–4:00 PM', 30],
            ['CC 103', 'A', '2nd Semester', 'Prof. A. Castillo', 'CCS-Lab 2', 'TTh 1:00–2:30 PM', 35],
            ['CC 104', 'A', '2nd Semester', 'Prof. E. Manalo', 'CCS-Lab 1', 'MWF 9:00–10:00 AM', 35],
            ['IT 302', 'A', '2nd Semester', 'Prof. S. Ocampo', 'Network Lab', 'TTh 9:00–10:30 AM', 30],
        ];

        foreach ($offerings as [$code, $section, $semester, $instructor, $room, $schedule, $slots]) {
            CourseOffering::updateOrCreate(
                [
                    'subject_id'    => Subject::where('code', $code)->value('id'),
                    'section'       => $section,
                    'semester'      => $semester,
                    'academic_year' => $year,
                ],
                compact('instructor', 'room', 'schedule', 'slots') + ['status' => 'Open']
            );
        }
    }
}
