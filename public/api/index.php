<?php

declare(strict_types=1);

/**
 * The single entry point for the REST API. Every request under /api/ comes
 * here (public/api/.htaccess on Apache, public/router.php on `php -S`).
 *
 * This file is the route table: method, URL, who may call it, and which
 * controller method handles it. The roles are checked by the Router before
 * the handler runs.
 */

require __DIR__ . '/../../backend/api/bootstrap.php';

$everyone  = Auth::ROLES;              // any signed-in role
$staff     = ['admin', 'registrar'];   // may add and edit
$adminOnly = ['admin'];                // may delete

// Controllers are created only when their route is used.
$students  = static fn (): StudentController => new StudentController(new StudentRepository(db()));
$dashboard = static fn (): DashboardController => new DashboardController(new DashboardRepository(db()), new SettingsRepository(db()));
$auth      = static fn (): AuthController => new AuthController();
$settings  = static fn (): SettingsController => new SettingsController(new SettingsRepository(db()));
$reports   = static function (): ReportController {
    $repository = new ReportRepository(db());

    return new ReportController($repository, new ReportService($repository));
};

$router = new Router();

// Authentication (token sign-in for API clients such as Postman)
$router->add('POST', 'auth/login', null, fn () => $auth()->login());
$router->add('POST', 'auth/logout', $everyone, fn (array $p, array $user) => $auth()->logout($user));
$router->add('GET', 'auth/me', $everyone, fn (array $p, array $user) => $auth()->me($user));

// Students — the system's core resource
$router->add('GET', 'students', $everyone, fn () => $students()->index());
$router->add('POST', 'students', $staff, fn () => $students()->store());
$router->add('GET', 'students/{id}', $everyone, fn (array $p) => $students()->show($p['id']));
$router->add('PUT', 'students/{id}', $staff, fn (array $p) => $students()->replace($p['id']));
$router->add('PATCH', 'students/{id}', $staff, fn (array $p) => $students()->patch($p['id']));
$router->add('DELETE', 'students/{id}', $adminOnly, fn (array $p) => $students()->destroy($p['id']));

// Dashboard
$router->add('GET', 'dashboard', $everyone, fn () => $dashboard()->show());

// Settings — everyone reads the defaults; only admin changes them
$router->add('GET', 'settings', $everyone, fn () => $settings()->show());
$router->add('PUT', 'settings', $adminOnly, fn (array $p, array $user) => $settings()->update($user));

// Reports — admin and registrar (faculty gets 403)
$router->add('GET', 'reports', $staff, fn () => $reports()->index());
$router->add('GET', 'reports/{type}', $staff, fn (array $p) => $reports()->show($p['type']));

$router->dispatch(Request::method(), Request::apiPath());
