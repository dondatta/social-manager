<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HubspotService;
use Illuminate\Support\Facades\Log;

class TestHubspotConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubspot:test {username?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test HubSpot integration by searching for a contact and logging a test message';

    /**
     * Execute the console command.
     */
    public function handle(HubspotService $hubspotService)
    {
        $this->info('Testing HubSpot Integration...');
        $this->newLine();

        // Test 1: Check if API key is configured
        $apiKey = config('services.hubspot.api_key') 
            ?: \App\Models\AutomationSetting::where('key', 'hubspot_api_key')->value('value');
        
        if (!$apiKey) {
            // Try reading from hubspot.config.yml
            $configPath = base_path('hubspot.config.yml');
            if (file_exists($configPath)) {
                $content = file_get_contents($configPath);
                if (preg_match('/personalAccessKey:\s*>\s*-\s*\n\s*([^\n]+)/', $content, $matches)) {
                    $apiKey = trim($matches[1]);
                    $this->info('✓ Found API key in hubspot.config.yml');
                }
            }
        }

        if (!$apiKey) {
            $this->error('✗ HubSpot API key not found!');
            $this->warn('Please configure it in .env (HUBSPOT_API_KEY) or hubspot.config.yml');
            return 1;
        }

        $this->info('✓ API Key found: ' . substr($apiKey, 0, 10) . '...');
        $this->newLine();

        // Test 2: Search for a contact
        $username = $this->argument('username');
        
        if (!$username) {
            $username = $this->ask('Enter an Instagram username to test (without @):');
        }

        if (!$username) {
            $this->warn('No username provided. Skipping contact search test.');
            return 0;
        }

        $this->info("Searching for contact with Instagram handle: {$username}");
        
        try {
            $contactId = $hubspotService->findContactByInstagramHandle($username);
            
            if ($contactId) {
                $this->info("✓ Contact found! Contact ID: {$contactId}");
                $this->newLine();
                
                // Test 3: Log a test message
                if ($this->confirm('Do you want to log a test message to this contact?', true)) {
                    $testMessage = "Test message from Laravel - " . now()->toDateTimeString();
                    $success = $hubspotService->logMessageToContact($contactId, $testMessage, 'Test');
                    
                    if ($success) {
                        $this->info("✓ Test message logged successfully!");
                        $this->info("Check the contact's timeline in HubSpot to see the note.");
                    } else {
                        $this->error("✗ Failed to log message. Check logs for details.");
                    }
                }
            } else {
                $this->warn("✗ No contact found with Instagram handle: {$username}");
                $this->newLine();
                $this->info("Troubleshooting:");
                $this->line("  1. Check the property name in HubSpot (might be 'instagram' not 'instagram_handle')");
                $this->line("  2. Verify the value matches exactly (case-sensitive, no @ symbol)");
                $this->line("  3. Check your configured property name: " . config('services.hubspot.instagram_handle_property', 'instagram_handle'));
                $this->newLine();
                
                if ($this->confirm('Would you like to search for contacts and see what Instagram properties they have?', false)) {
                    $this->call('hubspot:list-properties');
                }
            }
        } catch (\Exception $e) {
            $this->error("✗ Error: " . $e->getMessage());
            $this->warn("Check your API key and scopes. You may need to create a Private App.");
            Log::error('HubSpot test failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        $this->newLine();
        $this->info('Test completed!');
        return 0;
    }
}

