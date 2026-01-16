<div class="space-y-4 text-sm">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-3">How to Get Your Long-Lived Instagram Access Token (60 Days)</h4>
        
        <div class="mb-4">
            <p class="text-blue-800 dark:text-blue-200 mb-2"><strong>Step 1: Get a Short-Term Token</strong></p>
            <ol class="list-decimal list-inside space-y-2 ml-2 text-blue-800 dark:text-blue-200">
                <li>
                    Go to <a href="https://developers.facebook.com/tools/explorer/" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">Graph API Explorer</a>
                </li>
                <li>
                    In the top left, select your app from the dropdown
                </li>
                <li>
                    Click the <strong>"User or Page"</strong> dropdown and select your <strong>Facebook Page</strong> (the one connected to your Instagram account)
                </li>
                <li>
                    Click <strong>"Generate Access Token"</strong>
                </li>
                <li>
                    Select these permissions when prompted (IMPORTANT - select all of these):
                    <ul class="list-disc list-inside ml-4 mt-1 space-y-0.5">
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">instagram_basic</code></li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">instagram_manage_messages</code> <strong class="text-red-600 dark:text-red-400">(Required for sending DMs)</strong></li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">pages_show_list</code></li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">pages_read_engagement</code></li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">pages_messaging</code></li>
                    </ul>
                </li>
                <li>
                    Copy the generated token (it will start with "EAA...") - this is a short-term token (expires in 1-2 hours)
                </li>
            </ol>
        </div>

        <div class="mb-4 pt-3 border-t border-blue-200 dark:border-blue-700">
            <p class="text-blue-800 dark:text-blue-200 mb-2"><strong>Step 2: Exchange for Long-Lived Token (60 Days)</strong></p>
            <ol class="list-decimal list-inside space-y-2 ml-2 text-blue-800 dark:text-blue-200">
                <li>
                    In the same Graph API Explorer, make sure your short-term token is in the "Access Token" field
                </li>
                <li>
                    In the endpoint field, change it to: <code class="bg-blue-100 dark:bg-blue-800 px-1.5 py-0.5 rounded text-xs font-mono">/oauth/access_token</code>
                </li>
                <li>
                    Click the <strong>"Query String"</strong> tab below the endpoint field
                </li>
                <li>
                    Add these parameters:
                    <ul class="list-disc list-inside ml-4 mt-1 space-y-0.5">
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">grant_type</code> = <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">fb_exchange_token</code></li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">client_id</code> = Your App ID (found in your app settings)</li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">client_secret</code> = Your App Secret (found in your app settings)</li>
                        <li><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">fb_exchange_token</code> = The short-term token you just copied</li>
                    </ul>
                </li>
                <li>
                    Click <strong>"Submit"</strong> or press Enter
                </li>
                <li>
                    Copy the <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">access_token</code> from the response - this is your long-lived token (60 days)
                </li>
                <li>
                    Paste this long-lived token in the field below
                </li>
            </ol>
        </div>

        <div class="pt-3 border-t border-blue-200 dark:border-blue-700">
            <p class="text-blue-800 dark:text-blue-200 mb-2"><strong>Step 3: Get Your Page ID</strong></p>
            <ol class="list-decimal list-inside space-y-2 ml-2 text-blue-800 dark:text-blue-200">
                <li>
                    In Graph API Explorer, with your Page selected, make a GET request to: <code class="bg-blue-100 dark:bg-blue-800 px-1.5 py-0.5 rounded text-xs font-mono">/me</code>
                </li>
                <li>
                    The <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">id</code> field in the response is your Page ID
                </li>
                <li>
                    Copy the Page ID and paste it in the Page ID field below
                </li>
            </ol>
        </div>
    </div>

    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
        <p class="text-amber-800 dark:text-amber-200">
            <strong>⚠️ Important:</strong> Long-lived tokens expire after 60 days. You'll need to repeat this process to get a new token when it expires. The system will notify you when your token is invalid.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
        <p class="text-gray-700 dark:text-gray-300 text-xs">
            <strong>💡 Tip:</strong> You can find your App ID and App Secret in Facebook Developers → Your App → Settings → Basic. If you don't see your Facebook Page in the dropdown, make sure you're an admin of both the Facebook Page and the Facebook App.
        </p>
    </div>
</div>


