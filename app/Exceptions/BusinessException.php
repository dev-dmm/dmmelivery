<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BusinessException extends Exception
{
    protected $errorCode;
    protected $userMessage;
    protected $context;

    public function __construct(
        string $message = 'Business logic error',
        string $errorCode = 'BUSINESS_ERROR',
        string $userMessage = null,
        array $context = [],
        int $code = 400,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->errorCode = $errorCode;
        $this->userMessage = $userMessage ?: $message;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $this->getUserMessage(),
                'error_code' => $this->getErrorCode(),
                'context' => $this->getContext()
            ], $this->getCode());
        }

        return redirect()->back()
            ->with('error', $this->getUserMessage())
            ->with('error_context', $this->getContext());
    }
}
