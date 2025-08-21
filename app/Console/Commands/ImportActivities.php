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
            $this->info("Processing endpoint: {$endpoint->url} ({$endpoint->description})");
            Log::info("[ImportActivities] Processing endpoint", ['endpoint_url' => $endpoint->url, 'endpoint_description' => $endpoint->description]);
            try {
                $activitiesFromApi = $this->fetchActivityList($endpoint);
                Log::info("[ImportActivities] Activities fetched from API", ['count' => is_array($activitiesFromApi) ? count($activitiesFromApi) : 0]);
                $filteredActivities = $this->filterActivities($activitiesFromApi);
                Log::info("[ImportActivities] Filtered activities", ['count' => is_array($filteredActivities) ? count($filteredActivities) : 0]);
                $detailedActivities = $this->fetchActivityDetails($filteredActivities, $endpoint);
                Log::info("[ImportActivities] Detailed activities", ['count' => is_array($detailedActivities) ? count($detailedActivities) : 0]);
                $allActivities = array_merge($allActivities, $detailedActivities);
            } catch (\Exception $e) {
                $this->error("Failed to process endpoint {$endpoint->url}: {$e->getMessage()}");
                Log::error("[ImportActivities] Endpoint processing failed", ['endpoint_url' => $endpoint->url, 'exception' => $e->getMessage()]);
            }
        }
        
        $this->info("Finished fetching data. Found " . count($allActivities) . " total activities to process.");
        Log::info("[ImportActivities] Finished fetching data", ['total_activities' => count($allActivities)]);

        if (!empty($allActivities)) {
            $allActivities = $this->processActivityFields($allActivities);
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
        Log::info("[ImportActivities] Fetching activity list", ['url' => $baseUrl]);
        $response = Http::withHeaders($this->getAuthHeaders($endpoint))->get($baseUrl);
        Log::info("[ImportActivities] Activity list response", ['status' => $response->status()]);
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
        Log::info("[ImportActivities] Fetching activity details", ['baseUrl' => $baseUrl, 'count' => is_array($activities) ? count($activities) : 0]);
        
        // Statistics for geocoding optimization
        $geocodeStats = [
            'attempted' => 0,
            'skipped_coordinates_exist' => 0,
            'skipped_no_location' => 0,
            'location_removed' => 0,
            'successful' => 0,
            'failed' => 0
        ];

        foreach ($activities as $activity) {
            try {
                $detailUrl = $baseUrl . $activity['id'] . '/';
                Log::info("[ImportActivities] Fetching activity detail", ['detailUrl' => $detailUrl, 'activity_id' => $activity['id']]);
                $response = Http::withHeaders($this->getAuthHeaders($endpoint))->get($detailUrl);
                Log::info("[ImportActivities] Activity detail response", ['status' => $response->status(), 'activity_id' => $activity['id']]);
                
                $details = $response->successful() ? $response->json() : [];
                $fullActivity = array_merge($activity, $details);

                // Pulizia campo location
                if (!empty($fullActivity['location']) && strpos($fullActivity['location'], '(Italië)') !== false) {
                    $fullActivity['location'] = str_replace('(Italië)', '(Italia)', $fullActivity['location']);
                }

                // Check if activity exists in database and get existing data for optimization
                $existingActivity = Activity::find($activity['id']);
                
                // Geocode optimization logic
                $shouldGeocode = false;
                $geocodeReason = '';
                
                // If activity doesn't exist in database (new activity)
                if (!$existingActivity) {
                    if (!empty($fullActivity['location'])) {
                        $shouldGeocode = true;
                        $geocodeReason = 'new_activity';
                    }
                }
                // If activity exists in database
                else {
                    // If location has been removed
                    if (empty($fullActivity['location'])) {
                        $fullActivity['latitude'] = null;
                        $fullActivity['longitude'] = null;
                        $geocodeStats['location_removed']++;
                        Log::info("[ImportActivities] Location removed, coordinates set to null", [
                            'activity_id' => $activity['id'],
                            'previous_location' => $existingActivity->location
                        ]);
                    }
                    // If location is present
                    else {
                        // If location has changed
                        if ($existingActivity->location !== $fullActivity['location']) {
                            $shouldGeocode = true;
                            $geocodeReason = 'location_changed';
                        }
                        // If location is the same but coordinates are missing
                        elseif (empty($fullActivity['latitude']) || empty($fullActivity['longitude'])) {
                            $shouldGeocode = true;
                            $geocodeReason = 'coordinates_missing';
                        }
                    }
                }
                
                // Perform geocoding if needed
                if ($shouldGeocode) {
                    $geocodeStats['attempted']++;
                    Log::info("[ImportActivities] Attempting geocode", [
                        'location' => $fullActivity['location'], 
                        'activity_id' => $activity['id'],
                        'reason' => $geocodeReason
                    ]);
                    $coords = $this->geocodeLocation($fullActivity['location']);
                    Log::info("[ImportActivities] Geocode result", [
                        'coords_found' => $coords ? true : false, 
                        'activity_id' => $activity['id']
                    ]);
                    if ($coords) {
                        $fullActivity['latitude'] = $coords['lat'];
                        $fullActivity['longitude'] = $coords['lon'];
                        $geocodeStats['successful']++;
                    } else {
                        $geocodeStats['failed']++;
                    }
                } else {
                    // Log skipped geocoding
                    if ($existingActivity && !empty($existingActivity->latitude) && !empty($existingActivity->longitude)) {
                        $geocodeStats['skipped_coordinates_exist']++;
                        Log::info("[ImportActivities] Geocoding skipped - coordinates already exist", [
                            'activity_id' => $activity['id'],
                            'existing_location' => $existingActivity->location,
                            'new_location' => $fullActivity['location']
                        ]);
                    } elseif (empty($fullActivity['location'])) {
                        $geocodeStats['skipped_no_location']++;
                        Log::info("[ImportActivities] Geocoding skipped - no location provided", [
                            'activity_id' => $activity['id']
                        ]);
                    }
                }
                Log::info("[ImportActivities] Final activity before upsert", ['activity_id' => $activity['id'], 'has_lat' => !empty($fullActivity['latitude']), 'has_lon' => !empty($fullActivity['longitude'])]);
                $fullActivity['api_endpoint_id'] = $endpoint->id;
                $detailedActivities[] = $fullActivity;

            } catch (\Exception $e) {
                $this->error("Could not fetch details for activity ID {$activity['id']}: {$e->getMessage()}");
                Log::warning("[ImportActivities] Activity detail fetch failed", ['id' => $activity['id'], 'exception' => $e->getMessage()]);
            }
        }
        
        $this->info("Fetched details for " . count($detailedActivities) . " activities.");
        Log::info("[ImportActivities] Fetched details for activities", [
            'count' => count($detailedActivities),
            'geocode_stats' => $geocodeStats
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
            Log::info("[ImportActivities] Geocoding location (Google)", ['location' => $location]);
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
            Log::info("[ImportActivities] Google Geocode response", ['status' => $response->status()]);

            if ($response->status() === 200) {
                $payload = $response->json();
                $status = $payload['status'] ?? null;
                $results = $payload['results'] ?? [];
                if ($status === 'OK' && !empty($results)) {
                    $locationData = $results[0]['geometry']['location'] ?? null;
                    if ($locationData && isset($locationData['lat'], $locationData['lng'])) {
                        Log::info("[ImportActivities] Geocode data (Google)", ['coords_found' => true]);
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

    private function upsertActivities(array $activities): void
    {
        $this->info("Saving " . count($activities) . " activities to the database...");
        Log::info("[ImportActivities] Upserting activities", ['count' => count($activities)]);

        // Prepare data for upsert by mapping keys
        $upsertData = array_map(fn($activity) => [
            'id' => $activity['id'],
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
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $activities);
        Log::info("[ImportActivities] Upsert data prepared", ['count' => count($upsertData)]);

        // Use upsert to perform an efficient "insert or update"
        Activity::upsert($upsertData, ['id'], [
            'level_id', 'name', 'type', 'description', 'start_date', 'end_date',
            'rt_type', 'rt_visibility', 'location', 'cover_picture', 'canceled',
            'latitude', 'longitude', 'api_endpoint_id', 'updated_at'
        ]);
        
        $this->info("Database operations complete.");
        Log::info("[ImportActivities] Database operations complete.");
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
                // Rimuove tag HTML indesiderati, lasciando solo quelli base (es: <p>, <ul>, <li>, <b>, <i>, <strong>, <em>, <a>, <br>)
                $allowed_tags = '<p><ul><ol><li><b><i><strong><em><a><br>';
                $activity['description'] = strip_tags($activity['description'], $allowed_tags);
            }
        }
        unset($activity); // buona pratica per riferimenti
        return $activities;
    }
} 