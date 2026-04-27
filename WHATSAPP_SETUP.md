# WhatsApp Bot Integration Setup Guide

This guide will help you set up the WhatsApp Business API integration for the Gas Manager application.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [WhatsApp Business API Setup](#whatsapp-business-api-setup)
3. [Backend Configuration](#backend-configuration)
4. [Database Setup](#database-setup)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

## Prerequisites

- Laravel application with database configured
- WhatsApp Business Account
- Meta Developer Account
- Access to your server's environment variables
- SSL certificate for webhook (required by WhatsApp)

## WhatsApp Business API Setup

### Step 1: Create Meta Developer Account

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Create a new app or use an existing one
3. Add "WhatsApp" product to your app

### Step 2: Set Up WhatsApp Business Account

1. In your Meta app, navigate to WhatsApp > Getting Started
2. Follow the setup wizard to create a WhatsApp Business Account
3. Add a phone number (this will be your bot's number)
4. Complete the phone number verification process

### Step 3: Get API Credentials

You'll need the following credentials from your Meta app:

1. **Access Token** (WhatsApp API Token)
   - Go to WhatsApp > API Setup
   - Copy the temporary access token (or create a permanent one)
   - For production, create a System User and generate a permanent token

2. **Phone Number ID**
   - Found in WhatsApp > API Setup
   - Format: A long numeric ID

3. **Business Account ID**
   - Found in WhatsApp > API Setup
   - Format: A long numeric ID

4. **Verify Token**
   - Create a custom token for webhook verification
   - This can be any random string (e.g., `your-secret-verify-token-12345`)

### Step 4: Configure Webhook

1. In your Meta app, go to WhatsApp > Configuration
2. Set the webhook URL: `https://yourdomain.com/api/whatsapp/webhook`
3. Set the Verify Token (use the same token you'll add to `.env`)
4. Subscribe to the following webhook fields:
   - `messages`
   - `message_status`

## Backend Configuration

### Step 1: Environment Variables

Add the following variables to your `.env` file:

```env
# WhatsApp Business API Configuration
WHATSAPP_API_TOKEN=your_access_token_here
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id_here
WHATSAPP_VERIFY_TOKEN=your_secret_verify_token_here
```

**Important Notes:**
- Replace all placeholder values with your actual credentials
- Keep `WHATSAPP_VERIFY_TOKEN` secret and secure
- For production, use a permanent access token

### Step 2: Run Database Migrations

```bash
cd GasManagerBackend
php artisan migrate
```

This will create:
- `phone_number` and `phone_verified_at` columns in `users` table
- `whatsapp_sessions` table for conversation state management

### Step 3: Create Storage Directory

Create a temporary directory for image processing:

```bash
mkdir -p storage/app/temp
chmod 755 storage/app/temp
```

### Step 4: Configure Cache Driver

Ensure your cache driver is configured in `.env`:

```env
CACHE_DRIVER=redis
# or
CACHE_DRIVER=file
```

The OTP service uses Laravel's cache, so make sure caching is working.

### Step 5: Set Up Queue (Optional but Recommended)

For better performance, set up queue processing:

```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
```

Then run:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

## Database Setup

### User Phone Number Linking

Users need to link their phone numbers to their accounts. There are two ways:

#### Option 1: Via WhatsApp (Automatic)

1. User sends any message to the WhatsApp bot
2. Bot asks for registered email address
3. User sends their email address
4. Bot sends an OTP code
5. User enters the OTP
6. System verifies email, verifies OTP, and links the phone number to the account

#### Option 2: Via Admin Panel (Manual)

You can manually update user phone numbers in the database:

```sql
UPDATE users SET phone_number = '+1234567890', phone_verified_at = NOW() WHERE id = 1;
```

**Phone Number Format:**
- Include country code (e.g., `+2348012345678` for Nigeria)
- No spaces or special characters except the leading `+`

**Email Verification:**
- Users must provide the email address associated with their Gas Manager account
- The system will verify the email exists before sending OTP
- Phone number will be linked to the account matching the provided email

## Testing

### Step 1: Verify Webhook

1. Send a GET request to your webhook URL:
   ```
   https://yourdomain.com/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=your_secret_verify_token_here&hub.challenge=test123
   ```

2. You should receive `test123` as the response

### Step 2: Test OTP Flow

1. Send a message to your WhatsApp bot number
2. Bot should ask for your registered email address
3. Send your email address (the one used in your Gas Manager account)
4. Bot should respond with an OTP code
5. Enter the 6-digit OTP code
6. Bot should confirm verification and link your phone number to your account

### Step 3: Test Sales Recording Flow

1. **Send Closing Image**
   - Take a photo of gas machine display showing KG and Amount
   - Send image to WhatsApp bot
   - Bot should extract values and ask for date

2. **Enter Date**
   - Send date in format: `YYYY-MM-DD` (e.g., `2024-12-20`)
   - Bot should show location list

3. **Select Location**
   - Choose location from interactive list
   - Bot should show dispenser list

4. **Select Dispenser**
   - Choose dispenser from interactive list
   - Bot should create sales record and send confirmation

### Step 4: Test Error Handling

- Send invalid image (should show error)
- Send invalid date format (should show error)
- Send 'cancel' command (should reset session)

## Usage Flow

### For End Users

1. **First Time Setup:**
   - Send any message to the bot
   - Bot asks for your registered email address
   - Send your email address (must match your Gas Manager account)
   - Receive OTP code
   - Enter 6-digit OTP to verify and link phone number

2. **Recording Sales:**
   - Send closing image → Enter date → Select location → Select dispenser
   - Receive confirmation with sales details

3. **Commands:**
   - `cancel` or `reset` - Reset current session

### For Administrators

1. **Monitor Sessions:**
   ```sql
   SELECT * FROM whatsapp_sessions WHERE expires_at > NOW();
   ```

2. **View User Phone Numbers:**
   ```sql
   SELECT id, name, email, phone_number, phone_verified_at FROM users WHERE phone_verified_at IS NOT NULL;
   ```

3. **Clean Expired Sessions:**
   ```sql
   DELETE FROM whatsapp_sessions WHERE expires_at < NOW();
   ```

## Troubleshooting

### Webhook Not Receiving Messages

1. **Check Webhook URL:**
   - Must be HTTPS (not HTTP)
   - Must be publicly accessible
   - Must return 200 status code

2. **Check Verify Token:**
   - Must match in both Meta app and `.env` file
   - Case-sensitive

3. **Check Server Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### OTP Not Working

1. **Check Cache Configuration:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

2. **Check Cache Driver:**
   - Ensure `CACHE_DRIVER` is set correctly
   - Test cache: `php artisan tinker` → `Cache::put('test', 'value', 60)`

### Email Not Recognized

1. **Verify Email in Database:**
   ```sql
   SELECT id, name, email FROM users WHERE email = 'user@example.com';
   ```

2. **Check Email Format:**
   - Ensure user sends exact email address (case-insensitive)
   - Email must match the one used during account registration

3. **User Not Found:**
   - If email doesn't exist, user needs to create an account first
   - Or admin needs to create the user account

### Image Processing Fails

1. **Check OpenAI API Key:**
   - Ensure `OPENAI_API_KEY` is set in `.env`
   - Verify API key is valid and has credits

2. **Check Image Quality:**
   - Image must be clear
   - Must show gas machine display
   - Must have visible KG and Amount values

3. **Check Storage Permissions:**
   ```bash
   chmod -R 755 storage/app/temp
   ```

### Sales Record Not Created

1. **Check Required Data:**
   - Location must exist and belong to user's business
   - Dispenser must be active
   - Price must be set for location

2. **Check User Permissions:**
   - User must be linked to a business
   - User must have access to selected location

3. **Check Database:**
   ```sql
   SELECT * FROM sales ORDER BY id DESC LIMIT 5;
   ```

### Session Expired Errors

1. **Increase Session Timeout:**
   - Edit `WhatsAppConversationService.php`
   - Change `$sessionTimeout` value (default: 30 minutes)

2. **Check Session Cleanup:**
   - Sessions expire after 30 minutes of inactivity
   - Expired sessions are automatically cleaned

## Security Considerations

1. **Webhook Security:**
   - Always use HTTPS
   - Verify webhook token on every request
   - Implement rate limiting

2. **OTP Security:**
   - OTPs expire after 10 minutes
   - Maximum 5 verification attempts
   - OTPs are stored in cache (not database)

3. **User Verification:**
   - Phone numbers must be verified before use
   - Users can only access their own business data

4. **API Token Security:**
   - Never commit tokens to version control
   - Use environment variables
   - Rotate tokens regularly

## API Endpoints

### Webhook Endpoint
- **GET/POST** `/api/whatsapp/webhook`
- No authentication required
- Handles incoming WhatsApp messages

### OTP Verification
- **POST** `/api/whatsapp/verify-otp`
- Body: `{ "phone_number": "+1234567890", "email": "user@example.com", "otp": "123456" }`
- Returns: `{ "success": true/false, "message": "..." }`
- Note: Email is required to identify which user account to link the phone number to

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check WhatsApp webhook logs in Meta Developer Console
3. Verify all environment variables are set correctly
4. Ensure database migrations have run successfully

## Additional Resources

- [WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp)
- [Meta for Developers](https://developers.facebook.com/)
- [Laravel Documentation](https://laravel.com/docs)
