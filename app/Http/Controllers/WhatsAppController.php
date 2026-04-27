<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use App\Services\OTPService;
use App\Services\WhatsAppConversationService;
use App\Services\OpenAIService;
use App\Models\User;
use App\Models\Location;
use App\Models\Dispenser;
use App\Models\Business;
use App\Models\Business_User;
use App\Models\Sale;
use App\Models\Supply;
use App\Models\Price;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    protected $whatsappService;
    protected $otpService;
    protected $conversationService;
    protected $openAIService;

    public function __construct(
        WhatsAppService $whatsappService,
        OTPService $otpService,
        WhatsAppConversationService $conversationService,
        OpenAIService $openAIService
    ) {
        $this->whatsappService = $whatsappService;
        $this->otpService = $otpService;
        $this->conversationService = $conversationService;
        $this->openAIService = $openAIService;
    }

    /**
     * Webhook verification (GET request)
     */
    public function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $result = $this->whatsappService->verifyWebhook($mode, $token, $challenge);

        if ($result) {
            return response($result, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Webhook handler (POST request)
     */
    public function handleWebhook(Request $request)
    {
        try {
            $data = $request->all();

            // Handle webhook verification
            if (isset($data['hub_mode'])) {
                return $this->verifyWebhook($request);
            }

            // Process incoming messages
            if (isset($data['entry'][0]['changes'][0]['value']['messages'])) {
                $messages = $data['entry'][0]['changes'][0]['value']['messages'];

                foreach ($messages as $message) {
                    $this->processMessage($message);
                }
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('WhatsApp Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Process incoming message
     */
    protected function processMessage(array $message)
    {
        $phoneNumber = $message['from'];
        $messageType = $message['type'] ?? 'text';
        $messageId = $message['id'];

        // Get or create session
        $user = User::where('phone_number', $phoneNumber)
            ->whereNotNull('phone_verified_at')
            ->first();

        $session = $this->conversationService->getOrCreateSession($phoneNumber, $user?->id);

        // Check if user is authenticated
        if (!$user) {
            $this->handleUnauthenticatedUser($phoneNumber, $message, $session);
            return;
        }

        // Check if session is expired
        if ($this->conversationService->isExpired($session)) {
            $this->conversationService->clearSession($session);
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "Your session has expired. Please send a new closing image to start again."
            );
            return;
        }

        // Process based on message type
        if ($messageType === 'image') {
            $this->handleImageMessage($phoneNumber, $message, $session, $user);
        } elseif ($messageType === 'text') {
            $this->handleTextMessage($phoneNumber, $message, $session, $user);
        } elseif ($messageType === 'interactive') {
            $this->handleInteractiveMessage($phoneNumber, $message, $session, $user);
        }
    }

    /**
     * Handle unauthenticated user
     */
    protected function handleUnauthenticatedUser(string $phoneNumber, array $message, $session)
    {
        $messageText = trim($message['text']['body'] ?? '');
        $messageTextLower = strtolower($messageText);
        
        // Get email from session context if already provided
        $userEmail = $this->conversationService->getContextValue($session, 'user_email');

        // Check if user is entering OTP
        if (preg_match('/^\d{6}$/', $messageText)) {
            // User is entering OTP - verify it
            if (!$userEmail) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ Please provide your email address first. Send your registered email address to continue."
                );
                return;
            }

            if ($this->otpService->verifyOTP($phoneNumber, $messageText)) {
                // OTP verified - find user by email and link phone number
                $user = User::where('email', $userEmail)->first();
                
                if ($user) {
                    // Link phone number to user account
                    $user->update([
                        'phone_number' => $phoneNumber,
                        'phone_verified_at' => now()
                    ]);
                    $session->update(['user_id' => $user->id]);
                    
                    // Clear email from context
                    $context = $session->context ?? [];
                    unset($context['user_email']);
                    $session->update(['context' => $context]);
                    
                    $this->whatsappService->sendMessage(
                        $phoneNumber,
                        "✅ Phone number verified and linked to your account!\n\nPlease send a closing image to start recording sales."
                    );
                } else {
                    $this->whatsappService->sendMessage(
                        $phoneNumber,
                        "❌ No account found with email: {$userEmail}\n\nPlease contact your administrator or try again with a different email address."
                    );
                    // Clear email from context to allow retry
                    $context = $session->context ?? [];
                    unset($context['user_email']);
                    $session->update(['context' => $context]);
                }
            } else {
                $remaining = $this->otpService->getRemainingAttempts($phoneNumber);
                if ($remaining > 0) {
                    $this->whatsappService->sendMessage(
                        $phoneNumber,
                        "❌ Invalid OTP. You have {$remaining} attempt(s) remaining. Please try again."
                    );
                } else {
                    $this->whatsappService->sendMessage(
                        $phoneNumber,
                        "❌ Maximum OTP attempts exceeded. Please send your email address again to request a new OTP."
                    );
                    // Clear email from context to allow retry
                    $context = $session->context ?? [];
                    unset($context['user_email']);
                    $session->update(['context' => $context]);
                }
            }
        } elseif (filter_var($messageText, FILTER_VALIDATE_EMAIL)) {
            // User sent their email - validate and send OTP
            $email = strtolower(trim($messageText));
            
            // Check if user exists with this email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ No account found with email: {$email}\n\nPlease make sure you're using the email address associated with your Gas Manager account.\n\nIf you don't have an account, please contact your administrator."
                );
                return;
            }

            // Check if phone number is already linked to another account
            $existingUser = User::where('phone_number', $phoneNumber)
                ->where('id', '!=', $user->id)
                ->whereNotNull('phone_verified_at')
                ->first();
            
            if ($existingUser) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ This phone number is already linked to another account. Please contact your administrator if you need to change this."
                );
                return;
            }

            // Store email in session context
            $this->conversationService->setContextValue($session, 'user_email', $email);
            
            // Generate and send OTP
            $otp = $this->otpService->generateOTP($phoneNumber);
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "✅ Email verified: {$email}\n\n🔐 To verify your phone number, please enter the 6-digit OTP:\n\n*{$otp}*\n\nThis OTP will expire in 10 minutes."
            );
        } else {
            // First message or invalid input - ask for email
            if ($userEmail) {
                // Email already provided, waiting for OTP
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "Please enter the 6-digit OTP that was sent to you, or send 'cancel' to start over."
                );
            } else {
                // First interaction - ask for email
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "🔐 Welcome to Gas Manager!\n\nTo get started, please send your registered email address.\n\nThis will be used to link your WhatsApp number to your account."
                );
            }
        }
    }

    /**
     * Handle image message
     */
    protected function handleImageMessage(string $phoneNumber, array $message, $session, User $user)
    {
        if ($session->state !== 'waiting_image') {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "Please complete the current process or send 'cancel' to start over."
            );
            return;
        }

        try {
            // Download image from WhatsApp
            $imageId = $message['image']['id'];
            $imageUrl = $this->downloadWhatsAppImage($imageId);

            if (!$imageUrl) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ Error downloading image. Please try again with a clear image."
                );
                return;
            }

            // Process image with AI
            $imagePath = storage_path('app/temp/' . basename($imageUrl));
            $response = $this->openAIService->analyzeImage($imagePath);
            $text = $response['choices'][0]['message']['content'] ?? '';

            if (Str::contains($text, 'null')) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ Could not extract data from the image. Please ensure the image is clear and shows the gas machine display with KG and Amount values."
                );
                return;
            }

            $extractedData = json_decode($text, true);

            if (!is_array($extractedData) || count($extractedData) < 2) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ Invalid data extracted from image. Please try again with a clearer image."
                );
                return;
            }

            // Store extracted data
            $this->conversationService->setContextValue($session, 'closing_kg', $extractedData[0]);
            $this->conversationService->setContextValue($session, 'closing_sales', $extractedData[1]);
            $this->conversationService->updateState($session, 'waiting_date');

            // Ask for date
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "✅ Image processed successfully!\n\n📊 Extracted values:\n• Closing KG: {$extractedData[0]}\n• Closing Sales: ₦" . number_format($extractedData[1], 2) . "\n\n📅 Please enter the sales date (format: YYYY-MM-DD)\nExample: " . date('Y-m-d')
            );

            // Clean up temp file
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        } catch (\Exception $e) {
            Log::error('Image Processing Error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber
            ]);

            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ An error occurred while processing the image. Please try again."
            );
        }
    }

    /**
     * Handle text message
     */
    protected function handleTextMessage(string $phoneNumber, array $message, $session, User $user)
    {
        $messageText = strtolower(trim($message['text']['body'] ?? ''));

        // Handle cancel command
        if ($messageText === 'cancel' || $messageText === 'reset') {
            $this->conversationService->clearSession($session);
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "🔄 Session reset. Please send a new closing image to start again."
            );
            return;
        }

        // Process based on current state
        switch ($session->state) {
            case 'waiting_date':
                $this->handleDateInput($phoneNumber, $messageText, $session, $user);
                break;

            case 'waiting_location':
                $this->handleLocationInput($phoneNumber, $messageText, $session, $user);
                break;

            case 'waiting_dispenser':
                $this->handleDispenserInput($phoneNumber, $messageText, $session, $user);
                break;

            default:
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "Please send a closing image to start recording sales, or type 'cancel' to reset."
                );
        }
    }

    /**
     * Handle interactive message (button/list selections)
     */
    protected function handleInteractiveMessage(string $phoneNumber, array $message, $session, User $user)
    {
        $interactive = $message['interactive'] ?? [];
        $type = $interactive['type'] ?? '';

        if ($type === 'list_reply') {
            $selectedId = $interactive['list_reply']['id'] ?? '';
            
            if ($session->state === 'waiting_location') {
                $this->handleLocationSelection($phoneNumber, $selectedId, $session, $user);
            } elseif ($session->state === 'waiting_dispenser') {
                $this->handleDispenserSelection($phoneNumber, $selectedId, $session, $user);
            }
        }
    }

    /**
     * Handle date input
     */
    protected function handleDateInput(string $phoneNumber, string $dateInput, $session, User $user)
    {
        try {
            $date = Carbon::parse($dateInput);
            
            if (!$date || $date->isFuture()) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ Invalid date. Please enter a valid date in the format YYYY-MM-DD (not in the future).\nExample: " . date('Y-m-d')
                );
                return;
            }

            $this->conversationService->setContextValue($session, 'sales_date', $date->format('Y-m-d'));
            $this->conversationService->updateState($session, 'waiting_location');

            // Get user's business locations
            $this->sendLocationList($phoneNumber, $session, $user);
        } catch (\Exception $e) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ Invalid date format. Please enter date as YYYY-MM-DD\nExample: " . date('Y-m-d')
            );
        }
    }

    /**
     * Send location list
     */
    protected function sendLocationList(string $phoneNumber, $session, User $user)
    {
        // Get user's business
        $businessUser = Business_User::where('user_id', $user->id)->with('Business')->first();
        $business = $businessUser?->Business ?? Business::where('owner_id', $user->id)->first();

        if (!$business) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ No business found for your account. Please contact your administrator."
            );
            return;
        }

        // Get locations
        $locations = Location::where('business_id', $business->id)->get();

        if ($locations->isEmpty()) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ No locations found for your business."
            );
            return;
        }

        // Store business_id in context
        $this->conversationService->setContextValue($session, 'business_id', $business->id);

        // Format locations for interactive list
        $sections = [[
            'title' => 'Select Location',
            'rows' => $locations->map(function ($location) {
                return [
                    'id' => "loc_{$location->id}",
                    'title' => $location->name,
                    'description' => "Location ID: {$location->id}"
                ];
            })->toArray()
        ]];

        $this->whatsappService->sendInteractiveList(
            $phoneNumber,
            'Select Location',
            'Please select a location:',
            'Gas Manager',
            $sections
        );
    }

    /**
     * Handle location selection
     */
    protected function handleLocationSelection(string $phoneNumber, string $locationId, $session, User $user)
    {
        $locationId = str_replace('loc_', '', $locationId);
        $location = Location::find($locationId);

        if (!$location) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ Invalid location selected. Please try again."
            );
            return;
        }

        // Verify location belongs to user's business
        $businessId = $this->conversationService->getContextValue($session, 'business_id');
        if ($location->business_id != $businessId) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ You don't have access to this location."
            );
            return;
        }

        $this->conversationService->setContextValue($session, 'location_id', $location->id);
        $this->conversationService->updateState($session, 'waiting_dispenser');

        // Get dispensers for location
        $this->sendDispenserList($phoneNumber, $session, $location);
    }

    /**
     * Handle location text input (fallback)
     */
    protected function handleLocationInput(string $phoneNumber, string $input, $session, User $user)
    {
        // Try to find location by name or ID
        $businessId = $this->conversationService->getContextValue($session, 'business_id');
        $location = Location::where('business_id', $businessId)
            ->where(function ($query) use ($input) {
                $query->where('name', 'like', "%{$input}%")
                    ->orWhere('id', $input);
            })
            ->first();

        if ($location) {
            $this->handleLocationSelection($phoneNumber, "loc_{$location->id}", $session, $user);
        } else {
            $this->sendLocationList($phoneNumber, $session, $user);
        }
    }

    /**
     * Send dispenser list
     */
    protected function sendDispenserList(string $phoneNumber, $session, Location $location)
    {
        $dispensers = Dispenser::where('location_id', $location->id)
            ->where('active', '1')
            ->get();

        if ($dispensers->isEmpty()) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ No active dispensers found for this location."
            );
            return;
        }

        // Format dispensers for interactive list
        $sections = [[
            'title' => 'Select Dispenser',
            'rows' => $dispensers->map(function ($dispenser) {
                return [
                    'id' => "disp_{$dispenser->id}",
                    'title' => $dispenser->name,
                    'description' => "Capacity: {$dispenser->capacity} KG"
                ];
            })->toArray()
        ]];

        $this->whatsappService->sendInteractiveList(
            $phoneNumber,
            'Select Dispenser',
            "Please select a dispenser for {$location->name}:",
            'Gas Manager',
            $sections
        );
    }

    /**
     * Handle dispenser selection
     */
    protected function handleDispenserSelection(string $phoneNumber, string $dispenserId, $session, User $user)
    {
        $dispenserId = str_replace('disp_', '', $dispenserId);
        $dispenser = Dispenser::find($dispenserId);

        if (!$dispenser) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ Invalid dispenser selected. Please try again."
            );
            return;
        }

        // Verify dispenser belongs to selected location
        $locationId = $this->conversationService->getContextValue($session, 'location_id');
        if ($dispenser->location_id != $locationId) {
            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ This dispenser doesn't belong to the selected location."
            );
            return;
        }

        // Create sales record
        $this->createSalesRecord($phoneNumber, $session, $user, $dispenser);
    }

    /**
     * Handle dispenser text input (fallback)
     */
    protected function handleDispenserInput(string $phoneNumber, string $input, $session, User $user)
    {
        $locationId = $this->conversationService->getContextValue($session, 'location_id');
        $dispenser = Dispenser::where('location_id', $locationId)
            ->where('active', '1')
            ->where(function ($query) use ($input) {
                $query->where('name', 'like', "%{$input}%")
                    ->orWhere('id', $input);
            })
            ->first();

        if ($dispenser) {
            $this->handleDispenserSelection($phoneNumber, "disp_{$dispenser->id}", $session, $user);
        } else {
            $location = Location::find($locationId);
            if ($location) {
                $this->sendDispenserList($phoneNumber, $session, $location);
            }
        }
    }

    /**
     * Create sales record
     */
    protected function createSalesRecord(string $phoneNumber, $session, User $user, Dispenser $dispenser)
    {
        try {
            $businessId = $this->conversationService->getContextValue($session, 'business_id');
            $locationId = $this->conversationService->getContextValue($session, 'location_id');
            $salesDate = $this->conversationService->getContextValue($session, 'sales_date');
            $closingKg = $this->conversationService->getContextValue($session, 'closing_kg');
            $closingSales = $this->conversationService->getContextValue($session, 'closing_sales');

            // Get previous sales to determine opening values
            $prevSales = Sale::where([
                ['business_id', '=', $businessId],
                ['location_id', '=', $locationId],
                ['dispenser_id', '=', $dispenser->id]
            ])->latest()->first();

            // Get supply
            $supply = Supply::where([
                ['business_id', '=', $businessId],
                ['location_id', '=', $locationId],
                ['dispenser_id', '=', $dispenser->id],
                ['sold', '=', '0']
            ])->first();

            if (!$supply) {
                $supply = Supply::where([
                    ['business_id', '=', $businessId],
                    ['location_id', '=', $locationId],
                    ['dispenser_id', '=', $dispenser->id]
                ])->latest()->first();
            }

            // Get price
            $price = Price::where([
                ['business_id', '=', $businessId],
                ['location_id', '=', $locationId],
                ['active', '=', 'true']
            ])->first();

            if (!$price) {
                $this->whatsappService->sendMessage(
                    $phoneNumber,
                    "❌ No active price set for this location. Please set a price first."
                );
                return;
            }

            // Determine opening values
            $openingSales = $prevSales ? $prevSales->closing_sales : 0;
            $openingKg = $prevSales ? $prevSales->closing_kg : 0;

            // Create sales record
            $sales = new Sale;
            $sales->business_id = $businessId;
            $sales->location_id = $locationId;
            $sales->dispenser_id = $dispenser->id;
            $sales->opening_sales = $openingSales;
            $sales->closing_sales = $closingSales;
            $sales->opening_kg = $openingKg;
            $sales->closing_kg = $closingKg;

            // Calculate amount
            if ((float)$closingSales >= (float)$openingSales) {
                $sales->amount = (float)$closingSales - (float)$openingSales;
            } else {
                $sales->amount = (1000000 + (float)$closingSales) - (float)$openingSales;
            }

            // Calculate kg quantity
            $sales->kg_quantity = (float)$closingKg - (float)$openingKg;
            $sales->sales_date = Carbon::parse($salesDate);
            $sales->uploaded_by = $user->id;
            $sales->price_id = $price->id;
            $sales->supply_id = $supply?->id;
            $sales->status = 'confirmed';
            $sales->save();

            // Update dispenser level
            $dispenser->prev_level = $dispenser->current_level;
            if ($sales->kg_quantity > $dispenser->current_level) {
                $dispenser->current_level = 0;
            } else {
                $dispenser->current_level = $dispenser->current_level - $sales->kg_quantity;
            }
            $dispenser->save();

            // Send success message
            $message = "✅ Sales record created successfully!\n\n";
            $message .= "📊 Sales Details:\n";
            $message .= "• Location: " . $dispenser->Location->name . "\n";
            $message .= "• Dispenser: " . $dispenser->name . "\n";
            $message .= "• Date: " . $salesDate . "\n";
            $message .= "• Opening Sales: ₦" . number_format($openingSales, 2) . "\n";
            $message .= "• Closing Sales: ₦" . number_format($closingSales, 2) . "\n";
            $message .= "• Opening KG: " . number_format($openingKg, 2) . " KG\n";
            $message .= "• Closing KG: " . number_format($closingKg, 2) . " KG\n";
            $message .= "• Amount: ₦" . number_format($sales->amount, 2) . "\n";
            $message .= "• Quantity: " . number_format($sales->kg_quantity, 2) . " KG\n\n";
            $message .= "Send another closing image to record more sales.";

            $this->whatsappService->sendMessage($phoneNumber, $message);

            // Clear session
            $this->conversationService->clearSession($session);
        } catch (\Exception $e) {
            Log::error('Sales Creation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phoneNumber
            ]);

            $this->whatsappService->sendMessage(
                $phoneNumber,
                "❌ An error occurred while creating the sales record. Please try again or contact support."
            );
        }
    }

    /**
     * Download image from WhatsApp
     */
    protected function downloadWhatsAppImage(string $imageId): ?string
    {
        try {
            // Get image URL from WhatsApp
            $response = Http::withToken(env('WHATSAPP_API_TOKEN'))
                ->get("https://graph.facebook.com/v18.0/{$imageId}");

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $imageUrl = $data['url'] ?? null;

            if (!$imageUrl) {
                return null;
            }

            // Download image
            $imageContent = Http::withToken(env('WHATSAPP_API_TOKEN'))
                ->get($imageUrl)
                ->body();

            // Save to temp storage
            $filename = 'whatsapp_' . uniqid() . '.jpg';
            $path = storage_path('app/temp/' . $filename);
            
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            file_put_contents($path, $imageContent);

            return $path;
        } catch (\Exception $e) {
            Log::error('Image Download Error', [
                'error' => $e->getMessage(),
                'image_id' => $imageId
            ]);

            return null;
        }
    }

    /**
     * Verify OTP endpoint (for manual verification if needed)
     */
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $phoneNumber = $request->phone_number;
        $email = strtolower(trim($request->email));
        $otp = $request->otp;

        if ($this->otpService->verifyOTP($phoneNumber, $otp)) {
            // Find user by email
            $user = User::where('email', $email)->first();
            
            if ($user) {
                // Check if phone number is already linked to another account
                $existingUser = User::where('phone_number', $phoneNumber)
                    ->where('id', '!=', $user->id)
                    ->whereNotNull('phone_verified_at')
                    ->first();
                
                if ($existingUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This phone number is already linked to another account'
                    ], 400);
                }

                // Link phone number to user account
                $user->update([
                    'phone_number' => $phoneNumber,
                    'phone_verified_at' => now()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Phone number verified and linked successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address'
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP'
        ], 400);
    }
}
