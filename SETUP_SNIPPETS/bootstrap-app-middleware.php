<?php

// Add this inside the ->withMiddleware(function (Middleware $middleware) { ... })
// closure in your project's bootstrap/app.php file.
//
// Example full file after editing:
//
// use App\Http\Middleware\EnsureAdminAccess;
// use App\Http\Middleware\EnsureAccessLevel;
// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;
//
// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         web: __DIR__.'/../routes/web.php',
//         commands: __DIR__.'/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function (Middleware $middleware) {
//         $middleware->alias([
//             'admin' => EnsureAdminAccess::class,
//             'access.level' => EnsureAccessLevel::class,
//         ]);
//     })
//     ->withExceptions(function (Exceptions $exceptions) {
//         //
//     })->create();
