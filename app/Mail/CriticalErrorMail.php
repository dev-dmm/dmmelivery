<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CriticalErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public Throwable $exception;
    public array $context;

    /**
     * Create a new message instance.
     */
    public function __construct(Throwable $exception, array $context = [])
    {
        $this->exception = $exception;
        $this->context = $context;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('🚨 Critical Error Alert - ' . config('app.name'))
            ->view('emails.critical-error')
            ->with([
                'exception' => $this->exception,
                'context' => $this->context,
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
                'timestamp' => now()->toDateTimeString(),
            ]);
    }
}
