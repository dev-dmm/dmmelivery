<?php

namespace App\Services;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\PredictiveEta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotService
{
    private ?string $openaiApiKey;
    private ?string $openaiEndpoint;

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.key');
        $this->openaiEndpoint = config('services.openai.endpoint', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * Process incoming message and generate response
     */
    public function processMessage(ChatSession $session, string $message, string $customerId = null): ChatMessage
    {
        Log::info("🤖 Processing chatbot message: {$message}");

        // Create customer message
        $customerMessage = ChatMessage::create([
            'tenant_id' => $session->tenant_id,
            'chat_session_id' => $session->id,
            'sender_type' => 'customer',
            'message' => $message,
            'message_type' => 'text',
            'is_ai_generated' => false,
        ]);

        // Update session activity
        $session->updateActivity();

        // Analyze message intent
        $intent = $this->analyzeIntent($message);
        $entities = $this->extractEntities($message);

        // Update customer message with analysis
        $customerMessage->update([
            'intent' => $intent,
            'entities' => $entities,
        ]);

        // Generate AI response
        $aiResponse = $this->generateAIResponse($session, $message, $intent, $entities);

        // Create AI message
        $aiMessage = ChatMessage::create([
            'tenant_id' => $session->tenant_id,
            'chat_session_id' => $session->id,
            'sender_type' => 'ai',
            'message' => $aiResponse['message'],
            'message_type' => $aiResponse['message_type'],
            'metadata' => $aiResponse['metadata'],
            'is_ai_generated' => true,
            'confidence_score' => $aiResponse['confidence_score'],
            'intent' => $intent,
        ]);

        // Check if escalation is needed
        if ($aiResponse['needs_escalation']) {
            $this->escalateSession($session);
        }

        Log::info("✅ Chatbot response generated with confidence: {$aiResponse['confidence_score']}");

        return $aiMessage;
    }

    /**
     * Analyze message intent
     */
    private function analyzeIntent(string $message): string
    {
        $message = strtolower($message);
        
        // Intent patterns
        $intents = [
            'tracking' => ['track', 'where', 'status', 'location', 'shipping'],
            'delivery' => ['deliver', 'arrive', 'eta', 'when', 'time'],
            'complaint' => ['problem', 'issue', 'wrong', 'bad', 'angry', 'frustrated'],
            'escalation' => ['human', 'agent', 'manager', 'supervisor', 'help'],
            'greeting' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon'],
            'goodbye' => ['bye', 'goodbye', 'thanks', 'thank you', 'done'],
            'shipment_info' => ['package', 'parcel', 'order', 'shipment'],
        ];

        foreach ($intents as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($message, $pattern)) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * Extract entities from message
     */
    private function extractEntities(string $message): array
    {
        $entities = [];

        // Extract tracking numbers
        if (preg_match_all('/\b[A-Z0-9]{8,}\b/', $message, $matches)) {
            $entities['tracking_numbers'] = $matches[0];
        }

        // Extract email addresses
        if (preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message, $matches)) {
            $entities['emails'] = $matches[0];
        }

        // Extract phone numbers
        if (preg_match_all('/\b\d{10,}\b/', $message, $matches)) {
            $entities['phones'] = $matches[0];
        }

        return $entities;
    }

    /**
     * Generate AI response
     */
    private function generateAIResponse(ChatSession $session, string $message, string $intent, array $entities): array
    {
        if (!$this->openaiApiKey) {
            Log::info("OpenAI API key not configured, using fallback responses");
            return $this->getFallbackResponse($intent, $entities);
        }

        try {
            // Get context data
            $context = $session->getContextForAI();
            
            // Build system prompt
            $systemPrompt = $this->buildSystemPrompt($context);
            
            // Build user message
            $userMessage = $this->buildUserMessage($message, $intent, $entities);

            // Call OpenAI API
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->openaiEndpoint, [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->body());
            }

            $data = $response->json();
            $aiResponse = $data['choices'][0]['message']['content'] ?? '';

            // Parse response and determine message type
            $parsedResponse = $this->parseAIResponse($aiResponse, $intent, $entities);

            return $parsedResponse;

        } catch (\Exception $e) {
            Log::error("Chatbot AI error: " . $e->getMessage());
            
            // Fallback response
            return $this->getFallbackResponse($intent, $entities);
        }
    }

