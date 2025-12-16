<?php

namespace App\Services;

use HubSpot\Client\Crm\Contacts\Api\BasicApi as ContactsApi;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactInput;
use HubSpot\Factory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\AutomationSetting;

class HubspotService
{
    protected ?ContactsApi $contactsApi;
    protected ?string $apiKey;
    protected ?string $instagramHandleProperty;

    public function __construct()
    {
        // Try config first, then fallback to AutomationSetting, then hubspot.config.yml
        $this->apiKey = config('services.hubspot.api_key') 
            ?: AutomationSetting::where('key', 'hubspot_api_key')->value('value')
            ?: $this->getTokenFromConfigYml();
        
        $this->instagramHandleProperty = config('services.hubspot.instagram_handle_property', 'Instagram')
            ?: AutomationSetting::where('key', 'hubspot_instagram_handle_property')->value('value')
            ?: 'Instagram';
        
        if ($this->apiKey) {
            $client = Factory::createWithAccessToken($this->apiKey);
            $this->contactsApi = $client->crm()->contacts()->basicApi();
        } else {
            $this->contactsApi = null;
            Log::warning('HubSpot API key not configured');
        }
    }

    /**
     * Find a contact by Instagram username
     * Uses REST API directly since SDK search method isn't available
     * 
     * @param string $username Instagram username (without @)
     * @return string|null Contact ID if found, null otherwise
     */
    public function findContactByInstagramHandle(string $username): ?string
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $baseUrl = 'https://api.hubapi.com';
            
            // Try multiple possible property names (try capitalized first since that's what HubSpot uses)
            $possibleProperties = [
                $this->instagramHandleProperty, // User configured name
                'Instagram', // Capitalized (actual property name in HubSpot)
                'instagram', // Lowercase (fallback)
                'instagram_handle', // Alternative
            ];

            foreach ($possibleProperties as $propertyName) {
                // Use REST API to search contacts
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post("{$baseUrl}/crm/v3/objects/contacts/search", [
                    'filterGroups' => [
                        [
                            'filters' => [
                                [
                                    'propertyName' => $propertyName,
                                    'operator' => 'EQ',
                                    'value' => $username,
                                ],
                            ],
                        ],
                    ],
                    'properties' => ['id', 'firstname', 'lastname', $propertyName],
                    'limit' => 1,
                ]);

                if (!$response->successful()) {
                    // If property doesn't exist, try next one
                    if ($response->status() === 400) {
                        $errorBody = $response->json();
                        if (isset($errorBody['message']) && str_contains($errorBody['message'], 'property')) {
                            Log::debug("Property '{$propertyName}' not found, trying next...");
                            continue;
                        }
                    }
                    
                    Log::error('HubSpot search API call failed', [
                        'username' => $username,
                        'property' => $propertyName,
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                    continue;
                }

                $data = $response->json();
                $results = $data['results'] ?? [];

                if (!empty($results)) {
                    Log::info('HubSpot contact found', [
                        'username' => $username,
                        'property_used' => $propertyName,
                        'contact_id' => $results[0]['id'] ?? null,
                    ]);
                    return $results[0]['id'] ?? null;
                }
            }

            // If no results, try a broader search to see what properties exist
            Log::warning('HubSpot contact not found with any property', [
                'username' => $username,
                'properties_tried' => $possibleProperties,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('HubSpot search failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Log a message/note to a contact's timeline
     * Uses the HubSpot REST API directly since Notes SDK client isn't available
     * 
     * @param string $contactId HubSpot contact ID
     * @param string $message The message content to log
     * @param string $source Source of the message (e.g., 'Instagram DM')
     * @return bool Success status
     */
    public function logMessageToContact(string $contactId, string $message, string $source = 'Instagram DM'): bool
    {
        if (!$this->apiKey) {
            return false;
        }

        try {
            // Use REST API directly to create a note
            $baseUrl = 'https://api.hubapi.com';
            
            // Step 1: Create the note
            $noteResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/crm/v3/objects/notes", [
                'properties' => [
                    'hs_note_body' => "[$source] $message",
                    'hs_timestamp' => time() * 1000, // HubSpot expects milliseconds
                ],
            ]);

            if (!$noteResponse->successful()) {
                throw new \Exception('Failed to create note: ' . $noteResponse->body());
            }

            $noteData = $noteResponse->json();
            $noteId = $noteData['id'] ?? null;

            if (!$noteId) {
                throw new \Exception('Note created but no ID returned');
            }

            // Step 2: Associate the note with the contact
            // Using v4 batch associations endpoint: POST /crm/v4/associations/{fromObjectType}/{toObjectType}/batch/create
            // Using association label format
            $associationResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/crm/v4/associations/notes/contacts/batch/create", [
                'inputs' => [
                    [
                        'from' => ['id' => $noteId],
                        'to' => ['id' => $contactId],
                        'type' => 'note_to_contact', // Association label
                    ],
                ],
            ]);

            if (!$associationResponse->successful()) {
                // Note was created but association failed - log warning but don't fail completely
                Log::warning('HubSpot: Note created but association failed', [
                    'note_id' => $noteId,
                    'contact_id' => $contactId,
                    'status' => $associationResponse->status(),
                    'response' => $associationResponse->body(),
                ]);
                // Still return true since note was created, even if association failed
            } else {
                // Log successful association for debugging
                Log::info('HubSpot: Note association successful', [
                    'note_id' => $noteId,
                    'contact_id' => $contactId,
                    'response' => $associationResponse->json(),
                ]);
            }

            Log::info('HubSpot: Message logged successfully', [
                'contact_id' => $contactId,
                'note_id' => $noteId,
                'source' => $source,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('HubSpot: Failed to log message', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Read HubSpot token from hubspot.config.yml file (created by CLI)
     * Tries to read personalAccessKey first, then accessToken as fallback
     * 
     * @return string|null
     */
    protected function getTokenFromConfigYml(): ?string
    {
        $configPath = base_path('hubspot.config.yml');
        
        if (!file_exists($configPath)) {
            return null;
        }

        try {
            $content = file_get_contents($configPath);
            
            // Try to get personalAccessKey (multi-line YAML format with >-)
            if (preg_match('/personalAccessKey:\s*>\s*-\s*\n\s*([^\n]+)/', $content, $matches)) {
                $token = trim($matches[1]);
                if (!empty($token)) {
                    return $token;
                }
            }
            
            // Try single-line format
            if (preg_match('/personalAccessKey:\s*([^\n]+)/', $content, $matches)) {
                $token = trim($matches[1]);
                if (!empty($token) && !str_starts_with($token, '>')) {
                    return $token;
                }
            }
            
            // Fallback: try accessToken (though it expires)
            if (preg_match('/accessToken:\s*>\s*-\s*\n\s*([^\n]+)/', $content, $matches)) {
                $token = trim($matches[1]);
                if (!empty($token)) {
                    Log::info('Using accessToken from hubspot.config.yml (note: this token expires)');
                    return $token;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to read hubspot.config.yml', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

