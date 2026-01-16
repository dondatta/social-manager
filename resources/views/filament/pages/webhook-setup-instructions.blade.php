<div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 text-sm">
    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Setting Up Your Instagram Webhook</h4>
    <ol class="list-decimal list-inside space-y-2 ml-2 text-gray-700 dark:text-gray-300">
        <li>
            Go to <a href="https://developers.facebook.com/apps" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">Facebook Developers</a> and select your app
        </li>
        <li>
            In the left sidebar, click on <strong>"Instagram"</strong> → <strong>"Webhooks"</strong>
        </li>
        <li>
            Click <strong>"Add Callback URL"</strong> or <strong>"Edit"</strong> if you already have one
        </li>
        <li>
            Enter the <strong>Webhook URL</strong> shown above (copy it from the field above)
        </li>
        <li>
            Enter the <strong>Verify Token</strong> shown above (copy it exactly as shown)
        </li>
        <li>
            Click <strong>"Verify and Save"</strong>
        </li>
        <li>
            Subscribe to these events:
            <ul class="list-disc list-inside ml-4 mt-1 space-y-0.5">
                <li><strong>messages</strong> - For direct messages</li>
                <li><strong>comments</strong> - For post comments</li>
                <li><strong>mentions</strong> - For @mentions</li>
            </ul>
        </li>
        <li>
            Click <strong>"Save"</strong>
        </li>
    </ol>
    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
        <p class="text-xs text-gray-600 dark:text-gray-400">
            <strong>Note:</strong> Once configured, your webhook will receive Instagram messages, comments, and mentions in real-time. Make sure your server is accessible from the internet.
        </p>
    </div>
</div>

