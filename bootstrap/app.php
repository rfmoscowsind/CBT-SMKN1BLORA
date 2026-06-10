<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['kelola/data/jadwal-ujian']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) return null;
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : ($e instanceof ValidationException ? 422 : 500);
            $details = $e instanceof ValidationException ? $e->errors() : null;
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => $status >= 500 ? 'Internal server error' : ($e->getMessage() ?: 'Request failed'),
                'code' => $status,
                'details' => $details,
            ], $status);
        });
    })->create();
