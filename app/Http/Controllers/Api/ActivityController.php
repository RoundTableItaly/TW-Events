<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\StatisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activities = Activity::with(['apiEndpoint:id,description,area'])->orderBy('start_date', 'desc')->get();
        
        // Mappa le attività per includere description e area direttamente
        $activities = $activities->map(function ($activity) {
            $activityArray = $activity->toArray();
            $activityArray['api_endpoint_description'] = $activity->apiEndpoint->description ?? null;
            $activityArray['api_endpoint_area'] = $activity->apiEndpoint->area ?? null;
            return $activityArray;
        });
        
        return response()->json($activities);
    }

    public function statistics(StatisticsService $statisticsService)
    {
        // Get all statistics from service
        $statistics = $statisticsService->getAllStatistics();

        // Return only data used by JavaScript frontend
        return response()->json([
            'top_tables' => $statistics['top_tables'],
            'events_by_zone' => $statistics['events_by_zone'],
            'monthly_distribution' => $statistics['monthly_distribution'],
            'event_types' => $statistics['event_types'],
            'multi_day_events' => $statistics['multi_day_events'],
            'single_day_events' => $statistics['single_day_events'],
            'day_of_week_distribution' => $statistics['day_of_week_distribution'],
            'days_from_creation' => $statistics['days_from_creation'],
        ]);
    }
} 