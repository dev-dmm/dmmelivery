<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

/*
|--------------------------------------------------------------------------
| Chatbot Routes
|--------------------------------------------------------------------------
|
| Chatbot session and message management
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant', 'throttle:60,1'])
    ->prefix('chatbot')
    ->name('chatbot.')
    ->group(function () {
        Route::get('/', [ChatbotController::class, 'index'])->name('index');
        Route::post('/sessions', [ChatbotController::class, 'startSession'])->name('sessions.start');
        Route::post('/sessions/{session}/messages', [ChatbotController::class, 'sendMessage'])->name('sessions.send-message');
        Route::get('/sessions/{session}/messages', [ChatbotController::class, 'getMessages'])->name('sessions.messages');
        Route::post('/sessions/{session}/escalate', [ChatbotController::class, 'escalateSession'])->name('sessions.escalate');
        Route::post('/sessions/{session}/resolve', [ChatbotController::class, 'resolveSession'])->name('sessions.resolve');
        Route::get('/sessions/{session}', [ChatbotController::class, 'show'])->name('sessions.show');
        Route::get('/chat/{session}', [ChatbotController::class, 'chat'])->name('chat');
        Route::get('/stats', [ChatbotController::class, 'stats'])->name('stats');
    });

