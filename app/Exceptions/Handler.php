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
use App\Exceptions\OwnerPanelAccessDeniedException;
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
            // Extra logging for Livewire component not found in Filament
            if ($e instanceof \Livewire\Exceptions\ComponentNotFoundException) {
                try {
                    \Log::error('[Livewire][ComponentNotFound]', [
                        'message' => $e->getMessage(),
                        'url' => request()?->fullUrl(),
                        'user_id' => auth()->id(),
                        'user_email' => auth()->user()?->email,
                        'store_id' => auth()->user()?->store_id,
                    ]);
                } catch (\Throwable $ie) {
                    // ignore
                }
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        // Handle OwnerPanelAccessDeniedException with detailed messages
        if ($e instanceof OwnerPanelAccessDeniedException) {
            return $this->renderOwnerPanelAccessDenied($request, $e);
        }

        // Handle ValidationException separately to use invalidJson method
        if ($e instanceof ValidationException) {
            return $this->invalidJson($request, $e);
        }

        // Handle API requests with JSON responses
        if ($this->isApiRequest($request)) {
            return $this->renderApiException($request, $e);
        }

        // Handle Filament requests with better error messages
        if ($this->isFilamentRequest($request)) {
            return $this->renderFilamentException($request, $e);
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

        return redirect()->guest($exception->redirectTo() ?? route('landing.login'));
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

        if ($e instanceof OwnerPanelAccessDeniedException) {
            $details = array_merge($details, $e->toArray());
        }

        if (config('app.debug')) {
            $details['exception'] = get_class($e);
            $details['file'] = $e->getFile();
            $details['line'] = $e->getLine();
            $details['trace'] = collect($e->getTrace())->take(5)->toArray();
        }

        return $details;
    }

    /**
     * Render OwnerPanelAccessDeniedException with informative message
     */
    protected function renderOwnerPanelAccessDenied(Request $request, OwnerPanelAccessDeniedException $e): Response
    {
        // For Filament requests, show error page with detailed message
        if ($this->isFilamentRequest($request)) {
            // Flash error message to session for Filament to display
            session()->flash('error', $e->getMessage());
            
            // Log the access denial with full context
            \Log::warning('OwnerPanel access denied', [
                'reason' => $e->getReason(),
                'user_email' => $e->getUserEmail(),
                'store_id' => $e->getStoreId(),
                'user_roles' => $e->getUserRoles(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            // Return 403 with custom view or redirect to login with message
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'OWNER_PANEL_ACCESS_DENIED',
                        'message' => $e->getMessage(),
                        'reason' => $e->getReason(),
                        'user_email' => $e->getUserEmail(),
                    ]
                ], 403);
            }

            // For Filament, redirect to login with error message
            return redirect()
                ->route('filament.owner.auth.login')
                ->with('error', $e->getMessage());
        }

        // For API requests
        if ($this->isApiRequest($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'OWNER_PANEL_ACCESS_DENIED',
                    'message' => $e->getMessage(),
                    'reason' => $e->getReason(),
                ]
            ], 403);
        }

        // Default: use parent handler but with custom message
        return parent::render($request, $e);
    }

    /**
     * Render Filament-specific exceptions with better messages
     */
    protected function renderFilamentException(Request $request, Throwable $e): Response
    {
        // Handle AccessDeniedHttpException for Filament
        if ($e instanceof AccessDeniedHttpException) {
            $message = $e->getMessage();
            
            // If message is generic, provide more context
            if (in_array($message, ['Forbidden', 'Unauthorized access to this panel.', 'Access denied'])) {
                $user = auth()->user();
                $userRoles = $user ? $user->getRoleNames()->toArray() : [];
                
                $detailedMessage = 'Anda tidak memiliki izin untuk mengakses halaman ini. ';
                
                if ($user) {
                    $detailedMessage .= 'Akun Anda (' . $user->email . ') memiliki role: ' . implode(', ', $userRoles) . '. ';
                    
                    if (empty($userRoles)) {
                        $detailedMessage .= 'Silakan hubungi administrator untuk menetapkan role yang sesuai.';
                    } else {
                        $detailedMessage .= 'Jika Anda yakin seharusnya memiliki akses, silakan hubungi administrator.';
                    }
                } else {
                    $detailedMessage .= 'Silakan login terlebih dahulu.';
                }

                session()->flash('error', $detailedMessage);
                
                \Log::warning('Filament access denied', [
                    'message' => $message,
                    'user_id' => $user?->id,
                    'user_email' => $user?->email,
                    'user_roles' => $userRoles,
                    'url' => $request->fullUrl(),
                ]);
            }

            // Redirect to appropriate login page based on panel
            if ($request->is('owner-panel/*') || $this->isOwnerPathRequest($request)) {
                return redirect()
                    ->route('filament.owner.auth.login')
                    ->with('error', $message ?: 'Anda tidak memiliki izin untuk mengakses dashboard toko.');
            }

            if ($request->is('admin-panel/*') || $this->isAdminPathRequest($request)) {
                return redirect()
                    ->route('filament.admin.auth.login')
                    ->with('error', $message ?: 'Anda tidak memiliki izin untuk mengakses panel admin.');
            }
        }

        return parent::render($request, $e);
    }

    /**
     * Check if request is for Filament panel
     */
    protected function isFilamentRequest(Request $request): bool
    {
        return $request->is('owner-panel/*') ||
               $request->is('admin-panel/*') ||
               $request->routeIs('filament.*') ||
               $this->isOwnerPathRequest($request) ||
               $this->isAdminPathRequest($request);
    }

    /**
     * Determine if request targets the owner panel path.
     */
    protected function isOwnerPathRequest(Request $request): bool
    {
        $ownerPath = $this->getOwnerPath();

        return $request->is($ownerPath) || $request->is($ownerPath . '/*');
    }

    /**
     * Determine if request targets the admin panel path.
     */
    protected function isAdminPathRequest(Request $request): bool
    {
        $adminPath = $this->getAdminPath();

        return $request->is($adminPath) || $request->is($adminPath . '/*');
    }

    protected function getOwnerPath(): string
    {
        $defaultOwnerUrl = rtrim(config('app.url'), '/') . '/owner';

        return trim(parse_url(config('app.owner_url', $defaultOwnerUrl), PHP_URL_PATH) ?: 'owner', '/');
    }

    protected function getAdminPath(): string
    {
        $defaultAdminUrl = rtrim(config('app.url'), '/') . '/admin';

        return trim(parse_url(config('app.admin_url', $defaultAdminUrl), PHP_URL_PATH) ?: 'admin', '/');
    }
}
