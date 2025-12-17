# How to Get Instagram Access Token (IG_ACCESS_TOKEN)

## Method 1: Using Artisan Command (Recommended - Avoids manage_pages Error)

This method uses the `/me/accounts` endpoint to get a Page Access Token without deprecated permissions.

### Step 1: Get a User Access Token
1. Go to: https://developers.facebook.com/tools/explorer/
2. Select your app
3. Select "User Token" (NOT Page Token)
4. Click "Generate Access Token"
5. Select ONLY these permissions:
   - ✅ `pages_show_list`
   - ✅ `pages_messaging`
   - ✅ `instagram_basic`
   - ✅ `business_management` (if using Business account)
6. **⚠️ DO NOT select `manage_pages` - it's deprecated!**
7. Copy the generated User Access Token

### Step 2: Run the Artisan Command
```bash
php artisan instagram:get-page-token
```

The command will:
- Ask for your User Access Token
- List all your pages
- Let you select the page connected to Instagram
- Generate a Page Access Token without deprecated permissions
- Display the token to add to your `.env` file

### Step 3: Add to .env
Copy the token and page ID shown by the command to your `.env`:
```env
IG_ACCESS_TOKEN="your_page_access_token_here"
IG_PAGE_ID="your_page_id_here"
```

---

## Method 2: Using Graph API Explorer (May Include Deprecated Permissions)

### Step 1: Go to Graph API Explorer
Visit: https://developers.facebook.com/tools/explorer/

### Step 2: Select Your App
1. Click the dropdown next to "Meta App" (top left)
2. Select your Facebook App that's connected to Instagram
3. If you don't have an app, you'll need to create one first (see Method 2)

### Step 3: Select Your Page
1. Click the dropdown next to "User or Page" (below the app dropdown)
2. **IMPORTANT**: Select your **Facebook Page** (not your User account)
   - The page should be connected to your Instagram Business/Creator account
   - Look for your page name in the list
   - If you don't see your page, make sure:
     - Your Instagram account is a Business or Creator account
     - Your Instagram is connected to a Facebook Page
     - You're an admin of that Facebook Page

### Step 4: Set Permissions
1. Click "Get Token" → "Get Page Access Token" (NOT User Access Token)
2. In the permissions dialog, select these permissions:
   - ✅ `instagram_basic` - Basic Instagram access
   - ✅ `pages_show_list` - List your pages
   - ✅ `pages_messaging` - Messaging permissions (for Instagram DMs)
   - ✅ `pages_manage_metadata` - Manage page metadata
   - ✅ `business_management` - Business account access (if using Business account)

**⚠️ IMPORTANT: Do NOT select `manage_pages` - it's deprecated and will cause errors!**

**⚠️ WARNING**: The Graph API Explorer may automatically add `manage_pages` even if you don't select it. If you get the "Invalid Scopes: manage_pages" error, use **Method 1** (Artisan command) instead.

**Note**: `instagram_manage_messages` is automatically included when you select `pages_messaging` for Instagram messaging.

### Step 5: Generate Token
1. Click "Generate Access Token"
2. You may be asked to log in and approve permissions
3. Copy the generated token (it will be a long string)

### Step 6: Convert to Long-Lived Token (Recommended)
Short-lived tokens expire in 1-2 hours. Convert to a long-lived token (60 days):

1. Go to: https://developers.facebook.com/tools/accesstoken/
2. Find your token in the list
3. Click "Extend Access Token" or use this endpoint:
   ```
   GET https://graph.facebook.com/v21.0/oauth/access_token?
     grant_type=fb_exchange_token&
     client_id={app-id}&
     client_secret={app-secret}&
     fb_exchange_token={short-lived-token}
   ```

### Step 7: Add to .env File
Add the token to your `.env` file:
```env
IG_ACCESS_TOKEN="paste_your_token_here"
IG_PAGE_ID="your_facebook_page_id"
IG_APP_SECRET="your_app_secret"
```

**Important**: 
- Use quotes around the token if it contains special characters
- No spaces before or after the token
- Restart your application after updating .env

