<?php

namespace App\Services\Contracts;

use App\Models\ChatSession;
use App\Models\ChatMessage;

interface ChatbotServiceInterface
{
    /**
     * Process incoming message and generate response
     */
    public function processMessage(ChatSession $session, string $message, string $customerId = null): ChatMessage;

    /**
     * Create a new chat session
     */
    public function createSession(string $tenantId, string $customerId = null, string $language = 'el'): ChatSession;

    /**
     * Get session statistics
     */
    public function getSessionStats(string $tenantId, int $days = 30): array;
}

