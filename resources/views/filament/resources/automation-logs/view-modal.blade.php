<div class="space-y-4">
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">User ID</h3>
        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->instagram_user_id }}</p>
    </div>
    
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Action Type</h3>
        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->action_type }}</p>
    </div>
    
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
        <p class="mt-1 text-sm">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $record->status === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                {{ ucfirst($record->status) }}
            </span>
        </p>
    </div>
    
    @if($record->error_message)
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Error Message</h3>
        <p class="mt-1 text-sm text-red-600 dark:text-red-400 whitespace-pre-wrap">{{ $record->error_message }}</p>
    </div>
    @endif
    
    @if($record->payload)
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Payload</h3>
        <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-auto max-h-64">{{ json_encode($record->payload, JSON_PRETTY_PRINT) }}</pre>
    </div>
    @endif
    
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</h3>
        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->created_at->format('M j, Y g:i A') }}</p>
    </div>
</div>

