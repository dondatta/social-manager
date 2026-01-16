# Setting Up ngrok with Herd for Instagram Webhooks

## The Problem
ngrok's free tier shows a warning page that can interfere with Facebook's webhook verification. Facebook sends a GET request to verify the webhook, and ngrok's warning page can block this.

## Solution 1: Use ngrok with Request Header Rewriting (Recommended)

### Step 1: Install ngrok
If you don't have ngrok installed:
```bash
# Windows (using Chocolatey)
choco install ngrok

# Or download from: https://ngrok.com/download
```

### Step 2: Start ngrok with Herd
Herd typically runs on port 80. Start ngrok pointing to your Herd server:

```bash
ngrok http 80 --host-header=rewrite
```

The `--host-header=rewrite` flag helps with host header issues.

### Step 3: Get Your ngrok URL
After starting ngrok, you'll see something like:
```
Forwarding  https://abc123.ngrok-free.app -> http://localhost:80
```

Copy the HTTPS URL (e.g., `https://abc123.ngrok-free.app`)

### Step 4: Configure Webhook in Facebook
1. Go to: https://developers.facebook.com/apps/
2. Select your app
3. Go to "Webhooks" → "Instagram"
4. Click "Add Callback URL"
5. Enter: `https://abc123.ngrok-free.app/webhooks/instagram`
6. Click "Verify and Save"

### Step 5: Handle ngrok Warning Page
When Facebook tries to verify, you might see ngrok's warning page. Here's how to handle it:

**Option A: Click through the warning (one-time)**
- When you see the ngrok warning page, click "Visit Site"
- Facebook's verification should complete
- This only needs to be done once during setup

**Option B: Use ngrok's bypass (if available)**
- Some ngrok versions allow bypassing the warning for specific requests
- Check ngrok dashboard for bypass options

**Option C: Use ngrok's reserved domain (Paid)**
- Upgrade to ngrok paid plan
- Get a reserved domain (no warning page)
- Use: `ngrok http 80 --domain=your-reserved-domain.ngrok.app`

## Solution 2: Manual Verification Workaround

If the automatic verification fails due to ngrok's warning page:

### Step 1: Temporarily Disable ngrok Warning (if possible)
Some ngrok configurations allow this, but it's not always available on free tier.

### Step 2: Manual Verification
1. Facebook will send a GET request with these parameters:
   - `hub.mode=subscribe`
   - `hub.challenge=<random_string>`
   - `hub.verify_token=<your_verify_token>`

2. Your webhook endpoint should return the `hub.challenge` value

3. You can manually trigger this by visiting:
   ```
   https://your-ngrok-url.ngrok-free.app/webhooks/instagram?hub.mode=subscribe&hub.challenge=test123&hub.verify_token=YOUR_VERIFY_TOKEN
   ```

4. If it returns `test123`, your endpoint is working correctly

5. Then in Facebook's webhook settings, try verification again

## Solution 3: Use Expose Instead (Alternative)

Expose doesn't have a warning page and works well with Herd:

### Step 1: Install Expose
```bash
composer global require beyondcode/expose
```

### Step 2: Start Expose
```bash
expose share http://social-manager.test
```

### Step 3: Use the Expose URL
Expose will give you a URL like: `https://abc123.sharedwithexpose.com`
Use this in Facebook's webhook settings instead of ngrok.

## Solution 4: Test Webhook Verification Locally

Create a test command to verify your webhook endpoint works:

```bash
php artisan instagram:test-webhook-verification
```

This will simulate Facebook's verification request.

## Troubleshooting

### Issue: "Webhook verification failed"
- Make sure your webhook URL is accessible (test in browser)
- Check that your `IG_VERIFY_TOKEN` in `.env` matches what you enter in Facebook
- Ensure the route is not blocked by middleware (CSRF is already excluded)

### Issue: "ngrok warning page blocks verification"
- Click through the warning page once
- Or use Expose instead
- Or upgrade to ngrok paid for reserved domain

### Issue: "Webhook receives events but messages don't appear"
- Check Laravel logs: `storage/logs/laravel.log`
- Verify access token is valid: `php artisan instagram:test-token`
- Check database: `php artisan tinker` → `App\Models\Message::count()`

## Quick Setup Checklist

- [ ] ngrok installed and running
- [ ] ngrok pointing to Herd (port 80)
- [ ] ngrok URL copied (HTTPS version)
- [ ] Webhook URL added in Facebook: `https://your-ngrok-url.ngrok-free.app/webhooks/instagram`
- [ ] Verify token set in `.env`: `IG_VERIFY_TOKEN=your_secret_token`
- [ ] Same verify token entered in Facebook webhook settings
- [ ] Webhook verified (click through ngrok warning if needed)
- [ ] Subscribed to: `messaging`, `comments`, `mentions`
- [ ] Test by sending a DM to your Instagram account

## Production Setup

For production, you don't need ngrok. Just use your production domain:
- Webhook URL: `https://social.chrismichaelsmgmt.com/webhooks/instagram`
- No warning pages or tunneling needed






