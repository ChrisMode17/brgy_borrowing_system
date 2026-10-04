<?php

use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            $connectionError = $exception instanceof QueryException
                ? $exception->getPrevious()
                : $exception;

            $isDatabaseUnavailable = $exception instanceof LostConnectionException
                || ($connectionError instanceof PDOException
                    && app(LostConnectionDetector::class)->causedByLostConnection($connectionError));

            if (! $isDatabaseUnavailable) {
                return null;
            }

            $message = 'The service is temporarily unavailable. Please try again later.';

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return response()->view('errors.database-unavailable', status: 503);
        });
    })->create();
