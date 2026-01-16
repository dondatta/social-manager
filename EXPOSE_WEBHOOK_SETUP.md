# Setting Up Expose for Instagram Webhooks Testing

## Quick Setup Guide

### Step 1: Start Expose
Run this command in your terminal:
```bash
expose share http://social-manager.test --subdomain=social-manager
```

Expose will output a URL like:
```
https://social-manager.sharedwithexpose.com
```

**Keep this terminal window open** - Expose needs to keep running.

### Step 2: Set Your Verify Token
Make sure you have `IG_VERIFY_TOKEN` set in your `.env` file:
```env
IG_VERIFY_TOKEN=your_secret_token_here
```

Choose a secure random string (e.g., `my_instagram_webhook_2024_secret`)

### Step 3: Configure Webhook in Facebook

1. **Go to Facebook Developers:**
   - Visit: https://developers.facebook.com/apps/
   - Select your app

2. **Navigate to Webhooks:**
   - In the left sidebar, click **"Webhooks"**
   - Click **"Instagram"** (or find it under Products)

3. **Add Callback URL:**
   - Click **"Add Callback URL"** or **"Edit Subscription"**
   - Enter your Expose URL + webhook path:
     ```
     https://social-manager.sharedwithexpose.com/webhooks/instagram
     ```
   - **Verify Token:** Enter the same value as `IG_VERIFY_TOKEN` in your `.env` file
   - Click **"Verify and Save"**

4. **Subscribe to Events:**
   After verification, subscribe to these events:
   - ✅ **messaging** - Direct messages
   - ✅ **comments** - Comments on posts
   - ✅ **mentions** - @mentions in posts/stories
   - ✅ **story_mentions** - Story mentions (if available)

### Step 4: Test the Webhook

1. **Test Verification Locally:**
   ```bash
   php artisan instagram:test-webhook-verification
   ```

2. **Test with Expose URL:**
   ```bash
   php artisan instagram:test-webhook-verification --url="https://social-manager.sharedwithexpose.com/webhooks/instagram"
   ```

3. **Send a Test Message:**
   - Send a DM to your Instagram account
   - Check your Laravel logs: `storage/logs/laravel.log`
   - Check the Messages table in your admin panel

### Step 5: Monitor Webhook Events

Watch for incoming webhooks:
```bash
# Windows PowerShell
Get-Content storage\logs\laravel.log -Tail 50 -Wait

# Or use Laravel's log viewer
```

## Troubleshooting

### Issue: "Webhook verification failed"
- ✅ Make sure Expose is still running
- ✅ Check that `IG_VERIFY_TOKEN` in `.env` matches what you entered in Facebook
- ✅ Verify the webhook URL is correct (include `/webhooks/instagram`)
- ✅ Run: `php artisan config:clear` after changing `.env`

### Issue: "Webhook receives events but nothing happens"
- ✅ Check Laravel logs: `storage/logs/laravel.log`
- ✅ Verify access token: `php artisan instagram:test-token`
- ✅ Check database: Messages should be created
- ✅ Verify queue is running: `php artisan queue:work`

### Issue: "Expose URL not accessible"
- ✅ Make sure Expose is running (check the terminal)
- ✅ Try a different subdomain: `expose share http://social-manager.test --subdomain=test123`
- ✅ Check your internet connection

### Issue: "No webhook events received"
- ✅ Verify webhook is subscribed to events in Facebook
- ✅ Make sure your Instagram account is connected to the app
- ✅ Test by sending a DM to your Instagram account
- ✅ Check Facebook's webhook logs in the app dashboard

## Production Setup

For production, you don't need Expose. Use your production domain:
- Webhook URL: `https://yourdomain.com/webhooks/instagram`
- Same verify token setup
- No tunneling needed

## Quick Checklist

- [ ] Expose is running and showing a URL
- [ ] `IG_VERIFY_TOKEN` is set in `.env`
- [ ] Webhook URL added in Facebook: `https://your-expose-url.sharedwithexpose.com/webhooks/instagram`
- [ ] Verify token matches in both `.env` and Facebook
- [ ] Webhook verified successfully
- [ ] Subscribed to: `messaging`, `comments`, `mentions`
- [ ] Test by sending a DM to Instagram
- [ ] Check logs and database for incoming messages


