<?php

namespace App\Services;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\PredictiveEta;
use App\Services\Contracts\ChatbotServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotService implements ChatbotServiceInterface
{
    private ?string $openaiApiKey;
    private ?string $openaiEndpoint;
    private string $openaiModel;

    public function __construct()
    {
        $this->openaiApiKey   = config('services.openai.key');
        $this->openaiEndpoint = config('services.openai.endpoint', 'https://api.openai.com/v1/chat/completions');
        $this->openaiModel    = config('services.openai.model', 'gpt-4.1');
    }

    /**
     * Process incoming message and generate response
     */
    public function processMessage(ChatSession $session, string $message, string $customerId = null): ChatMessage
    {
        Log::info("🤖 Processing chatbot message: " . $this->safeLog($message));

        // Attach/refresh session customer if provided
        if ($customerId && !$session->customer_id) {
            $session->customer_id = $customerId;
            $session->save();
        }

        // Create customer message
        $customerMessage = ChatMessage::create([
            'tenant_id'       => $session->tenant_id,
            'chat_session_id' => $session->id,
            'sender_type'     => 'customer',
            'message'         => $message,
            'message_type'    => 'text',
            'is_ai_generated' => false,
        ]);

        // Update session activity
        $session->updateActivity();

        // Analyze message intent + entities
        $intent   = $this->analyzeIntent($message);
        $entities = $this->extractEntities($message);

        // Pre-resolve a shipment if we have a plausible tracking number
        $resolvedShipment = null;
        if (!empty($entities['tracking_numbers'])) {
            foreach ($entities['tracking_numbers'] as $t) {
                $resolvedShipment = $this->findShipmentByTracking($t, $session->tenant_id);
                if ($resolvedShipment) {
                    Log::info("✅ Pre-resolved shipment for tracking [$t]: {$resolvedShipment->status}");
                    break;
                }
            }
        }

        // Debug logging (redacted)
        Log::info("Chatbot processing message (redacted)", [
            'intent'     => $intent,
            'entities'   => $this->redactedEntities($entities),
            'session_id' => $session->id,
            'tenant_id'  => $session->tenant_id,
            'pre_shipment' => $resolvedShipment ? 'found' : 'none',
        ]);

        // Update customer message with analysis
        $customerMessage->update([
            'intent'   => $intent,
            'entities' => $entities,
        ]);

        // Generate AI response (pass pre-resolved shipment)
        $aiResponse = $this->generateAIResponse($session, $message, $intent, $entities, $resolvedShipment);

        // Create AI message
        $aiMessage = ChatMessage::create([
            'tenant_id'         => $session->tenant_id,
            'chat_session_id'   => $session->id,
            'sender_type'       => 'ai',
            'message'           => $aiResponse['message'],
            'message_type'      => $aiResponse['message_type'],
            'metadata'          => $aiResponse['metadata'],
            'is_ai_generated'   => true,
            'confidence_score'  => $aiResponse['confidence_score'],
            'intent'            => $intent,
        ]);

        // Check if escalation is needed
        if ($aiResponse['needs_escalation']) {
            $this->escalateSession($session);
        }

        Log::info("✅ Chatbot response generated with confidence: {$aiResponse['confidence_score']}");

        return $aiMessage;
    }

    /**
     * Analyze message intent (English + Greek, Unicode-safe)
     */
    private function analyzeIntent(string $message): string
    {
        $m = mb_strtolower($message, 'UTF-8');

        $intents = [
            'tracking'      => ['track','tracking','where','status','location','shipping','package','parcel','αποστολή','δέμα','εντοπισμός','κατάσταση'],
            'delivery'      => ['deliver','arrive','eta','when','time','παράδοση','πότε','ώρα'],
            'complaint'     => ['problem','issue','wrong','bad','angry','frustrated','παράπονο','λάθος','πρόβλημα'],
            'escalation'    => ['human','agent','manager','supervisor','help','άνθρωπο','εκπρόσωπο','υπεύθυνο','χειριστή'],
            'greeting'      => ['hello','hi','hey','good morning','good afternoon','γεια','καλημέρα','καλησπέρα'],
            'goodbye'       => ['bye','goodbye','thanks','thank you','done','ευχαριστώ','αντίο','τα λέμε'],
            'shipment_info' => ['order','shipment','package','parcel','αποστολή','παραγγελία'],
        ];

        foreach ($intents as $intent => $patterns) {
            foreach ($patterns as $p) {
                if (str_contains($m, $p)) {
                    return $intent;
                }
            }
        }

        // Stricter tracking-like pattern: 6–32 alphanumeric with at least one digit
        if (preg_match('/\b(?=[A-Z0-9]{6,32}\b)(?=.*\d)[A-Z0-9]+\b/i', $message)) {
            return 'tracking';
        }

        return 'general';
    }

    /**
     * Extract entities (trackers, emails, phones)
     */
    private function extractEntities(string $message): array
    {
        $entities = [];

        // Reasonable tracking candidates: 6–32 alphanum and must include a digit
        if (preg_match_all('/\b(?=[A-Z0-9]{6,32}\b)(?=.*\d)[A-Z0-9]+\b/', $message, $m)) {
            $entities['tracking_numbers'] = array_values(array_unique($m[0]));
        }

        // Emails
        if (preg_match_all('/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/', $message, $m)) {
            $entities['emails'] = $m[0];
        }

        // Phone numbers (conservative)
        if (preg_match_all('/\+?\d[\d\s\-]{7,}\d/', $message, $m)) {
            $entities['phones'] = $m[0];
        }

        return $entities;
    }

    /**
     * Generate AI response
     */
    private function generateAIResponse(
        ChatSession $session,
        string $message,
        string $intent,
        array $entities,
        ?Shipment $shipment = null
    ): array {
        if (!$this->openaiApiKey) {
            Log::info("OpenAI API key not configured, using fallback responses");
            return $this->getFallbackResponse($intent, $entities, $shipment);
        }

        try {
            // Get context data
            $context = $session->getContextForAI();

            // Build system prompt (make “no invention” rule explicit)
            $systemPrompt = $this->buildSystemPrompt($context);

            // Build user message
            $userMessage = $this->buildUserMessage($message, $intent, $entities, $shipment);

            // Conversation history (short, trimmed)
            $conversationHistory = $this->getConversationHistory($session);

            // Build messages array
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];
            foreach ($conversationHistory as $msg) {
                $messages[] = $msg;
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            // Call OpenAI API (with retry)
            $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(30)
                ->retry(2, 200)
                ->post($this->openaiEndpoint, [
                    'model'       => $this->openaiModel,
                    'messages'    => $messages,
                    'max_tokens'  => 500,
                    'temperature' => 0.7,
                ]);

            if (!$response->successful()) {
                Log::warning('OpenAI API error', [
                    'status' => $response->status(),
                    'reason' => $response->reason(),
                ]);
                throw new \RuntimeException('OpenAI API failure');
            }

            $data       = $response->json();
            $aiResponse = $data['choices'][0]['message']['content'] ?? '';

            // Parse with deterministic rules and shipment injected
            return $this->parseAIResponse($aiResponse, $intent, $entities, $session, $shipment);

        } catch (\Throwable $e) {
            Log::error("Chatbot AI error: " . $e->getMessage());
            // Fallback response
            return $this->getFallbackResponse($intent, $entities, $shipment);
        }
    }

    /**
     * Build system prompt for AI
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "You are a helpful customer service chatbot for a delivery tracking company.\n"
            . "- NEVER invent tracking data or shipment details. If shipment context is provided in the user message metadata, use it; otherwise ask for a tracking number.\n"
            . "- Be concise, friendly, and professional.\n"
            . "- If asked for a human, offer escalation.\n"
            . "- When shipment data is available, present: current status, ETA, courier, and any relevant updates.\n\n";

        $prompt .= "Company Context:\n"
            . "- You help customers track shipments and packages.\n"
            . "- You provide delivery status updates and ETAs.\n\n";

        if (isset($context['customer'])) {
            $prompt .= "Customer Information:\n";
            $prompt .= "- Name: " . ($context['customer']['name'] ?? 'N/A') . "\n";
            $prompt .= "- Email: " . ($context['customer']['email'] ?? 'N/A') . "\n\n";
        }

        if (isset($context['recent_shipments']) && !empty($context['recent_shipments'])) {
            $prompt .= "Recent Shipments:\n";
            foreach ($context['recent_shipments'] as $shipment) {
                $prompt .= "- Tracking: {$shipment['tracking_number']}, Status: {$shipment['status']}, Courier: {$shipment['courier']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Guidelines:\n"
            . "- If shipment context is missing, ask for the tracking number.\n"
            . "- If you don't know something, say so and offer to help in other ways.\n"
            . "- For complex issues, suggest escalating to human support.\n"
            . "- Keep responses concise but informative.\n";

        return $prompt;
    }

    /**
     * Build user message for AI (include intent/entities and a shallow snapshot of shipment if present)
     */
    private function buildUserMessage(string $message, string $intent, array $entities, ?Shipment $shipment = null): string
    {
        $userMessage = "Customer message: {$message}\n\n";
        $userMessage .= "Intent: {$intent}\n";

        if (!empty($entities)) {
            $userMessage .= "Entities found: " . json_encode($entities) . "\n";
        }

        if ($shipment) {
            $userMessage .= "Resolved shipment context: "
                . json_encode([
                    'tracking_number'   => $shipment->tracking_number,
                    'status'            => $shipment->status,
                    'courier'           => $shipment->courier?->name ?? 'Unknown',
                    'estimated_delivery'=> $shipment->estimated_delivery?->toIso8601String(),
                ]) . "\n";
        }

        return $userMessage;
    }

    /**
     * Parse AI response and determine message type (deterministic)
     */
    private function parseAIResponse(
        string $response,
        string $intent,
        array $entities,
        ChatSession $session,
        ?Shipment $shipment = null
    ): array {
        $messageType      = 'text';
        $metadata         = [];
        $needsEscalation  = ($intent === 'escalation');
        $confidenceScore  = 0.8;

        if ($shipment) {
            $messageType = 'shipment_info';
            $metadata['shipment'] = [
                'tracking_number'    => $shipment->tracking_number,
                'status'             => $shipment->status,
                'courier'            => $shipment->courier?->name ?? 'Unknown',
                'estimated_delivery' => $shipment->estimated_delivery?->format('Y-m-d H:i:s'),
            ];
            // Replace response with authoritative formatted shipment info
            $response = $this->formatShipmentResponse($shipment, $session);
        } elseif ($intent === 'tracking' && empty($entities['tracking_numbers'])) {
            // Ask for tracking number explicitly (quick action)
            $messageType = 'quick_reply';
            $response = $session->language === 'el'
                ? "Θα χαρώ να βοηθήσω με τον εντοπισμό. Μπορείτε να μου δώσετε τον αριθμό παρακολούθησης;"
                : "I’d be happy to help track this. Could you share your tracking number?";
        }

        // Escalation detection from the generated text (without substring accidents)
        if (preg_match('/\b(human|agent|manager|supervisor)\b/i', $response)) {
            $needsEscalation = true;
            $confidenceScore = 0.9;
        }

        return [
            'message'           => $response,
            'message_type'      => $messageType,
            'metadata'          => $metadata,
            'confidence_score'  => $confidenceScore,
            'needs_escalation'  => $needsEscalation,
        ];
    }

    /**
     * Find shipment by tracking number
     */
    private function findShipmentByTracking(string $trackingNumber, string $tenantId): ?Shipment
    {
        return Shipment::where('tenant_id', $tenantId)
            ->where(function($query) use ($trackingNumber) {
                $query->where('tracking_number', $trackingNumber)
                      ->orWhere('courier_tracking_id', $trackingNumber);
            })
            ->first();
    }

    /**
     * Get conversation history for context
     * - Return last 5 exchanges (10 messages), trimmed to keep token usage reasonable
     */
    private function getConversationHistory(ChatSession $session): array
    {
        $history = [];

        $messages = $session->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        foreach ($messages as $message) {
            $content = $this->trimForContext($message->message, 800); // trim long messages
            if ($message->sender_type === 'customer') {
                $history[] = ['role' => 'user', 'content' => $content];
            } elseif ($message->sender_type === 'ai') {
                $history[] = ['role' => 'assistant', 'content' => $content];
            }
        }

        return $history;
    }

    /**
     * Format shipment response with actual data (EL/EN) and timezone awareness
     */
    private function formatShipmentResponse(Shipment $shipment, ChatSession $session): string
    {
        $language = $session->language ?? 'en';
        $tz = $session->timezone ?? config('app.timezone', 'Europe/Athens');

        if ($language === 'el') {
            $response  = "📦 **ΕΝΗΜΕΡΩΣΗ ΚΑΤΑΣΤΑΣΗΣ ΑΠΟΣΤΟΛΗΣ**\n\n";
            $response .= "🔍 **Αριθμός Παρακολούθησης:** {$shipment->tracking_number}\n\n";
            $response .= "📊 **Τρέχουσα Κατάσταση:** " . $this->translateStatus($shipment->status, 'el') . "\n\n";
            if ($shipment->courier) {
                $response .= "🚚 **Μεταφορέας:** {$shipment->courier->name}\n\n";
            }
            if ($shipment->estimated_delivery) {
                $response .= "📅 **Εκτιμώμενη Παράδοση:** "
                    . $shipment->estimated_delivery->setTimezone($tz)->format('d/m/Y \σ\τ\ις H:i')
                    . "\n\n";
            }
            if ($shipment->shipping_address) {
                $response .= "📍 **Διεύθυνση Αποστολής:** {$shipment->shipping_address}\n\n";
            }
            $response .= "\n\n💬 Αν χρειάζεστε περισσότερες λεπτομέρειες ή έχετε ερωτήσεις, ενημερώστε με!";
            return $response;
        }

        // English (default)
        $response  = "📦 **SHIPMENT STATUS UPDATE**\n\n";
        $response .= "🔍 **Tracking Number:** {$shipment->tracking_number}\n\n";
        $response .= "📊 **Current Status:** " . ucfirst(str_replace('_', ' ', $shipment->status)) . "\n\n";
        if ($shipment->courier) {
            $response .= "🚚 **Courier:** {$shipment->courier->name}\n\n";
        }
        if ($shipment->estimated_delivery) {
            $response .= "📅 **Estimated Delivery:** "
                . $shipment->estimated_delivery->setTimezone($tz)->format('M j, Y \a\t g:i A')
                . "\n\n";
        }
        if ($shipment->shipping_address) {
            $response .= "📍 **Shipping Address:** {$shipment->shipping_address}\n\n";
        }
        $response .= "\n💬 If you need more detailed information or have any questions, please let me know!";
        return $response;
    }

    /**
     * Translate status to Greek
     */
    private function translateStatus(string $status, string $language): string
    {
        if ($language !== 'el') {
            return ucfirst(str_replace('_', ' ', $status));
        }

        $translations = [
            'pending'     => 'Εκκρεμεί',
            'in_transit'  => 'Σε Μεταφορά',
            'delivered'   => 'Παραδόθηκε',
            'failed'      => 'Αποτυχία',
            'cancelled'   => 'Ακυρώθηκε',
            'processing'  => 'Επεξεργασία',
        ];

        return $translations[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Fallback response (uses existing entities/shipment if present)
     */
    private function getFallbackResponse(string $intent, array $entities, ?Shipment $shipment = null): array
    {
        // If we already have a shipment, prefer authoritative response
        if ($shipment) {
            return [
                'message'          => $this->formatShipmentResponse($shipment, app(ChatSession::class)), // session not available here normally
                'message_type'     => 'shipment_info',
                'metadata'         => [
                    'shipment' => [
                        'tracking_number'    => $shipment->tracking_number,
                        'status'             => $shipment->status,
                        'courier'            => $shipment->courier?->name ?? 'Unknown',
                        'estimated_delivery' => $shipment->estimated_delivery?->format('Y-m-d H:i:s'),
                    ],
                ],
                'confidence_score' => 0.7,
                'needs_escalation' => false,
            ];
        }

        if ($intent === 'tracking') {
            if (!empty($entities['tracking_numbers'])) {
                return [
                    'message'          => "I’m checking your tracking number now…",
                    'message_type'     => 'quick_reply',
                    'metadata'         => ['tracking_numbers' => $entities['tracking_numbers']],
                    'confidence_score' => 0.6,
                    'needs_escalation' => false,
                ];
            }
            return [
                'message'          => "I'd be happy to help. Could you share your tracking number?",
                'message_type'     => 'quick_reply',
                'metadata'         => [],
                'confidence_score' => 0.5,
                'needs_escalation' => false,
            ];
        }

        $fallbackMessages = [
            'delivery'   => "I can help with delivery information. What would you like to know?",
            'complaint'  => "I’m sorry for the trouble. I can connect you with our support team to assist further.",
            'escalation' => "I’ll connect you with a human agent who can assist you in detail.",
            'greeting'   => "Hello! I’m here to help with shipment tracking and delivery questions. How can I assist you today?",
            'goodbye'    => "Thank you for contacting us! Have a great day!",
            'general'    => "I’m here to help with your shipment and delivery questions. What can I assist you with?",
        ];

        $message = $fallbackMessages[$intent] ?? $fallbackMessages['general'];

        return [
            'message'          => $message,
            'message_type'     => 'text',
            'metadata'         => [],
            'confidence_score' => 0.5,
            'needs_escalation' => in_array($intent, ['complaint','escalation'], true),
        ];
    }

    /**
     * Escalate session to human agent (guard against double escalation)
     */
    private function escalateSession(ChatSession $session): void
    {
        if ($session->status !== 'escalated') {
            $session->escalate();

            ChatMessage::create([
                'tenant_id'         => $session->tenant_id,
                'chat_session_id'   => $session->id,
                'sender_type'       => 'ai',
                'message'           => "I'm connecting you with a human agent who can provide more detailed assistance. Please hold on while we transfer you.",
                'message_type'      => 'text',
                'is_ai_generated'   => true,
                'confidence_score'  => 1.0,
            ]);

            Log::info("🚨 Chat session {$session->id} escalated to human agent");
        }
    }

    /**
     * Create new chat session
     */
    public function createSession(string $tenantId, string $customerId = null, string $language = 'el'): ChatSession
    {
        return ChatSession::create([
            'tenant_id'        => $tenantId,
            'customer_id'      => $customerId,
            'session_id'       => Str::uuid(),
            'status'           => 'active',
            'language'         => $language,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(string $tenantId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $sessions = ChatSession::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $resolvedDurations = $sessions->whereNotNull('resolved_at')->map(function ($s) {
            return $s->created_at->diffInMinutes($s->resolved_at);
        });

        return [
            'total_sessions'       => $sessions->count(),
            'active_sessions'      => $sessions->where('status', 'active')->count(),
            'resolved_sessions'    => $sessions->where('status', 'resolved')->count(),
            'escalated_sessions'   => $sessions->where('status', 'escalated')->count(),
            'avg_satisfaction'     => $sessions->whereNotNull('satisfaction_rating')->avg('satisfaction_rating') ?? 0,
            'avg_duration_minutes' => $resolvedDurations->count() ? $resolvedDurations->avg() : 0,
        ];
    }

    /* ---------------------------- Helpers ----------------------------- */

    /** Redact obvious PII for logs */
    private function safeLog(string $text): string
    {
        // mask emails
        $text = preg_replace(
            '/([A-Za-z0-9._%+\-])([A-Za-z0-9._%+\-]*)(@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})/',
            '$1***$3',
            $text
        );
        // mask long phone-like numbers
        $text = preg_replace('/\b(\+?\d[\d\s\-]{5,}\d)\b/', '***redacted-phone***', $text);
        // mask tracking-like tokens
        $text = preg_replace('/\b[A-Z0-9]{6,32}\b/', '***tracking***', $text);

        return $text;
    }

    /** Redact entities for structured log */
    private function redactedEntities(array $entities): array
    {
        $copy = $entities;

        if (!empty($copy['tracking_numbers'])) {
            $copy['tracking_numbers'] = array_fill(0, count($copy['tracking_numbers']), '***tracking***');
        }
        if (!empty($copy['emails'])) {
            $copy['emails'] = array_fill(0, count($copy['emails']), '***email***');
        }
        if (!empty($copy['phones'])) {
            $copy['phones'] = array_fill(0, count($copy['phones']), '***phone***');
        }

        return $copy;
    }

    /** Trim large messages to avoid token bloat */
    private function trimForContext(string $text, int $maxLen = 800): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen - 3, 'UTF-8') . '...';
    }
}