    /**
     * Build system prompt for AI
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "You are a helpful customer service chatbot for a delivery tracking company. ";
        $prompt .= "You help customers with shipment tracking, delivery information, and general inquiries.\n\n";
        
        $prompt .= "Company Context:\n";
        $prompt .= "- You help customers track their shipments and packages\n";
        $prompt .= "- You can provide delivery status updates and ETAs\n";
        $prompt .= "- You should be friendly, helpful, and professional\n";
        $prompt .= "- If you cannot help, escalate to human support\n\n";

        if (isset($context['customer'])) {
            $prompt .= "Customer Information:\n";
            $prompt .= "- Name: {$context['customer']['name']}\n";
            $prompt .= "- Email: {$context['customer']['email']}\n\n";
        }

        if (isset($context['recent_shipments']) && !empty($context['recent_shipments'])) {
            $prompt .= "Recent Shipments:\n";
            foreach ($context['recent_shipments'] as $shipment) {
                $prompt .= "- Tracking: {$shipment['tracking_number']}, Status: {$shipment['status']}, Courier: {$shipment['courier']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Guidelines:\n";
        $prompt .= "- Always be helpful and friendly\n";
        $prompt .= "- Provide accurate information when available\n";
        $prompt .= "- If you don't know something, say so and offer to help in other ways\n";
        $prompt .= "- For complex issues, suggest escalating to human support\n";
        $prompt .= "- Keep responses concise but informative\n";

        return $prompt;
    }

    /**
     * Build user message for AI
     */
    private function buildUserMessage(string $message, string $intent, array $entities): string
    {
        $userMessage = "Customer message: {$message}\n\n";
        $userMessage .= "Intent: {$intent}\n";
        
        if (!empty($entities)) {
            $userMessage .= "Entities found: " . json_encode($entities) . "\n";
        }

        return $userMessage;
    }

    /**
     * Parse AI response and determine message type
     */
    private function parseAIResponse(string $response, string $intent, array $entities): array
    {
        $messageType = 'text';
        $metadata = [];
        $needsEscalation = false;
        $confidenceScore = 0.8;

        // Check if response contains shipment information
        if (str_contains($response, 'tracking') || str_contains($response, 'shipment')) {
            $messageType = 'shipment_info';
            
            // Try to find shipment data
            if (!empty($entities['tracking_numbers'])) {
                $trackingNumber = $entities['tracking_numbers'][0];
                $shipment = $this->findShipmentByTracking($trackingNumber);
                
                if ($shipment) {
                    $metadata['shipment'] = [
                        'tracking_number' => $shipment->tracking_number,
                        'status' => $shipment->status,
                        'courier' => $shipment->courier?->name ?? 'Unknown',
                        'estimated_delivery' => $shipment->estimated_delivery?->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        // Check if escalation is needed
        if (str_contains($response, 'escalate') || str_contains($response, 'human') || $intent === 'escalation') {
            $needsEscalation = true;
            $confidenceScore = 0.9;
        }

        // Check if response suggests quick actions
        if (str_contains($response, 'track') || str_contains($response, 'status')) {
            $messageType = 'quick_reply';
        }

        return [
            'message' => $response,
            'message_type' => $messageType,
            'metadata' => $metadata,
            'confidence_score' => $confidenceScore,
            'needs_escalation' => $needsEscalation,
        ];
    }

    /**
     * Find shipment by tracking number
     */
    private function findShipmentByTracking(string $trackingNumber): ?Shipment
    {
        return Shipment::where('tracking_number', $trackingNumber)
            ->orWhere('courier_tracking_id', $trackingNumber)
            ->first();
    }

    /**
     * Get fallback response when AI fails
     */
    private function getFallbackResponse(string $intent, array $entities): array
    {
        $fallbackMessages = [
            'tracking' => "I'd be happy to help you track your shipment. Could you please provide your tracking number?",
            'delivery' => "I can help you with delivery information. What would you like to know?",
            'complaint' => "I understand you're having an issue. Let me connect you with our support team who can better assist you.",
            'escalation' => "I'll connect you with a human agent who can provide more detailed assistance.",
            'greeting' => "Hello! I'm here to help you with your shipment tracking and delivery questions. How can I assist you today?",
            'goodbye' => "Thank you for contacting us! Have a great day!",
            'general' => "I'm here to help you with your shipment and delivery questions. What can I assist you with?",
        ];

        $message = $fallbackMessages[$intent] ?? $fallbackMessages['general'];
        
        return [
            'message' => $message,
            'message_type' => 'text',
            'metadata' => [],
            'confidence_score' => 0.5,
            'needs_escalation' => $intent === 'complaint' || $intent === 'escalation',
        ];
    }

    /**
     * Escalate session to human agent
     */
    private function escalateSession(ChatSession $session): void
    {
        $session->escalate();
        
        // Create escalation message
        ChatMessage::create([
            'tenant_id' => $session->tenant_id,
            'chat_session_id' => $session->id,
            'sender_type' => 'ai',
            'message' => "I'm connecting you with a human agent who can provide more detailed assistance. Please hold on while we transfer you.",
            'message_type' => 'text',
            'is_ai_generated' => true,
            'confidence_score' => 1.0,
        ]);

        Log::info("🚨 Chat session {$session->id} escalated to human agent");
    }

    /**
     * Create new chat session
     */
    public function createSession(string $tenantId, string $customerId = null, string $language = 'en'): ChatSession
    {
        return ChatSession::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'session_id' => Str::uuid(),
            'status' => 'active',
            'language' => $language,
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

        return [
            'total_sessions' => $sessions->count(),
            'active_sessions' => $sessions->where('status', 'active')->count(),
            'resolved_sessions' => $sessions->where('status', 'resolved')->count(),
            'escalated_sessions' => $sessions->where('status', 'escalated')->count(),
            'avg_satisfaction' => $sessions->whereNotNull('satisfaction_rating')->avg('satisfaction_rating'),
            'avg_duration_minutes' => $sessions->whereNotNull('resolved_at')->avg(function($session) {
                return $session->created_at->diffInMinutes($session->resolved_at);
            }),
        ];
    }
}
