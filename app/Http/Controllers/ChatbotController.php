<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    private ChatbotService $chatbotService;

    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Display chatbot dashboard
     */
    public function index(): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        $sessions = ChatSession::where('tenant_id', $tenant->id)
            ->with(['customer', 'messages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->latest('last_activity_at')
            ->paginate(20);

        $stats = $this->chatbotService->getSessionStats($tenant->id);

        return Inertia::render('Chatbot/Index', [
            'sessions' => $sessions,
            'stats' => $stats,
        ]);
    }

    /**
     * Start new chat session
     */
    public function startSession(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $tenant = $user->currentTenant();
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenant found for user',
                ], 400);
            }
            
            $request->validate([
                'customer_id' => 'nullable|uuid|exists:customers,id',
                'language' => 'string|in:en,el,es,fr,de',
            ]);

            $session = $this->chatbotService->createSession(
                $tenant->id,
                $request->customer_id,
                $request->language ?? 'en'
            );

            return response()->json([
                'success' => true,
                'message' => 'Chat session started',
                'data' => [
                    'session' => [
                        'id' => $session->id,
                        'session_id' => $session->session_id,
                        'status' => $session->status,
                        'language' => $session->language,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Chatbot session creation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send message to chatbot
     */
    public function sendMessage(Request $request, string $sessionId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $request->validate([
            'message' => 'required|string|max:1000',
            'customer_id' => 'nullable|uuid|exists:customers,id',
        ]);

        $session = ChatSession::where('tenant_id', $tenant->id)
            ->where('id', $sessionId)
            ->firstOrFail();

        try {
            $aiMessage = $this->chatbotService->processMessage(
                $session,
                $request->message,
                $request->customer_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Message processed successfully',
                'data' => [
                    'ai_response' => [
                        'id' => $aiMessage->id,
                        'message' => $aiMessage->message,
                        'message_type' => $aiMessage->message_type,
                        'formatted_message' => $aiMessage->formatted_message,
                        'sender_display_name' => $aiMessage->sender_display_name,
                        'confidence_score' => $aiMessage->confidence_score,
                        'intent' => $aiMessage->intent,
                        'needs_human_review' => $aiMessage->needsHumanReview(),
                        'created_at' => $aiMessage->created_at->format('Y-m-d H:i:s'),
                    ],
                    'session_status' => $session->fresh()->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get session messages
     */
    public function getMessages(string $sessionId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $session = ChatSession::where('tenant_id', $tenant->id)
            ->where('id', $sessionId)
            ->firstOrFail();

        $messages = ChatMessage::where('chat_session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(function($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'formatted_message' => $message->formatted_message,
                    'sender_type' => $message->sender_type,
                    'sender_display_name' => $message->sender_display_name,
                    'message_type' => $message->message_type,
                    'message_type_color' => $message->message_type_color,
                    'message_type_icon' => $message->message_type_icon,
                    'is_ai_generated' => $message->is_ai_generated,
                    'confidence_score' => $message->confidence_score,
                    'intent' => $message->intent,
                    'intent_color' => $message->intent_color,
                    'entities' => $message->entities,
                    'metadata' => $message->metadata,
                    'needs_human_review' => $message->needsHumanReview(),
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'language' => $session->language,
                    'duration' => $session->duration,
                    'satisfaction_level' => $session->satisfaction_level,
                ],
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Escalate session to human agent
     */
    public function escalateSession(string $sessionId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $session = ChatSession::where('tenant_id', $tenant->id)
            ->where('id', $sessionId)
            ->firstOrFail();

        $session->escalate();

        return response()->json([
            'success' => true,
            'message' => 'Session escalated to human agent',
        ]);
    }

    /**
     * Resolve session
     */
    public function resolveSession(Request $request, string $sessionId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $session = ChatSession::where('tenant_id', $tenant->id)
            ->where('id', $sessionId)
            ->firstOrFail();

        $request->validate([
            'satisfaction_rating' => 'nullable|integer|min:1|max:5',
            'satisfaction_feedback' => 'nullable|string|max:500',
        ]);

        $session->markAsResolved();
        
        if ($request->satisfaction_rating) {
            $session->update([
                'satisfaction_rating' => $request->satisfaction_rating,
                'satisfaction_feedback' => $request->satisfaction_feedback,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session resolved successfully',
        ]);
    }

    /**
     * Display chat interface
     */
    public function chat(string $sessionId): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        $session = ChatSession::where('tenant_id', $tenant->id)
            ->where('id', $sessionId)
            ->with(['customer', 'messages' => function($query) {
                $query->orderBy('created_at');
            }])
            ->firstOrFail();

        return Inertia::render('Chatbot/Chat', [
            'session' => [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'status' => $session->status,
                'language' => $session->language,
                'duration' => $session->duration,
                'satisfaction_level' => $session->satisfaction_level,
                'satisfaction_rating' => $session->satisfaction_rating,
                'satisfaction_feedback' => $session->satisfaction_feedback,
                'last_activity_at' => $session->last_activity_at?->format('Y-m-d H:i:s'),
                'resolved_at' => $session->resolved_at?->format('Y-m-d H:i:s'),
                'customer' => $session->customer ? [
                    'id' => $session->customer->id,
                    'name' => $session->customer->name,
                    'email' => $session->customer->email,
                    'phone' => $session->customer->phone,
                ] : null,
            ],
            'messages' => $session->messages->map(function($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'formatted_message' => $message->formatted_message,
                    'sender_type' => $message->sender_type,
                    'sender_display_name' => $message->sender_display_name,
                    'message_type' => $message->message_type,
                    'is_ai_generated' => $message->is_ai_generated,
                    'confidence_score' => $message->confidence_score,
                    'intent' => $message->intent,
                    'entities' => $message->entities,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Get session details
     */
    public function show(string $sessionId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $session = ChatSession::where('tenant_id', $tenant->id)
            ->where('id', $sessionId)
            ->with(['customer', 'messages' => function($query) {
                $query->orderBy('created_at');
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'status' => $session->status,
                'language' => $session->language,
                'duration' => $session->duration,
                'satisfaction_level' => $session->satisfaction_level,
                'satisfaction_rating' => $session->satisfaction_rating,
                'satisfaction_feedback' => $session->satisfaction_feedback,
                'last_activity_at' => $session->last_activity_at?->format('Y-m-d H:i:s'),
                'resolved_at' => $session->resolved_at?->format('Y-m-d H:i:s'),
                'customer' => $session->customer ? [
                    'id' => $session->customer->id,
                    'name' => $session->customer->name,
                    'email' => $session->customer->email,
                    'phone' => $session->customer->phone,
                ] : null,
                'messages' => $session->messages->map(function($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'formatted_message' => $message->formatted_message,
                        'sender_type' => $message->sender_type,
                        'sender_display_name' => $message->sender_display_name,
                        'message_type' => $message->message_type,
                        'is_ai_generated' => $message->is_ai_generated,
                        'confidence_score' => $message->confidence_score,
                        'intent' => $message->intent,
                        'entities' => $message->entities,
                        'metadata' => $message->metadata,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get chatbot statistics
     */
    public function stats(): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        $stats = $this->chatbotService->getSessionStats($tenant->id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
