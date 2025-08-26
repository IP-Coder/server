<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\IsAdmin;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    // Append global middleware
    $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

    // Register aliases
    $middleware->alias([
        // 'auth' => \App\Http\Middleware\Authenticate::class,
        'is_admin' => IsAdmin::class, // ✅ custom middleware
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();