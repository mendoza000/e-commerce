<?php

use App\Domain\Exceptions\InvalidOrderTransition;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA mode: requests from the configured stateful domains get
        // the session cookie + CSRF treatment, so the admin panel authenticates
        // with a first-party session instead of a bearer token.
        $middleware->statefulApi();

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'message' => 'Resource not found.',
                    'code' => 'not_found',
                ],
            ], 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'validation_error',
                    'fields' => $e->errors(),
                ],
            ], 422);
        });

        // An order was asked for a status change the business does not allow.
        // That is a rejected request, not a server fault.
        $exceptions->render(function (InvalidOrderTransition $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'invalid_order_transition',
                ],
            ], 422);
        });

        // No session (or an expired one) on a protected admin route. Laravel
        // does not convert this to an HttpException before the render
        // callbacks run, so it needs its own handler.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'message' => 'No has iniciado sesión.',
                    'code' => 'unauthenticated',
                ],
            ], 401);
        });

        // Everything that reached an abort() or a failed policy check: 403 from
        // the role middleware and the policies, 429 from the throttlers, and so
        // on. Registered after the specific handlers above so those still win,
        // and before the catch-all below so a 403 is never reported as a 500.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            return response()->json([
                'error' => [
                    'message' => $e->getMessage() ?: 'No se pudo procesar la solicitud.',
                    'code' => match ($status) {
                        401 => 'unauthenticated',
                        403 => 'forbidden',
                        429 => 'too_many_requests',
                        default => 'http_error',
                    },
                ],
            ], $status);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'message' => 'Server error.',
                    'code' => 'server_error',
                ],
            ], 500);
        });
    })->create();
