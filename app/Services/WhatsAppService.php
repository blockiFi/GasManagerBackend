<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiToken;
    protected $phoneNumberId;
    protected $businessAccountId;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiToken = env('WHATSAPP_API_TOKEN');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->businessAccountId = env('WHATSAPP_BUSINESS_ACCOUNT_ID');
        $this->apiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}";
    }

    /**
     * Send text message to WhatsApp
     */
    public function sendMessage(string $to, string $message): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('WhatsApp API Error', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Exception', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send interactive list message
     */
    public function sendInteractiveList(string $to, string $header, string $body, string $footer, array $sections): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'header' => [
                            'type' => 'text',
                            'text' => $header
                        ],
                        'body' => [
                            'text' => $body
                        ],
                        'footer' => [
                            'text' => $footer
                        ],
                        'action' => [
                            'button' => 'Select',
                            'sections' => $sections
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Interactive List Error', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send button message
     */
    public function sendButtons(string $to, string $body, array $buttons): array
    {
        try {
            $formattedButtons = [];
            foreach ($buttons as $button) {
                $formattedButtons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $button['id'],
                        'title' => $button['title']
                    ]
                ];
            }

            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => $body
                        ],
                        'action' => [
                            'buttons' => $formattedButtons
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Buttons Error', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number (remove +, spaces, etc.)
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If starts with +, keep it, otherwise ensure country code
        if (strpos($phone, '+') === 0) {
            return substr($phone, 1);
        }
        
        return $phone;
    }

    /**
     * Verify webhook token
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $verifyToken = env('WHATSAPP_VERIFY_TOKEN');
        
        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }
}
