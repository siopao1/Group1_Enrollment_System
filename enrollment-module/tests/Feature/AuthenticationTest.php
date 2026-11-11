<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Signing in and out of the module with the main app's accounts. */
class AuthenticationTest extends TestCase
{
    public function test_a_password_hashed_by_the_main_app_signs_in_here(): void
    {
        // password_hash() is what the main app's seeder and register.php use.
        $user = $this->user('registrar', 'Registrar@12345');

        $this->post('/login', ['username' => $user->username, 'password' => 'Registrar@12345'])
            ->assertRedirect(route('offerings.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_gets_the_generic_message(): void
    {
        $user = $this->user();

        $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'wrong'])
            ->assertSessionHasErrors(['username' => 'Invalid username or password.']);

        $this->assertGuest();
    }

    public function test_sign_in_is_blocked_after_five_failed_attempts(): void
    {
        $user = $this->user('faculty', 'Faculty@12345');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'wrong']);
        }

        // Even the correct password is refused while the limit is active.
        $this->post('/login', ['username' => $user->username, 'password' => 'Faculty@12345'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('username'));
    }

    public function test_logout_ends_the_session_and_passes_through_the_main_app(): void
    {
        $user = $this->user();
        $response = $this->actingAs($user)->post('/logout');

        // Single sign-on: the main app gets a one-time logout code for this user.
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(config('app.main_app_url') . '/sso.php?token=', $location);
        preg_match('/token=([a-f0-9]{64})/', $location, $m);
        $this->assertDatabaseHas('sso_tokens', ['user_id' => $user->id, 'purpose' => 'logout', 'token_hash' => hash('sha256', $m[1])]);

        $this->assertGuest();
        $this->get('/records')->assertRedirectContains('/go-laravel.php');
    }

    public function test_logout_by_get_is_not_allowed(): void
    {
        $this->actingAs($this->user())->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();
    }
}
