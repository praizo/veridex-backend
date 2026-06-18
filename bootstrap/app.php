<?php

use App\Exceptions\InvoiceStateException;
use App\Exceptions\NrsApiException;
use App\Exceptions\NrsConnectionException;
use App\Http\Middleware\EnsureOrganizationScope;
use App\Http\Middleware\VerifyNrsWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );
        $middleware->alias([
            'org.scope' => EnsureOrganizationScope::class,
            'verify.nrs.webhook' => VerifyNrsWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NrsConnectionException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'code' => 'NRS_CONNECTION_UNAVAILABLE',
                'message' => $e->getMessage(),
                'retryable' => true,
            ], 503);
        });

        $exceptions->render(function (NrsApiException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;

            return response()->json([
                'code' => 'NRS_API_ERROR',
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
                'retryable' => in_array($status, [408, 429, 500, 502, 503, 504], true),
            ], $status);
        });

        $exceptions->render(function (InvoiceStateException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'code' => 'INVOICE_STATE_ERROR',
                'message' => $e->getMessage(),
            ], 409);
        });
    })->create();
