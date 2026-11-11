<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/** Subjects from a typical IT / CS / IS curriculum, with prerequisites. */
class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        // code, name, units, department, prerequisite code, status, description
        $subjects = [
            ['GE 101', 'Understanding the Self', 3, 'General Education', null, 'Active', 'Nature of identity and the factors that shape the self.'],
            ['GE 102', 'Purposive Communication', 3, 'General Education', null, 'Active', 'Writing, speaking and presenting for different audiences and purposes.'],
            ['PE 101', 'Physical Fitness', 2, 'General Education', null, 'Active', 'Fitness concepts and exercise programs.'],
            ['CC 101', 'Introduction to Computing', 3, 'Information Technology', null, 'Active', 'Computer systems, software, the internet and computing careers.'],
            ['CC 102', 'Computer Programming 1', 3, 'Computer Science', null, 'Active', 'Programming fundamentals: variables, control structures and functions.'],
            ['CC 103', 'Computer Programming 2', 3, 'Computer Science', 'CC 102', 'Active', 'Arrays, records, files and an introduction to object-oriented programming.'],
            ['CC 104', 'Data Structures and Algorithms', 3, 'Computer Science', 'CC 103', 'Active', 'Lists, stacks, queues, trees, sorting and searching.'],
            ['CS 201', 'Discrete Structures', 3, 'Computer Science', null, 'Active', 'Logic, sets, relations, functions and graphs for computing.'],
            ['IS 201', 'Systems Analysis and Design', 3, 'Information Systems', null, 'Active', 'Requirements, modeling and design of information systems.'],
            ['IT 201', 'Web Systems and Technologies', 3, 'Information Technology', 'CC 102', 'Active', 'HTML, CSS, JavaScript and client–server web basics.'],
            ['IT 202', 'Advanced Web Development', 3, 'Information Technology', 'IT 201', 'Active', 'Server-side PHP, MySQL, REST APIs, security and Laravel.'],
            ['IT 203', 'Information Management', 3, 'Information Technology', 'CC 103', 'Active', 'Database design, normalization and SQL.'],
            ['IT 301', 'Networking 1', 3, 'Information Technology', null, 'Active', 'Network models, addressing, switching and routing basics.'],
            ['IT 302', 'Information Assurance and Security', 3, 'Information Technology', 'IT 301', 'Active', 'Security principles, threats, controls and policies.'],
            ['IT 399', 'Special Topics in IT', 3, 'Information Technology', null, 'Inactive', 'Elective on emerging technologies; not offered this year.'],
        ];

        // First pass: create or update every subject without prerequisites.
        foreach ($subjects as [$code, $name, $units, $department, , $status, $description]) {
            Subject::updateOrCreate(
                ['code' => $code],
                compact('name', 'units', 'department', 'status', 'description')
            );
        }

        // Second pass: link prerequisites now that every code exists.
        foreach ($subjects as [$code, , , , $prerequisiteCode]) {
            Subject::where('code', $code)->update([
                'prerequisite_id' => $prerequisiteCode
                    ? Subject::where('code', $prerequisiteCode)->value('id')
                    : null,
            ]);
        }
    }
}
