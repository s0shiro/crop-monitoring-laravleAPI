<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Use different schedule frequencies based on environment
        if (app()->environment('local')) {
            // Run every minute in local development
            $schedule->command('crop:check-harvest-status')->everyMinute();
        } else {
            // Run daily at midnight in production
            $schedule->command('crop:check-harvest-status')->daily();
        }
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Register your custom JWT middleware
        $middleware->append(\App\Http\Middleware\JwtCookieAuth::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
