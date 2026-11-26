<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'wheel/*/spin',
            'wheel/*/claim-whatsapp',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsureRestaurantIsActive::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\EnsureRestaurantIsActive::class,
        ]);

        $middleware->statefulApi();
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);

        $middleware->appendToPriorityList(
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\EnsureRestaurantIsActive::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // 403 Forbidden / Access Denied
        $render403 = function (\Throwable $e, Request $request) {
            $message = $e->getMessage() ?: 'ليس لديك الصلاحية للوصول إلى هذه الصفحة أو هذا الإجراء.';
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::render('Errors/403', [
                    'message' => $e->getMessage() ?: null,
                ])->toResponse($request)->setStatusCode(403);
            }

            return response()->view('errors.403', [
                'exception' => $e,
                'message' => $e->getMessage() ?: null,
            ], 403);
        };

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) use ($render403) {
            return $render403($e, $request);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) use ($render403) {
            return $render403($e, $request);
        });

        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) use ($render403) {
            return $render403($e, $request);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) use ($render403) {
            if ($e->getStatusCode() === 403) {
                return $render403($e, $request);
            }

            return null;
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'العنصر المطلوب غير موجود أو غير متاح حالياً.',
                ], 404);
            }

            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::render('Errors/404')->toResponse($request)->setStatusCode(404);
            }

            return response()->view('errors.404', [], 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'المورد المطلوب غير موجود أو انتهت صلاحيته.',
                ], 404);
            }

            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::render('Errors/404')->toResponse($request)->setStatusCode(404);
            }

            return response()->view('errors.404', [], 404);
        });
    })->create();