---

## Method 2: Create a New Facebook App (If You Don't Have One)

### Step 1: Go to Meta for Developers
Visit: https://developers.facebook.com/

### Step 2: Create App
1. Click "My Apps" → "Create App"
2. Select "Business" as the app type
3. Fill in:
   - App Name: e.g., "Social Manager"
   - App Contact Email: Your email
4. Click "Create App"

### Step 3: Add Instagram Product
1. In your app dashboard, go to "Add Products"
2. Find "Instagram" and click "Set Up"
3. Choose "Instagram Messaging" or "Instagram Basic Display"

### Step 4: Configure Instagram
1. Go to "Instagram" → "Basic Display" or "Messaging" in the sidebar
2. Add your Instagram Business/Creator account
3. Complete the setup wizard

### Step 5: Get App Secret
1. Go to "Settings" → "Basic"
2. Copy your "App Secret" (you'll need this for IG_APP_SECRET)
3. Add it to your `.env`:
   ```env
   IG_APP_SECRET="your_app_secret_here"
   ```

### Step 6: Get Page ID
1. Go to your Facebook Page
2. Click "About" → "Page ID" (or check the URL)
3. Or use Graph API Explorer to get it
4. Add to `.env`:
   ```env
   IG_PAGE_ID="your_page_id_here"
   ```

---

## Method 3: Using Meta Business Suite (Alternative)

1. Go to: https://business.facebook.com/
2. Select your Instagram Business account
3. Go to "Settings" → "Instagram" → "Instagram API"
4. Generate access token from there
5. Copy the token to your `.env` file

---

## Verify Your Token

After adding the token, test it:
```bash
php artisan instagram:test-token
```

This will verify:
- ✅ Token is set correctly
- ✅ Token format is valid
- ✅ Token has proper permissions
- ✅ Token can access your Instagram account

---

## Troubleshooting

### "Invalid OAuth access token"
- Token expired → Generate a new one
- Wrong token type → Make sure it's a **Page Access Token**, not User Token
- Token format → Check for spaces or missing quotes in .env

### "Cannot parse access token"
- Token has special characters → Use quotes in .env: `IG_ACCESS_TOKEN="token"`
- Token is truncated → Make sure the full token is copied
- Extra spaces → Remove any spaces before/after the token

### "Page not found"
- Wrong Page ID → Verify IG_PAGE_ID matches your Facebook Page ID
- Page not connected → Make sure Instagram is connected to the Facebook Page

### "Insufficient permissions"
- Missing permissions → Regenerate token with all required permissions
- App not approved → Some permissions require app review for production

### "Invalid Scopes: manage_pages"
- **This error occurs even if you didn't select `manage_pages`**
- **Cause**: Graph API Explorer automatically adds deprecated `manage_pages` when generating Page Access Tokens
- **Solution 1 (Recommended)**: Use the Artisan command instead:
  ```bash
  php artisan instagram:get-page-token
  ```
  This uses the `/me/accounts` endpoint which doesn't include deprecated permissions.
- **Solution 2**: Generate a User Access Token first, then use `/me/accounts` endpoint manually:
  ```bash
  GET https://graph.facebook.com/v21.0/me/accounts?fields=id,name,access_token,instagram_business_account&access_token=YOUR_USER_TOKEN
  ```
- **Solution 3**: If you must use Graph API Explorer:
  1. Generate a User Access Token (not Page Token) with only: `pages_show_list`, `pages_messaging`, `instagram_basic`
  2. Then use that token to call `/me/accounts` to get Page Access Tokens

---

## Token Expiration

- **Short-lived tokens**: Expire in 1-2 hours
- **Long-lived tokens**: Expire in 60 days
- **Never-expiring tokens**: Not available for Instagram (must refresh every 60 days)

**Best Practice**: Set a reminder to refresh your token every 50 days.

---

## Security Notes

⚠️ **Never commit your .env file to Git!**
- The `.env` file should be in `.gitignore`
- Use environment variables on your production server
- Rotate tokens if they're accidentally exposed

