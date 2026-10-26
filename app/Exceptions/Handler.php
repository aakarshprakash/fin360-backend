<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Always render as JSON — this is an API-only backend.
     * No HTML, no redirects, never calls route('login').
     */
    public function render($request, Throwable $e)
    {
        // ── 401 Unauthenticated ──────────────────────────────
        // Caught UNCONDITIONALLY — no $request->is() check needed.
        // This fires whether the client sends Accept: application/json or not.
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated. Please send a valid Bearer token.',
            ], 401);
        }

        // ── 403 Forbidden ────────────────────────────────────
        if ($e instanceof AuthorizationException) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        // ── 404 Model not found ──────────────────────────────
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'message' => "{$model} not found.",
            ], 404);
        }

        // ── 404 Route not found ──────────────────────────────
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'message' => 'The requested endpoint does not exist.',
            ], 404);
        }

        // ── 405 Method not allowed ───────────────────────────
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'message' => 'HTTP method not allowed for this endpoint.',
            ], 405);
        }

        // ── 422 Validation errors ────────────────────────────
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // ── 500 Any other exception ──────────────────────────
        // In production APP_DEBUG=false, so no stack trace leaks.
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Server error. Please try again later.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return parent::render($request, $e);
    }
}
