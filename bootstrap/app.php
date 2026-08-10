<?php

use App\Exceptions\ExternalIdConflictException;
use App\Exceptions\NoAvailableAmountException;
use App\Http\Middleware\VerifyApiSignature;
use App\Support\ApiError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.signature' => VerifyApiSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::make('validation_failed', 'Input tidak valid.', 422, $e->errors());
        });

        $exceptions->render(function (ExternalIdConflictException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::make('external_id_conflict', $e->getMessage(), 409);
        });

        $exceptions->render(function (NoAvailableAmountException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::make('no_available_amount', $e->getMessage(), 503);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::make('invoice_not_found', 'Invoice tidak ditemukan.', 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $response = ApiError::make('rate_limit_exceeded', 'Terlalu banyak permintaan.', 429);

            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

            if ($retryAfter !== null) {
                $response->headers->set('Retry-After', $retryAfter);
            }

            return $response;
        });
    })->create();
