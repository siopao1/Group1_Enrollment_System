<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // Not signed in → the login page; already signed in → skip the login page.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('offerings.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
