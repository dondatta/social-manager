<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ListHubspotProperties extends Command
{
    protected $signature = 'hubspot:list-properties {limit=5}';
    protected $description = 'List contacts and their Instagram-related properties to help identify the correct property name';

    public function handle()
    {
        $apiKey = config('services.hubspot.api_key') 
            ?: \App\Models\AutomationSetting::where('key', 'hubspot_api_key')->value('value');
        
        if (!$apiKey) {
            $configPath = base_path('hubspot.config.yml');
            if (file_exists($configPath)) {
                $content = file_get_contents($configPath);
                if (preg_match('/personalAccessKey:\s*>\s*-\s*\n\s*([^\n]+)/', $content, $matches)) {
                    $apiKey = trim($matches[1]);
                }
            }
        }

        if (!$apiKey) {
            $this->error('HubSpot API key not found!');
            return 1;
        }

        $limit = (int) $this->argument('limit');
        $baseUrl = 'https://api.hubapi.com';

        $this->info("Fetching {$limit} contacts to inspect properties...");
        $this->newLine();

        try {
            // Get a few contacts
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$baseUrl}/crm/v3/objects/contacts", [
                'limit' => $limit,
                'properties' => 'id,firstname,lastname,email',
            ]);

            if (!$response->successful()) {
                $this->error('Failed to fetch contacts: ' . $response->body());
                return 1;
            }

            $data = $response->json();
            $contacts = $data['results'] ?? [];

            if (empty($contacts)) {
                $this->warn('No contacts found');
                return 0;
            }

            // For each contact, get all properties to find Instagram-related ones
            foreach ($contacts as $contact) {
                $contactId = $contact['id'];
                $name = ($contact['properties']['firstname'] ?? '') . ' ' . ($contact['properties']['lastname'] ?? '');
                
                $this->line("Contact: {$name} (ID: {$contactId})");
                
                // Get all properties for this contact
                $contactResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->get("{$baseUrl}/crm/v3/objects/contacts/{$contactId}", [
                    'properties' => 'all',
                ]);

                if ($contactResponse->successful()) {
                    $contactData = $contactResponse->json();
                    $properties = $contactData['properties'] ?? [];
                    
                    // Find Instagram-related properties
                    $instagramProps = [];
                    foreach ($properties as $key => $value) {
                        if (stripos($key, 'instagram') !== false && !empty($value)) {
                            $instagramProps[$key] = $value;
                        }
                    }
                    
                    if (!empty($instagramProps)) {
                        foreach ($instagramProps as $prop => $val) {
                            $this->line("  → {$prop}: {$val}");
                        }
                    } else {
                        $this->line("  → No Instagram properties found");
                    }
                }
                
                $this->newLine();
            }

            $this->info('Tip: Use the property name shown above in your configuration!');
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('HubSpot list properties failed', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }
}


