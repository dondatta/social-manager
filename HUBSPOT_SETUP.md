# HubSpot Integration Setup Guide

## Prerequisites
- Node.js installed on your system
- HubSpot account with developer access

## Step 1: Install HubSpot CLI

```bash
npm install -g @hubspot/cli
```

Verify installation:
```bash
hs --version
```

## Step 2: Initialize HubSpot CLI

Navigate to your project directory and initialize:

```bash
cd /path/to/your/social-manager
hs init
```

This will:
1. Prompt you to choose authentication method (select "Personal Access Key")
2. Open your browser to `https://app.hubspot.com/1/personal-access-key` (or you can create one manually)
3. Ask you to paste your Personal Access Key
4. Ask for a unique name to reference this account (e.g., "defiant")
5. Create `hubspot.config.yml` in your project directory
6. Connect and set the account as default

**Note**: If you don't have a Personal Access Key yet:
- Go to https://app.hubspot.com/1/personal-access-key
- Click "Create a personal access key"
- Copy the key (you'll only see it once!)
- Paste it into the CLI prompt

## Step 3: Create a Private App (REQUIRED for API Access)

**Important**: The Personal Access Key from `hs init` is for CLI use only. For REST API calls, you MUST create a Private App via the web interface.

**Note**: The `hs project create` command is for building full HubSpot apps with features (cards, functions, etc.). For simple API access, use the web interface below.

### Create via Web Interface (Recommended for API Access):

1. Go to: **https://app.hubspot.com/settings/integrations/private-apps**
2. Click **"Create a private app"** (top right button)
3. **Name**: Enter `Instagram Integration` (or your preferred name)
4. Click **"Create app"**
5. Go to the **"Scopes"** tab
6. Select these scopes (check the boxes):
   - `crm.objects.contacts.read`
   - `crm.objects.contacts.write`
   - `crm.objects.notes.read`
   - `crm.objects.notes.write`
7. Click **"Save"** at the bottom
8. Go back to the **"Overview"** tab
9. Under **"Access token"**, click **"Show token"**
10. **Copy the token immediately** (starts with `pat-na1-`) - you won't see it again!

## Step 4: Configure Your Laravel Application

**You MUST add the Private App token to your `.env` file** (the Personal Access Key won't work for API calls):

```env
HUBSPOT_API_KEY=pat-na1-your-private-app-token-here
HUBSPOT_INSTAGRAM_HANDLE_PROPERTY=Instagram
```

**Note**: The token from `hubspot.config.yml` (Personal Access Key) is for CLI only. The Private App token is different and required for REST API calls.

Or configure via the Settings page in your Filament admin panel:
1. Go to `/admin/settings`
2. Scroll to "HubSpot Integration" section
3. Enter your API Key and Property Name
4. Click "Save Settings"

**Note**: If you configure via the Settings page, you'll still need to add it to `.env` for the application to use it properly.

## Step 5: Verify Your HubSpot Contacts

Ensure your HubSpot contacts have the Instagram handle property populated:
- Property name should match what you configured (default: `Instagram`)
- Values should be Instagram usernames **without** the `@` symbol (e.g., `john_doe` not `@john_doe`)

## Testing

Once configured, when someone sends you a DM or comments on your Instagram posts:
1. The system will fetch their Instagram username
2. Search HubSpot for a contact with that username
3. If found, create a note on that contact's timeline with the message content

Check your Laravel logs (`storage/logs/laravel.log`) to see HubSpot sync activity.

