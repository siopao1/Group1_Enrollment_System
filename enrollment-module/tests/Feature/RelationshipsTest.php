<?php

namespace Tests\Feature;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The Eloquent relationships the module is built on. */
class RelationshipsTest extends TestCase
{
    public function test_subject_has_many_offerings_and_an_offering_belongs_to_its_subject(): void
    {
        $subject = $this->subject();
        $a = $this->offering(['subject_id' => $subject->id, 'section' => 'A']);
        $this->offering(['subject_id' => $subject->id, 'section' => 'B']);

        $this->assertCount(2, $subject->offerings);
        $this->assertTrue($a->subject->is($subject));
    }

    public function test_subject_belongs_to_its_prerequisite(): void
    {
        $intro = $this->subject();
        $advanced = $this->subject(['prerequisite_id' => $intro->id]);

        $this->assertTrue($advanced->prerequisite->is($intro));
    }

    public function test_enrollment_and_offering_are_many_to_many(): void
    {
        [$a, $b] = [$this->offering(), $this->offering()];
        $first = $this->enrollment($this->student(), [$a, $b]);
        $this->enrollment($this->student(), [$a]);

        $this->assertCount(2, $first->offerings);
        $this->assertCount(2, $a->enrollments);
        $this->assertInstanceOf(CourseOffering::class, $first->offerings->first());
    }

    public function test_student_has_many_enrollments_and_belongs_to_a_program(): void
    {
        $student = $this->student();
        $this->enrollment($student, [$this->offering()]);

        $this->assertCount(1, $student->enrollments);
        $this->assertInstanceOf(Program::class, $student->program);
        $this->assertSame('BS Information Technology', $student->program->name);
        $this->assertTrue(Enrollment::first()->student->is($student));
    }

    public function test_enrolled_count_ignores_dropped_enrollments(): void
    {
        $section = $this->offering(['slots' => 10]);
        $this->enrollment($this->student(), [$section]);
        $this->enrollment($this->student(), [$section], 'Dropped');

        $loaded = CourseOffering::withEnrolledCount()->find($section->id);
        $this->assertSame(1, $loaded->enrolled_count);
        $this->assertSame(9, $loaded->seatsLeft());
    }

    public function test_deleting_a_student_removes_their_enrollments(): void
    {
        // The main app deletes students with plain SQL; the foreign key cascades.
        $student = $this->student();
        $enrollment = $this->enrollment($student, [$this->offering()]);

        DB::table('students')->where('student_id', $student->student_id)->delete();

        $this->assertNull(Enrollment::find($enrollment->id));
        $this->assertSame(0, DB::table('enrollment_offering')->where('enrollment_id', $enrollment->id)->count());
        $this->assertNull(Student::find($student->student_id));
    }
}
