<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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

        // Guarantee JSON 429 responses for WooCommerce routes
        $this->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/woocommerce/*')) {
                $headers = $e->getHeaders() ?? [];
                
                return new JsonResponse([
                    'success'    => false,
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'message'    => 'Too many requests. Please slow down.',
                ], 429, $headers);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception): Response
    {
        // Handle API requests differently
        // Check for API routes by path, Accept header, or Content-Type header
        $isApiRequest = $request->expectsJson() 
            || $request->is('api/*') 
            || $request->header('Accept') === 'application/json'
            || $request->header('Content-Type') === 'application/json';
        
        if ($isApiRequest) {
            return $this->handleApiException($request, $exception);
        }

        // Handle web requests
        return $this->handleWebException($request, $exception);
    }

    /**
     * Handle API exceptions
     */
    private function handleApiException(Request $request, Throwable $exception): Response
    {
        $this->logException($request, $exception);

        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        if ($exception instanceof ThrottleRequestsException) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Please retry later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED'
            ], 429);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'error_code' => 'MODEL_NOT_FOUND'
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
                'error_code' => 'NOT_FOUND'
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'error_code' => 'METHOD_NOT_ALLOWED'
            ], 405);
        }

        if ($exception instanceof QueryException) {
            // For WooCommerce routes, provide more detailed error information
            if ($request->is('api/woocommerce/*')) {
                Log::error('Database error on WooCommerce route', [
                    'exception' => $exception->getMessage(),
                    'sql_state' => $exception->errorInfo[0] ?? null,
                    'driver_code' => $exception->errorInfo[1] ?? null,
                    'url' => $request->fullUrl(),
                    'tenant_id' => $request->header('X-Tenant-Id'),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => app()->environment('production') 
                        ? 'Database error occurred. Please try again later.' 
                        : 'Database error: ' . $exception->getMessage(),
                    'error_code' => 'DATABASE_ERROR',
                    'trace_id' => $this->generateTraceId()
                ], 500);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        }

        if ($exception instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'HTTP error occurred',
                'error_code' => 'HTTP_ERROR',
                'status_code' => $exception->getStatusCode()
            ], $exception->getStatusCode());
        }

        // Generic error response
        // For WooCommerce routes, provide more context
        if ($request->is('api/woocommerce/*')) {
            Log::error('Unhandled exception on WooCommerce route', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'url' => $request->fullUrl(),
                'tenant_id' => $request->header('X-Tenant-Id'),
                'trace' => $exception->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => app()->environment('production') 
                    ? 'An error occurred while processing your request. Please check the logs or try again later.' 
                    : $exception->getMessage(),
                'error_code' => 'INTERNAL_ERROR',
                'trace_id' => $this->generateTraceId(),
                'http_code' => 500
            ], 500);
        }
        
        return response()->json([
            'success' => false,
            'message' => app()->environment('production') 
                ? 'An error occurred while processing your request' 
                : $exception->getMessage(),
            'error_code' => 'INTERNAL_ERROR',
            'trace_id' => $this->generateTraceId()
        ], 500);
    }

    /**
     * Handle web exceptions
     */
    private function handleWebException(Request $request, Throwable $exception): Response
    {
        $this->logException($request, $exception);

        if ($exception instanceof ValidationException) {
            return redirect()->back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        if ($exception instanceof AuthenticationException) {
            return redirect()->route('login')
                ->with('error', 'Please log in to continue');
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->view('errors.404', [], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->view('errors.404', [], 404);
        }

        if ($exception instanceof HttpException) {
            return response()->view('errors.' . $exception->getStatusCode(), [], $exception->getStatusCode());
        }

        // Generic error page
        return response()->view('errors.500', [
            'trace_id' => $this->generateTraceId()
        ], 500);
    }

    /**
     * Log exception with context
     */
    private function logException(Request $request, Throwable $exception): void
    {
        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
            'trace_id' => $this->generateTraceId(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // Add request data for debugging (be careful with sensitive data)
        if (app()->environment('local', 'staging')) {
            $context['request_data'] = $request->except(['password', 'password_confirmation', 'api_key']);
        }

        Log::error('Exception occurred', array_merge($context, [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]));

        // Send critical errors to administrators
        if ($this->isCriticalError($exception)) {
            $this->notifyAdministrators($exception, $context);
        }
    }

    /**
     * Check if error is critical
     */
    private function isCriticalError(Throwable $exception): bool
    {
        $criticalTypes = [
            \Error::class,
            \ParseError::class,
            \TypeError::class,
            \PDOException::class,
        ];

        foreach ($criticalTypes as $type) {
            if ($exception instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notify administrators of critical errors
     */
    private function notifyAdministrators(Throwable $exception, array $context): void
    {
        try {
            $admins = \App\Models\User::where('is_super_admin', true)->get();
            
            if ($admins->isNotEmpty()) {
                Mail::to($admins->pluck('email')->toArray())
                    ->send(new \App\Mail\CriticalErrorMail($exception, $context));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send critical error notification', [
                'original_error' => $exception->getMessage(),
                'notification_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate unique trace ID
     */
    private function generateTraceId(): string
    {
        return 'trace_' . uniqid() . '_' . time();
    }
}
