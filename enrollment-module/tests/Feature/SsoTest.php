<?php

namespace Tests\Feature;

use App\Support\SsoToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Single sign-on with the main app (app/Http/Controllers/Auth/SsoController.php). */
class SsoTest extends TestCase
{
    private function mainLogin(): string
    {
        return config('app.main_app_url') . '/login.php';
    }

    public function test_a_login_code_signs_the_user_in_and_opens_the_page(): void
    {
        $user = $this->user('registrar');
        $code = SsoToken::issue($user->id, 'login');

        $this->get("/sso?token={$code}&to=%2Fenrollments%2Fcreate")->assertRedirect('/enrollments/create');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('sso_tokens', ['token_hash' => hash('sha256', $code)]);
    }

    public function test_a_code_works_only_once(): void
    {
        $code = SsoToken::issue($this->user()->id, 'login');
        $this->get("/sso?token={$code}");
        $this->post('/logout');

        $this->get("/sso?token={$code}")->assertRedirect($this->mainLogin());
        $this->assertGuest();
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = SsoToken::issue($this->user()->id, 'login');
        DB::table('sso_tokens')->where('token_hash', hash('sha256', $code))->update(['expires_at' => DB::raw('NOW() - INTERVAL 1 SECOND')]);

        $this->get("/sso?token={$code}")->assertRedirect($this->mainLogin());
        $this->assertGuest();
    }

    public function test_a_made_up_code_is_refused(): void
    {
        $this->get('/sso?token=' . bin2hex(random_bytes(32)))->assertRedirect($this->mainLogin());
        $this->get('/sso?token=not-a-code')->assertRedirect($this->mainLogin());
        $this->assertGuest();
    }

    public function test_a_logout_code_ends_this_users_session(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $code = SsoToken::issue($user->id, 'logout');

        $this->get("/sso?token={$code}")->assertRedirect($this->mainLogin());
        $this->assertGuest();
    }

    public function test_a_logout_code_for_another_user_is_ignored(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $code = SsoToken::issue($this->user('faculty')->id, 'logout');

        $this->get("/sso?token={$code}");
        $this->assertAuthenticatedAs($admin);
    }

    public function test_go_main_hands_the_user_to_the_main_app(): void
    {
        $user = $this->user('registrar');

        $response = $this->actingAs($user)->get('/go-main?to=students');

        $location = $response->headers->get('Location');
        $this->assertMatchesRegularExpression('#^' . preg_quote(config('app.main_app_url'), '#') . '/sso\.php\?token=[a-f0-9]{64}&to=students$#', $location);
        preg_match('/token=([a-f0-9]{64})/', $location, $m);
        $this->assertDatabaseHas('sso_tokens', ['user_id' => $user->id, 'purpose' => 'login', 'token_hash' => hash('sha256', $m[1])]);
    }

    public function test_go_main_requires_a_signed_in_user(): void
    {
        $this->get('/go-main?to=students')->assertRedirectContains('/go-laravel.php');
    }

    public function test_targets_outside_the_system_are_ignored(): void
    {
        $user = $this->user();

        $code = SsoToken::issue($user->id, 'login');
        $this->get("/sso?token={$code}&to=" . rawurlencode('https://evil.example'))->assertRedirect('/offerings');

        $code = SsoToken::issue($user->id, 'login');
        $this->get("/sso?token={$code}&to=" . rawurlencode('//evil.example'))->assertRedirect('/offerings');

        $location = $this->actingAs($user)->get('/go-main?to=' . rawurlencode('https://evil.example'))->headers->get('Location');
        $this->assertStringEndsWith('&to=dashboard', $location);
    }
}
