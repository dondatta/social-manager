# Instagram API Limitations

## Profile Data for Messaging Users

**Important:** Instagram Graph API has limitations when fetching user profile data (username and profile picture) for Instagram-scoped IDs from messaging events.

### The Problem

When users send DMs, story replies, or story mentions, Instagram provides an Instagram-scoped user ID. However, the Instagram Graph API **does not support** fetching profile information (username, profile picture) for these IDs using the standard user profile endpoint.

### Why This Happens

1. **Privacy Restrictions**: Instagram limits what profile data can be accessed via API
2. **Scoped IDs**: Instagram-scoped IDs from messaging are different from public user IDs
3. **API Limitations**: The `/user-id` endpoint doesn't work for Instagram messaging user IDs
4. **Permissions**: Even with proper permissions, profile data may not be available

### What We Do

1. **Extract from Webhook Payload**: For comments, we try to extract username from the webhook payload if available (`data['from']['username']`)
2. **Async Retry**: We dispatch background jobs to retry fetching profile data
3. **Fallback Display**: If username/profile picture isn't available, we show the user ID instead

### Workarounds

#### For Comments
- Comments webhook payloads sometimes include the username in `data['from']['username']`
- We extract this automatically when available

#### For Direct Messages
- Instagram messaging webhooks do NOT include username or profile picture
- The API endpoint `/user-id` doesn't work for messaging user IDs
- **Result**: Username and profile picture may remain NULL for DM messages

### Alternative Solutions (Not Recommended)

1. **Third-party scraping**: Violates Instagram's Terms of Service
2. **User authentication**: Requires users to log in with Instagram (not practical for DMs)
3. **Manual entry**: Not scalable

### Current Behavior

- Messages are saved immediately with user ID
- Background jobs attempt to fetch profile data
- If fetching fails, messages still display with user ID
- Profile pictures show a default placeholder icon

### Checking Logs

If profile fetching fails, check `storage/logs/laravel.log` for detailed error messages. Common errors:

- `Invalid OAuth access token`
- `Unsupported get request`
- `User profile not available`

These errors are expected for Instagram messaging user IDs and are not a bug in the application.

