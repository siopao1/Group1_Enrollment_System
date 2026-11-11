<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use Tests\TestCase;

/** Who may open which page (routes/web.php + the EnsureRole middleware). */
class AccessControlTest extends TestCase
{
    public function test_guests_are_sent_to_the_main_apps_sign_in_page(): void
    {
        // Single sign-on: the main app signs them in and hands them back to the page.
        foreach (['/subjects', '/offerings', '/enrollments', '/enrollments/create', '/records'] as $url) {
            $this->get($url)->assertRedirect(config('app.main_app_url') . '/go-laravel.php?to=' . rawurlencode($url));
        }
    }

    public function test_faculty_can_view_but_not_manage(): void
    {
        $faculty = $this->user('faculty');
        $this->actingAs($faculty);

        $this->get('/subjects')->assertOk();
        $this->get('/offerings')->assertOk();
        $this->get('/records')->assertOk();

        $this->get('/subjects/create')->assertForbidden();
        $this->get('/offerings/create')->assertForbidden();
        $this->get('/enrollments')->assertForbidden();
        $this->get('/enrollments/create')->assertForbidden();
        $this->post('/subjects', ['code' => 'ZZ 100'])->assertForbidden();
    }

    public function test_registrar_can_enroll_but_not_manage_subjects_or_delete(): void
    {
        $registrar = $this->user('registrar');
        $enrollment = $this->enrollment($this->student(), [$this->offering()]);
        $this->actingAs($registrar);

        $this->get('/enrollments')->assertOk();
        $this->get('/enrollments/create')->assertOk();
        $this->get('/subjects/create')->assertForbidden();
        $this->delete(route('enrollments.destroy', $enrollment))->assertForbidden();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_admin_can_delete_an_enrollment(): void
    {
        $enrollment = $this->enrollment($this->student(), [$this->offering()]);

        $this->actingAs($this->user('admin'))
            ->delete(route('enrollments.destroy', $enrollment))
            ->assertRedirect(route('enrollments.index'));

        $this->assertNull(Enrollment::find($enrollment->id));
        $this->assertDatabaseMissing('enrollment_offering', ['enrollment_id' => $enrollment->id]);
    }

    public function test_admin_can_add_a_subject(): void
    {
        $this->actingAs($this->user('admin'))->post('/subjects', [
            'code' => 'ZZ 101', 'name' => 'Test Course', 'units' => 3,
            'department' => 'Information Technology', 'status' => 'Active',
        ])->assertRedirect();

        $this->assertDatabaseHas('subjects', ['code' => 'ZZ 101']);
    }

    public function test_subject_validation_rejects_a_bad_code_and_units(): void
    {
        $this->actingAs($this->user('admin'))->post('/subjects', [
            'code' => 'bad code!', 'name' => '', 'units' => 9,
            'department' => 'Nursing', 'status' => 'Active',
        ])->assertSessionHasErrors(['code', 'name', 'units', 'department']);
    }
}
