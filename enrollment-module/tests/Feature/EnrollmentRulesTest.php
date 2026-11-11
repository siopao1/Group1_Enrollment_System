<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Tests\TestCase;

/** The enrollment business rules (app/Services/EnrollmentService.php, EnrollmentRequest). */
class EnrollmentRulesTest extends TestCase
{
    public function test_registrar_enrolls_a_student_in_two_sections(): void
    {
        $registrar = $this->user('registrar');
        $student = $this->student();
        [$a, $b] = [$this->offering(), $this->offering()];

        $response = $this->enrollForm($registrar, $student, [$a, $b]);

        $enrollment = Enrollment::where('student_id', $student->student_id)->firstOrFail();
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertSame($registrar->id, $enrollment->enrolled_by);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $enrollment->offerings()->pluck('course_offerings.id')->all());
    }

    public function test_a_student_cannot_be_enrolled_twice_in_the_same_term(): void
    {
        $student = $this->student();
        $this->enrollment($student, [$this->offering()]);

        $this->enrollForm($this->user(), $student, [$this->offering()])
            ->assertSessionHasErrors(['student_id' => 'This student is already enrolled for that term. Open the existing enrollment to change it.']);

        $this->assertSame(1, Enrollment::where('student_id', $student->student_id)->count());
    }

    public function test_a_full_section_is_rejected(): void
    {
        $section = $this->offering(['slots' => 1]);
        $this->enrollment($this->student(), [$section]);

        $this->enrollForm($this->user(), $this->student(), [$section])->assertSessionHasErrors('offerings');
        $this->assertSame(1, $section->enrollments()->count());
    }

    public function test_a_dropped_enrollment_frees_its_seat(): void
    {
        $section = $this->offering(['slots' => 1]);
        $this->enrollment($this->student(), [$section], 'Dropped');

        $this->enrollForm($this->user(), $this->student(), [$section])->assertSessionHasNoErrors();
        $this->assertSame(2, $section->enrollments()->count());
    }

    public function test_a_closed_section_is_rejected(): void
    {
        $this->enrollForm($this->user(), $this->student(), [$this->offering(['status' => 'Closed'])])
            ->assertSessionHasErrors('offerings');
    }

    public function test_a_section_of_an_inactive_subject_is_rejected(): void
    {
        $subject = $this->subject(['status' => 'Inactive']);

        $this->enrollForm($this->user(), $this->student(), [$this->offering(['subject_id' => $subject->id])])
            ->assertSessionHasErrors('offerings');
    }

    public function test_two_sections_of_the_same_subject_are_rejected(): void
    {
        $subject = $this->subject();
        $a = $this->offering(['subject_id' => $subject->id, 'section' => 'A']);
        $b = $this->offering(['subject_id' => $subject->id, 'section' => 'B']);

        $this->enrollForm($this->user(), $this->student(), [$a, $b])->assertSessionHasErrors('offerings');
    }

    public function test_more_than_the_maximum_units_is_rejected(): void
    {
        // 5 subjects × 6 units = 30 units > 26
        $sections = collect(range(1, 5))->map(fn () => $this->offering(['subject_id' => $this->subject(['units' => 6])->id]))->all();
        $this->assertGreaterThan(EnrollmentService::MAX_UNITS, 30);

        $this->enrollForm($this->user(), $this->student(), $sections)->assertSessionHasErrors('offerings');
    }

    public function test_a_section_from_another_term_is_rejected(): void
    {
        $other = $this->offering(['semester' => '2nd Semester']);

        $this->enrollForm($this->user(), $this->student(), [$other])->assertSessionHasErrors('offerings');
    }

    public function test_at_least_one_subject_is_required(): void
    {
        $this->enrollForm($this->user(), $this->student(), [])
            ->assertSessionHasErrors(['offerings' => 'Select at least one subject.']);
    }

    public function test_an_unknown_student_is_rejected(): void
    {
        $this->actingAs($this->user())->post(route('enrollments.store'), [
            'student_id' => '2000-00000', 'semester' => self::TERM_SEM, 'academic_year' => self::TERM_YEAR,
            'status' => 'Active', 'offerings' => [$this->offering()->id],
        ])->assertSessionHasErrors(['student_id' => 'Choose a student from the list.']);
    }

    public function test_slots_cannot_be_set_below_the_number_enrolled(): void
    {
        $section = $this->offering(['slots' => 5]);
        $this->enrollment($this->student(), [$section]);
        $this->enrollment($this->student(), [$section]);

        $this->actingAs($this->user('admin'))->put(route('offerings.update', $section), [
            'subject_id' => $section->subject_id, 'section' => 'A', 'semester' => self::TERM_SEM,
            'academic_year' => self::TERM_YEAR, 'slots' => 1, 'status' => 'Open',
        ])->assertSessionHasErrors('slots');

        $this->assertSame(5, $section->fresh()->slots);
    }

    public function test_a_subject_with_offerings_cannot_be_deleted(): void
    {
        $section = $this->offering();

        $this->actingAs($this->user('admin'))->delete(route('subjects.destroy', $section->subject_id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('subjects', ['id' => $section->subject_id]);
    }
}
