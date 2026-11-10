<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Storefront\Http\Middleware\ValidateStorefrontHost;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
        $middleware->web(append: [ValidateStorefrontHost::class]);

        // Payment webhooks are server-to-server POSTs from the gateway; they carry no CSRF
        // token and are authenticated by signature verification in the controller.
        $middleware->validateCsrfTokens(except: [
            'payments/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Sign in again.',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()
                ->route('login')
                ->with('status', 'Your session has expired. Please sign in again.');
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() === 419 && ! $request->user()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your session has expired. Sign in again.',
                        'redirect' => route('login'),
                    ], 401);
                }

                return redirect()
                    ->route('login')
                    ->with('status', 'Your session has expired. Please sign in again.');
            }

            $isMissingBranch = $exception->getStatusCode() === 403
                && str_contains(strtolower($exception->getMessage()), 'active branch');

            if (! $isMissingBranch || ! $request->user()) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Select an active branch to continue.',
                    'redirect' => route('admin.home'),
                ], 409);
            }

            return redirect()
                ->route('admin.home')
                ->with('status', 'Select an active branch to continue.');
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
