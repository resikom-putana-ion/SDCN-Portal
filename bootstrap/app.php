<?php

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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([\App\Exceptions\FirebaseAuthException::class]);
        $exceptions->render(function (\App\Exceptions\FirebaseAuthException $e, \Illuminate\Http\Request $request) {
            return response()->view('errors.auth-unavailable', [], 503);
        });
    })->create();
