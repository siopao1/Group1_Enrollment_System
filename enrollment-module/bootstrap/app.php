<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ->middleware('role:admin,registrar') on a route
        $middleware->alias(['role' => EnsureRole::class]);

        // On Railway (and similar hosts) HTTPS ends at a proxy in front of the
        // app; trust its X-Forwarded-* headers so URLs and cookies use https.
        $middleware->trustProxies(at: '*');

        // Not signed in → the main app's sign-in page (single sign-on): it signs
        // the user in once and hands them back here to the page they wanted.
        // Already signed in → skip the login page.
        $middleware->redirectGuestsTo(fn (Request $request) => config('app.main_app_url')
            . '/go-laravel.php?to=' . rawurlencode('/' . ltrim($request->path(), '/')));
        $middleware->redirectUsersTo(fn () => route('offerings.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
