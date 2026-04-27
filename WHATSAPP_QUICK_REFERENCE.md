# WhatsApp Bot Quick Reference Guide

## Environment Variables

Add these to your `.env` file:

```env
WHATSAPP_API_TOKEN=your_access_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id
WHATSAPP_VERIFY_TOKEN=your_secret_token
```

## Setup Commands

```bash
# Run migrations
php artisan migrate

# Create temp directory
mkdir -p storage/app/temp
chmod 755 storage/app/temp

# Clear cache
php artisan cache:clear
php artisan config:clear
```

## Webhook URL

```
https://yourdomain.com/api/whatsapp/webhook
```

## User Flow

1. **First Time:**
   - User sends message → Bot asks for email → User sends email → Receives OTP → Enters OTP → Verified & Linked

2. **Recording Sales:**
   - Send image → Enter date (YYYY-MM-DD) → Select location → Select dispenser → Done

3. **Commands:**
   - `cancel` or `reset` - Reset session

## Testing Webhook

```bash
curl "https://yourdomain.com/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=YOUR_TOKEN&hub.challenge=test123"
```

Should return: `test123`

## Common Issues

### Webhook not working
- Check HTTPS is enabled
- Verify token matches
- Check server logs

### OTP not sending
- Check cache driver
- Verify WhatsApp API token
- Check phone number format
- Ensure user provided valid email first

### Email not recognized
- Verify email exists in users table
- Check email matches account registration email
- User must have an existing account

### Image processing fails
- Verify OpenAI API key
- Check image quality
- Ensure temp directory exists

## Database Queries

```sql
-- View verified users with email
SELECT id, name, email, phone_number, phone_verified_at FROM users WHERE phone_verified_at IS NOT NULL;

-- View active sessions
SELECT * FROM whatsapp_sessions WHERE expires_at > NOW();

-- Clean expired sessions
DELETE FROM whatsapp_sessions WHERE expires_at < NOW();

-- Find user by email
SELECT id, name, email, phone_number FROM users WHERE email = 'user@example.com';
```

## Support

Check logs: `storage/logs/laravel.log`
