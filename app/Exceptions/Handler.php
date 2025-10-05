<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        // Handle ValidationException separately to use invalidJson method
        if ($e instanceof ValidationException) {
            return $this->invalidJson($request, $e);
        }

        // Handle API requests with JSON responses
        if ($this->isApiRequest($request)) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert a validation exception into a validation error response.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {

        if ($this->isApiRequest($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'details' => $exception->errors()
                ]
            ], $exception->status);
        }

        return parent::invalidJson($request, $exception);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        if ($this->isApiRequest($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required to access this resource'
                ]
            ], 401);
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }

    /**
     * Render API exception responses
     */
    protected function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        $statusCode = $this->getStatusCode($e);
        $errorCode = $this->getErrorCode($e);
        $message = $this->getErrorMessage($e);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message
            ]
        ], $statusCode);
    }

    /**
     * Check if request is for API
     */
    protected function isApiRequest(Request $request): bool
    {
        $isApi = $request->is('api/*') ||
            $request->expectsJson() ||
            str_contains($request->header('Accept', ''), 'application/json') ||
            str_contains($request->header('Content-Type', ''), 'application/json');


        return $isApi;
    }

    /**
     * Get HTTP status code for exception
     */
    protected function getStatusCode(Throwable $e): int
    {
        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof AccessDeniedHttpException) {
            return 403;
        }

        if ($e instanceof NotFoundHttpException) {
            return 404;
        }

        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Get error code for exception
     */
    protected function getErrorCode(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return 'VALIDATION_FAILED';
        }

        if ($e instanceof AuthenticationException) {
            return 'UNAUTHENTICATED';
        }

        if ($e instanceof AccessDeniedHttpException) {
            return 'UNAUTHORIZED';
        }

        if ($e instanceof NotFoundHttpException) {
            return 'RESOURCE_NOT_FOUND';
        }

        return 'INTERNAL_SERVER_ERROR';
    }

    /**
     * Get error message for exception
     */
    protected function getErrorMessage(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return 'The given data was invalid.';
        }

        if ($e instanceof AuthenticationException) {
            return 'Authentication required to access this resource.';
        }

        if ($e instanceof AccessDeniedHttpException) {
            return 'You do not have permission to access this resource.';
        }

        if ($e instanceof NotFoundHttpException) {
            return 'The requested resource was not found.';
        }

        return config('app.debug') ? $e->getMessage() : 'An error occurred while processing your request.';
    }

    /**
     * Get error details for exception
     */
    protected function getErrorDetails(Throwable $e): array
    {
        $details = [];

        if ($e instanceof ValidationException) {
            $details['validation_errors'] = $e->errors();
        }

        if ($e instanceof AuthenticationException) {
            $details['guards'] = $e->guards();
        }

        if (config('app.debug')) {
            $details['exception'] = get_class($e);
            $details['file'] = $e->getFile();
            $details['line'] = $e->getLine();
            $details['trace'] = collect($e->getTrace())->take(5)->toArray();
        }

        return $details;
    }
}
