<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\ApiEndpoint;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ImportActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:activities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch, process, and store activities from external APIs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Set execution time limit for long-running import process
        set_time_limit(config('console.max_execution_time', 300));
        
        $this->info("Starting activity import process...");
        Log::info("[ImportActivities] Starting activity import process...");

        $endpoints = ApiEndpoint::all();
        Log::info("[ImportActivities] Retrieved endpoints", ['count' => $endpoints->count()]);
        if ($endpoints->isEmpty()) {
            $this->warn("No API endpoints found in the database. Please add some to the 'api_endpoints' table.");
            Log::warning("[ImportActivities] No API endpoints found in the database.");
            return;
        }

        $allActivities = [];

        foreach ($endpoints as $endpoint) {
            $this->info("Processing endpoint: {$endpoint->description} (Area: {$endpoint->area}, ID: {$endpoint->id})");
            Log::info("[ImportActivities] Processing endpoint", [
                'endpoint_id' => $endpoint->id,
                'endpoint_description' => $endpoint->description,
                'endpoint_area' => $endpoint->area
            ]);
            try {
                $activitiesFromApi = $this->fetchActivityList($endpoint);
                Log::info("[ImportActivities] Activities fetched from API", [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_description' => $endpoint->description,
                    'count' => is_array($activitiesFromApi) ? count($activitiesFromApi) : 0
                ]);
                $filteredActivities = $this->filterActivities($activitiesFromApi);
                Log::info("[ImportActivities] Filtered activities", [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_description' => $endpoint->description,
                    'count' => is_array($filteredActivities) ? count($filteredActivities) : 0
                ]);
                $detailedActivities = $this->fetchActivityDetails($filteredActivities, $endpoint);
                Log::info("[ImportActivities] Detailed activities", [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_description' => $endpoint->description,
                    'count' => is_array($detailedActivities) ? count($detailedActivities) : 0
                ]);
                $allActivities = array_merge($allActivities, $detailedActivities);
            } catch (\Exception $e) {
                $this->error("Failed to process endpoint {$endpoint->description} (ID: {$endpoint->id}): {$e->getMessage()}");
                Log::error("[ImportActivities] Endpoint processing failed", [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_description' => $endpoint->description,
                    'endpoint_area' => $endpoint->area,
                    'exception' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("Finished fetching data. Found " . count($allActivities) . " total activities to process.");
        Log::info("[ImportActivities] Finished fetching data", ['total_activities' => count($allActivities)]);

        // Soft delete activities that are no longer present in the API
        $this->softDeleteObsoleteActivities($endpoints, $allActivities);

        if (!empty($allActivities)) {
            $allActivities = $this->processActivityFields($allActivities);
            $allActivities = $this->processGeocoding($allActivities);
            $this->upsertActivities($allActivities);
        }

        $this->info("Activity import process finished.");
        Log::info("[ImportActivities] Activity import process finished.");

        // Clear ICS cache after import
        Cache::forget('ics_calendar_file');
    }

    private function fetchActivityList(ApiEndpoint $endpoint): array
    {
        $baseUrl = rtrim($endpoint->url, '/') . '/activities/';
        Log::debug("[ImportActivities] Fetching activity list", [
            'endpoint_id' => $endpoint->id,
            'endpoint_description' => $endpoint->description,
            'url' => $baseUrl
        ]);
        $response = Http::withHeaders($this->getAuthHeaders($endpoint))->get($baseUrl);
        Log::debug("[ImportActivities] Activity list response", [
            'endpoint_id' => $endpoint->id,
            'endpoint_description' => $endpoint->description,
            'status' => $response->status()
        ]);
        $response->throw(); // Throw an exception for 4xx/5xx responses
        
        $this->info("Fetched " . count($response->json()) . " activities from list endpoint.");
        return $response->json();
    }

    private function filterActivities(array $activities): array
    {
        
        $oneYearAgo = Carbon::now()->subYear();

        // Definizione dei filtri modulari
        $filters = [
            // Filtro per data recente
            'recent' => function ($activity) use ($oneYearAgo) {
                return isset($activity['start_date']) && Carbon::parse($activity['start_date'])->gte($oneYearAgo);
            },
            // Filtro per tipo
            'type' => function ($activity) {
                return isset($activity['type']) && in_array($activity['type'], ['announcement', 'external']);
            },
            // Altri filtri futuri...
        ];

        // Attiva/disattiva i filtri qui
        $activeFilters = [
            'recent',
            'type',
        ];

        // Applica i filtri attivi in sequenza
        return array_filter($activities, function ($activity) use ($filters, $activeFilters) {
            foreach ($activeFilters as $filterName) {
                if (isset($filters[$filterName]) && !$filters[$filterName]($activity)) {
                    return false;
                }
            }
            return true;
        });
    }

    private function fetchActivityDetails(array $activities, ApiEndpoint $endpoint): array
    {
        $detailedActivities = [];
        $baseUrl = rtrim($endpoint->url, '/') . '/activities/';
        Log::debug("[ImportActivities] Fetching activity details", [
            'endpoint_id' => $endpoint->id,
            'endpoint_description' => $endpoint->description,
            'baseUrl' => $baseUrl, 
            'count' => is_array($activities) ? count($activities) : 0
        ]);

        foreach ($activities as $activity) {
            try {
                $detailUrl = $baseUrl . $activity['id'] . '/';
                Log::debug("[ImportActivities] Fetching activity detail", [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_description' => $endpoint->description,
                    'detailUrl' => $detailUrl, 
                    'activity_id' => $activity['id']
                ]);
                $response = Http::withHeaders($this->getAuthHeaders($endpoint))->get($detailUrl);
                Log::debug("[ImportActivities] Activity detail response", [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_description' => $endpoint->description,
                    'status' => $response->status(), 
                    'activity_id' => $activity['id']
                ]);
                
                $details = $response->successful() ? $response->json() : [];
                $fullActivity = array_merge($activity, $details);

                $fullActivity['api_endpoint_id'] = $endpoint->id;
                $detailedActivities[] = $fullActivity;

            } catch (\Exception $e) {
                $this->error("Could not fetch details for activity ID {$activity['id']}: {$e->getMessage()}");
                Log::warning("[ImportActivities] Activity detail fetch failed", ['id' => $activity['id'], 'exception' => $e->getMessage()]);
            }
        }
        
        $this->info("Fetched details for " . count($detailedActivities) . " activities.");
        Log::info("[ImportActivities] Fetched details for activities", [
            'endpoint_id' => $endpoint->id,
            'endpoint_description' => $endpoint->description,
            'count' => count($detailedActivities)
        ]);
        return $detailedActivities;
    }

    private function getAuthHeaders(ApiEndpoint $endpoint): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($endpoint->token) {
            $headers['Authorization'] = 'Token ' . $endpoint->token;
        }
        return $headers;
    }

    private function geocodeLocation(string $location): ?array
    {
        try {
            Log::debug("[ImportActivities] Geocoding location (Google)", ['location' => $location]);
            $googleApiKey = env('GOOGLE_API_KEY');
            if (empty($googleApiKey)) {
                Log::warning("[ImportActivities] GOOGLE_API_KEY not configured; skipping geocoding");
                return null;
            }

            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $location,
                'language' => 'it',
                'region' => 'it',
                'key' => $googleApiKey,
            ]);
            Log::debug("[ImportActivities] Google Geocode response", ['status' => $response->status()]);

            if ($response->status() === 200) {
                $payload = $response->json();
                $status = $payload['status'] ?? null;
                $results = $payload['results'] ?? [];
                if ($status === 'OK' && !empty($results)) {
                    $locationData = $results[0]['geometry']['location'] ?? null;
                    if ($locationData && isset($locationData['lat'], $locationData['lng'])) {
                        Log::debug("[ImportActivities] Geocode data (Google)", ['coords_found' => true]);
                        return ['lat' => (float)$locationData['lat'], 'lon' => (float)$locationData['lng']];
                    }
                }

                Log::warning("[ImportActivities] Google Geocode returned no data", [
                    'api_status' => $payload['status'] ?? null,
                    'error_message' => $payload['error_message'] ?? null
                ]);
            } else {
                Log::warning("[ImportActivities] Google Geocode HTTP error", [
                    'http_status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->error("Geocoding failed for '{$location}': {$e->getMessage()}");
            Log::error("[ImportActivities] Geocoding failed", ['location' => $location, 'exception' => $e->getMessage()]);
        }
        return null;
    }

    private function processGeocoding(array $activities): array
    {
        $this->info("Processing geocoding for " . count($activities) . " activities...");
        Log::info("[ImportActivities] Starting geocoding process", ['total_activities' => count($activities)]);

        // Statistics for geocoding optimization
        $geocodeStats = [
            'attempted' => 0,
            'skipped_coordinates_exist' => 0,
            'skipped_no_location' => 0,
            'location_removed' => 0,
            'successful' => 0,
            'failed' => 0
        ];

        $processedActivities = [];

        foreach ($activities as $activity) {
            $activityId = $activity['id'];
            
            // Check if activity exists in database and get existing data for optimization
            $existingActivity = Activity::find($activityId);
            
            // Geocode optimization logic
            $shouldGeocode = false;
            $geocodeReason = '';
            
            // If activity doesn't exist in database (new activity)
            if (!$existingActivity) {
                if (!empty($activity['location'])) {
                    $shouldGeocode = true;
                    $geocodeReason = 'new_activity';
                }
            }
            // If activity exists in database
            else {
                // If location has been removed
                if (empty($activity['location'])) {
                    $activity['latitude'] = null;
                    $activity['longitude'] = null;
                    
                    $geocodeStats['location_removed']++;
                                    Log::debug("[ImportActivities] Location removed, coordinates set to null", [
                    'activity_id' => $activityId,
                    'previous_location' => $existingActivity->location
                ]);
                }
                // If location is present
                else {
                    // If location has changed
                    if ($existingActivity->location !== $activity['location']) {
                        $shouldGeocode = true;
                        $geocodeReason = 'location_changed';
                    }
                    // If location is the same but coordinates are missing in existing activity
                    elseif (empty($existingActivity->latitude) || empty($existingActivity->longitude)) {
                        $shouldGeocode = true;
                        $geocodeReason = 'coordinates_missing';
                    }
                }
            }
            
            // Perform geocoding if needed
            if ($shouldGeocode) {
                $geocodeStats['attempted']++;
                Log::debug("[ImportActivities] Attempting geocode", [
                    'location' => $activity['location'], 
                    'activity_id' => $activityId,
                    'reason' => $geocodeReason
                ]);
                $coords = $this->geocodeLocation($activity['location']);
                Log::debug("[ImportActivities] Geocode result", [
                    'coords_found' => $coords ? true : false, 
                    'activity_id' => $activityId
                ]);
                if ($coords) {
                    $activity['latitude'] = $coords['lat'];
                    $activity['longitude'] = $coords['lon'];
                    $geocodeStats['successful']++;
                } else {
                    $geocodeStats['failed']++;
                }
            } else {
                // Log skipped geocoding
                if ($existingActivity && !empty($existingActivity->latitude) && !empty($existingActivity->longitude)) {
                    // Copy existing coordinates to preserve them
                    $activity['latitude'] = $existingActivity->latitude;
                    $activity['longitude'] = $existingActivity->longitude;
                    $geocodeStats['skipped_coordinates_exist']++;
                    Log::debug("[ImportActivities] Geocoding skipped - coordinates already exist", [
                        'activity_id' => $activityId,
                        'existing_location' => $existingActivity->location,
                        'new_location' => $activity['location']
                    ]);
                } elseif (empty($activity['location'])) {
                    $geocodeStats['skipped_no_location']++;
                    Log::debug("[ImportActivities] Geocoding skipped - no location provided", [
                        'activity_id' => $activityId
                    ]);
                }
            }
            
            $processedActivities[] = $activity;
        }
        
        $this->info("Geocoding process completed.");
        Log::info("[ImportActivities] Geocoding process completed", [
            'total_processed' => count($processedActivities),
            'geocode_stats' => $geocodeStats
        ]);
        
        return $processedActivities;
    }

    private function upsertActivities(array $activities): void
    {
        $this->info("Saving " . count($activities) . " activities to the database...");
        Log::info("[ImportActivities] Upserting activities", ['count' => count($activities)]);

        $timestampStats = [
            'new_activities' => 0,
            'updated_activities' => 0,
            'unchanged_activities' => 0,
            'restored_activities' => 0
        ];

        // Prepare data for upsert by mapping keys with intelligent timestamp handling
        $upsertData = array_map(function($activity) use (&$timestampStats) {
            $activityId = $activity['id'];
            $now = Carbon::now();
            
            // Check if activity already exists (including soft-deleted ones)
            $existingActivity = Activity::withTrashed()->find($activityId);
            
            if ($existingActivity) {
                // Check if activity was soft-deleted and needs to be restored
                if ($existingActivity->trashed()) {
                    $timestampStats['restored_activities']++;
                    Log::info("[ImportActivities] Restoring soft-deleted activity", [
                        'activity_id' => $activityId,
                        'name' => $activity['name'],
                        'deleted_at' => $existingActivity->deleted_at
                    ]);
                    
                    // Restore the activity first
                    $existingActivity->restore();
                    
                    // Then update it with new data
                    return [
                        'id' => $activityId,
                        'level_id' => $activity['level_id'],
                        'name' => $activity['name'],
                        'type' => $activity['type'],
                        'description' => $activity['description'] ?? null,
                        'start_date' => isset($activity['start_date']) ? Carbon::parse($activity['start_date']) : null,
                        'end_date' => isset($activity['end_date']) ? Carbon::parse($activity['end_date']) : null,
                        'rt_type' => $activity['rt_type'],
                        'rt_visibility' => $activity['rt_visibility'],
                        'location' => $activity['location'] ?? null,
                        'cover_picture' => $activity['cover_picture'] ?? null,
                        'canceled' => $activity['canceled'] ?? false,
                        'latitude' => $activity['latitude'] ?? null,
                        'longitude' => $activity['longitude'] ?? null,
                        'api_endpoint_id' => $activity['api_endpoint_id'],
                        'created_at' => $existingActivity->created_at, // Preserve original creation time
                        'updated_at' => $now, // Update modification time
                    ];
                }
                // Activity exists - check if it needs updating
                $needsUpdate = $this->activityNeedsUpdate($existingActivity, $activity);
                
                if ($needsUpdate) {
                    $timestampStats['updated_activities']++;
                    Log::debug("[ImportActivities] Activity needs update", [
                        'activity_id' => $activityId,
                        'name' => $activity['name']
                    ]);
                    return [
                        'id' => $activityId,
                        'level_id' => $activity['level_id'],
                        'name' => $activity['name'],
                        'type' => $activity['type'],
                        'description' => $activity['description'] ?? null,
                        'start_date' => isset($activity['start_date']) ? Carbon::parse($activity['start_date']) : null,
                        'end_date' => isset($activity['end_date']) ? Carbon::parse($activity['end_date']) : null,
                        'rt_type' => $activity['rt_type'],
                        'rt_visibility' => $activity['rt_visibility'],
                        'location' => $activity['location'] ?? null,
                        'cover_picture' => $activity['cover_picture'] ?? null,
                        'canceled' => $activity['canceled'] ?? false,
                        'latitude' => $activity['latitude'] ?? null,
                        'longitude' => $activity['longitude'] ?? null,
                        'api_endpoint_id' => $activity['api_endpoint_id'],
                        'created_at' => $existingActivity->created_at, // Preserve original creation time
                        'updated_at' => $now, // Update modification time
                    ];
                } else {
                    $timestampStats['unchanged_activities']++;
                    Log::debug("[ImportActivities] Activity unchanged", [
                        'activity_id' => $activityId,
                        'name' => $activity['name']
                    ]);
                    return null; // Skip this activity
                }
            } else {
                // New activity
                $timestampStats['new_activities']++;
                Log::debug("[ImportActivities] New activity", [
                    'activity_id' => $activityId,
                    'name' => $activity['name']
                ]);
                return [
                    'id' => $activityId,
                    'level_id' => $activity['level_id'],
                    'name' => $activity['name'],
                    'type' => $activity['type'],
                    'description' => $activity['description'] ?? null,
                    'start_date' => isset($activity['start_date']) ? Carbon::parse($activity['start_date']) : null,
                    'end_date' => isset($activity['end_date']) ? Carbon::parse($activity['end_date']) : null,
                    'rt_type' => $activity['rt_type'],
                    'rt_visibility' => $activity['rt_visibility'],
                    'location' => $activity['location'] ?? null,
                    'cover_picture' => $activity['cover_picture'] ?? null,
                    'canceled' => $activity['canceled'] ?? false,
                    'latitude' => $activity['latitude'] ?? null,
                    'longitude' => $activity['longitude'] ?? null,
                    'api_endpoint_id' => $activity['api_endpoint_id'],
                    'created_at' => $now, // Set creation time for new activity
                    'updated_at' => $now, // Set modification time for new activity
                ];
            }
        }, $activities);

        // Filter out null values (unchanged activities)
        $upsertData = array_filter($upsertData, fn($item) => $item !== null);
        
        Log::debug("[ImportActivities] Upsert data prepared", [
            'total_activities' => count($activities),
            'activities_to_upsert' => count($upsertData),
            'timestamp_stats' => $timestampStats
        ]);

        if (!empty($upsertData)) {
            // Use upsert to perform an efficient "insert or update"
            Activity::upsert($upsertData, ['id'], [
                'level_id', 'name', 'type', 'description', 'start_date', 'end_date',
                'rt_type', 'rt_visibility', 'location', 'cover_picture', 'canceled',
                'latitude', 'longitude', 'api_endpoint_id', 'updated_at'
            ]);
        }
        
        $this->info("Database operations complete. New: {$timestampStats['new_activities']}, Updated: {$timestampStats['updated_activities']}, Restored: {$timestampStats['restored_activities']}, Unchanged: {$timestampStats['unchanged_activities']}");
        Log::info("[ImportActivities] Database operations complete", $timestampStats);
    }

    /**
     * Check if an activity needs to be updated by comparing hashes
     */
    private function activityNeedsUpdate(Activity $existing, array $newData): bool
    {
        // Normalize data for comparison
        $existingHash = $this->generateActivityHash($existing);
        $newHash = $this->generateActivityHash($newData);
        
        $needsUpdate = $existingHash !== $newHash;
        
        if ($needsUpdate) {
            Log::debug("[ImportActivities] Activity needs update", [
                'activity_id' => $existing->id,
                'name' => $existing->name,
                'existing_hash' => $existingHash,
                'new_hash' => $newHash
            ]);
        }
        
        return $needsUpdate;
    }

    /**
     * Generate a hash for activity data comparison
     */
    private function generateActivityHash($data): string
    {
        // Fields to include in hash comparison
        $fieldsToHash = [
            'level_id',
            'name', 
            'type',
            'description',
            'start_date',
            'end_date',
            'rt_type',
            'rt_visibility',
            'location',
            'cover_picture',
            'canceled',
            'latitude',
            'longitude',
            'api_endpoint_id'
        ];

        $dataToHash = [];
        
        foreach ($fieldsToHash as $field) {
            if ($data instanceof Activity) {
                $dataToHash[$field] = $data->$field;
            } elseif (is_array($data)) {
                $dataToHash[$field] = $data[$field] ?? null;
            }
        }
        
        // Sort array keys to ensure consistent hash regardless of field order
        ksort($dataToHash);
        return hash('md5', serialize($dataToHash));
    }

    /**
     * Elabora i campi delle activities (es: sostituzioni su location, pulizia HTML da description)
     */
    private function processActivityFields(array $activities): array
    {
        foreach ($activities as &$activity) {
            // Pulizia location
            if (!empty($activity['location']) && strpos($activity['location'], '(Italië)') !== false) {
                $activity['location'] = str_replace('(Italië)', '(Italia)', $activity['location']);
            }
            // Pulizia HTML da description
            if (!empty($activity['description'])) {
                $activity['description'] = $this->sanitizeHtmlContent($activity['description']);
            }
        }
        unset($activity); // buona pratica per riferimenti
        return $activities;
    }

    /**
     * Soft delete activities that are no longer present in the API response
     */
    private function softDeleteObsoleteActivities($endpoints, array $allActivities): void
    {
        $this->info("Starting soft delete of obsolete activities...");
        Log::info("[ImportActivities] Starting soft delete of obsolete activities", [
            'total_activities_from_api' => count($allActivities),
            'total_endpoints' => count($endpoints)
        ]);

        // Early return if no activities
        if (empty($allActivities)) {
            $this->info("No activities from API - skipping soft delete process.");
            Log::info("[ImportActivities] No activities from API - skipping soft delete");
            return;
        }

        // Group activities by endpoint using array_reduce for better performance
        $activitiesByEndpoint = array_reduce($allActivities, function($carry, $activity) {
            $endpointId = $activity['api_endpoint_id'];
            $carry[$endpointId][] = $activity['id'];
            return $carry;
        }, []);

        $softDeleteStats = [
            'endpoints_processed' => 0,
            'activities_soft_deleted' => 0,
            'activities_preserved' => 0,
            'endpoints_with_obsolete_activities' => 0
        ];

        foreach ($endpoints as $endpoint) {
            $endpointId = $endpoint->id;
            $currentActivityIds = $activitiesByEndpoint[$endpointId] ?? [];
            
            Log::debug("[ImportActivities] Processing endpoint for soft delete", [
                'endpoint_id' => $endpointId,
                'endpoint_description' => $endpoint->description,
                'current_activities_count' => count($currentActivityIds)
            ]);
            
            try {
                // Find obsolete activities for this endpoint
                $obsoleteActivities = $this->findObsoleteActivities($endpointId, $currentActivityIds);
                
                if (!empty($obsoleteActivities)) {
                    $this->performSoftDelete($obsoleteActivities, $endpoint);
                    $softDeleteStats['activities_soft_deleted'] += count($obsoleteActivities);
                    $softDeleteStats['endpoints_with_obsolete_activities']++;
                }
                
                $softDeleteStats['activities_preserved'] += count($currentActivityIds);
                
            } catch (\Exception $e) {
                $this->error("Failed to process soft delete for endpoint {$endpoint->description}: {$e->getMessage()}");
                Log::error("[ImportActivities] Endpoint soft delete failed", [
                    'endpoint_id' => $endpointId,
                    'endpoint_description' => $endpoint->description,
                    'exception' => $e->getMessage()
                ]);
            }
            
            $softDeleteStats['endpoints_processed']++;
        }

        $this->info("Soft delete completed. Marked {$softDeleteStats['activities_soft_deleted']} activities as deleted across {$softDeleteStats['endpoints_with_obsolete_activities']} endpoints.");
        Log::info("[ImportActivities] Soft delete completed", $softDeleteStats);
    }

    /**
     * Find activities that exist in database but are no longer present in API response
     * Returns only activities that are NOT already soft-deleted
     */
    private function findObsoleteActivities(int $endpointId, array $currentActivityIds): array
    {
        // Get all non-deleted activities for this endpoint
        $existingActivities = Activity::where('api_endpoint_id', $endpointId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        // Find activities that exist in DB but not in current API response
        $obsoleteIds = array_diff($existingActivities, $currentActivityIds);
        
        Log::debug("[ImportActivities] Found obsolete activities", [
            'endpoint_id' => $endpointId,
            'existing_activities_count' => count($existingActivities),
            'current_api_activities_count' => count($currentActivityIds),
            'obsolete_activities_count' => count($obsoleteIds),
            'obsolete_activity_ids' => $obsoleteIds
        ]);

        return $obsoleteIds;
    }

    /**
     * Perform soft delete on obsolete activities
     */
    private function performSoftDelete(array $obsoleteIds, ApiEndpoint $endpoint): void
    {
        if (empty($obsoleteIds)) {
            return;
        }

        $count = count($obsoleteIds);
        
        Log::info("[ImportActivities] Soft deleting obsolete activities", [
            'endpoint_id' => $endpoint->id,
            'endpoint_description' => $endpoint->description,
            'count' => $count,
            'activity_ids' => $obsoleteIds
        ]);

        // Soft delete the obsolete activities
        $deletedCount = Activity::whereIn('id', $obsoleteIds)->delete();
        
        $this->info("Soft deleted {$deletedCount} obsolete activities from endpoint: {$endpoint->description}");
        
        // Log completion with verification
        if ($deletedCount !== $count) {
            Log::warning("[ImportActivities] Soft delete count mismatch", [
                'endpoint_id' => $endpoint->id,
                'expected_count' => $count,
                'actual_deleted_count' => $deletedCount
            ]);
        }
    }

    /**
     * Sanitize HTML content by removing dangerous tags and attributes
     */
    private function sanitizeHtmlContent(string $html): string
    {
        // Define allowed tags and their allowed attributes
        $allowedTags = [
            'p' => [],
            'br' => [],
            'b' => [],
            'i' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'a' => ['href']
        ];

        // Dangerous attributes to remove globally
        $dangerousAttributes = ['style', 'on*']; // 'on*' for any event handlers

        // First, strip all tags except allowed ones
        $allowedTagsString = '<' . implode('><', array_keys($allowedTags)) . '>';
        $html = strip_tags($html, $allowedTagsString);

        try {
            // Load HTML with DOMDocument
            $dom = new \DOMDocument();
            // Suppress warnings for malformed HTML
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);

            // Remove dangerous attributes globally
            foreach ($dangerousAttributes as $attrPattern) {
                $query = "//@{$attrPattern}";
                $attributes = $xpath->query($query);
                foreach ($attributes as $attr) {
                    $attr->parentNode->removeAttribute($attr->nodeName);
                }
            }

            // Process each allowed tag
            foreach ($allowedTags as $tag => $allowedAttributes) {
                $elements = $xpath->query("//{$tag}");
                
                foreach ($elements as $element) {
                    // Save allowed attributes
                    $savedAttributes = [];
                    foreach ($allowedAttributes as $attr) {
                        if ($element->hasAttribute($attr)) {
                            $savedAttributes[$attr] = $element->getAttribute($attr);
                        }
                    }
                    
                    // Remove all attributes
                    while ($element->attributes->length) {
                        $element->removeAttribute($element->attributes->item(0)->nodeName);
                    }
                    
                    // Re-add sanitized attributes
                    foreach ($savedAttributes as $attr => $value) {
                        $sanitizedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                        if ($attr === 'href' && $this->isValidUrl($value)) {
                            $element->setAttribute($attr, $sanitizedValue);
                            $element->setAttribute('target', '_blank');
                            $element->setAttribute('rel', 'noopener noreferrer');
                        } else {
                            $element->setAttribute($attr, $sanitizedValue);
                        }
                    }
                }
            }

            // Escape text nodes to prevent XSS
            $this->escapeTextNodes($dom);

            // Get cleaned HTML
            $cleanedHtml = $dom->saveHTML($dom->documentElement);
            
            // Remove XML declaration and extra tags
            $cleanedHtml = preg_replace('/^<html><body>/', '', $cleanedHtml);
            $cleanedHtml = preg_replace('/<\/body><\/html>$/', '', $cleanedHtml);
            $cleanedHtml = preg_replace('/^<\?xml[^>]*\?>/', '', $cleanedHtml);

            return trim($cleanedHtml);

        } catch (\Exception $e) {
            Log::error("[ImportActivities] HTML sanitization failed", ['exception' => $e->getMessage()]);
            // Fallback: strict strip_tags without attributes
            return strip_tags($html, $allowedTagsString);
        }
    }

    /**
     * Escape text nodes in DOM to prevent XSS
     */
    private function escapeTextNodes(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $textNodes = $xpath->query('//text()');
        
        foreach ($textNodes as $node) {
            if (trim($node->nodeValue) !== '') {
                $node->nodeValue = htmlspecialchars($node->nodeValue, ENT_QUOTES, 'UTF-8');
            }
        }
    }

    /**
     * Validate if a URL is safe
     */
    private function isValidUrl(string $url): bool
    {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check for dangerous protocols
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme'])) {
            return false;
        }
        
        $allowedSchemes = ['http', 'https', 'mailto'];
        return in_array(strtolower($parsedUrl['scheme']), $allowedSchemes);
    }
} 