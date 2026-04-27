<?php

namespace App\Services;

use App\Models\WhatsAppSession;
use Carbon\Carbon;

class WhatsAppConversationService
{
    protected $sessionTimeout = 30; // minutes

    /**
     * Get or create session for phone number
     */
    public function getOrCreateSession(string $phoneNumber, ?int $userId = null): WhatsAppSession
    {
        $session = WhatsAppSession::where('phone_number', $phoneNumber)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$session) {
            $session = WhatsAppSession::create([
                'user_id' => $userId,
                'phone_number' => $phoneNumber,
                'state' => 'waiting_image',
                'context' => [],
                'expires_at' => now()->addMinutes($this->sessionTimeout),
            ]);
        }

        return $session;
    }

    /**
     * Update session state
     */
    public function updateState(WhatsAppSession $session, string $state, array $context = []): void
    {
        $currentContext = $session->context ?? [];
        $session->update([
            'state' => $state,
            'context' => array_merge($currentContext, $context),
            'expires_at' => now()->addMinutes($this->sessionTimeout),
        ]);
    }

    /**
     * Get session context value
     */
    public function getContextValue(WhatsAppSession $session, string $key, $default = null)
    {
        return $session->context[$key] ?? $default;
    }

    /**
     * Set context value
     */
    public function setContextValue(WhatsAppSession $session, string $key, $value): void
    {
        $context = $session->context ?? [];
        $context[$key] = $value;
        $session->update([
            'context' => $context,
            'expires_at' => now()->addMinutes($this->sessionTimeout),
        ]);
    }

    /**
     * Clear session
     */
    public function clearSession(WhatsAppSession $session): void
    {
        $session->update([
            'state' => 'waiting_image',
            'context' => [],
            'expires_at' => now()->addMinutes($this->sessionTimeout),
        ]);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(WhatsAppSession $session): bool
    {
        if (!$session->expires_at) {
            return false;
        }

        return $session->expires_at->isPast();
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(): void
    {
        WhatsAppSession::where('expires_at', '<', now())->delete();
    }
}
