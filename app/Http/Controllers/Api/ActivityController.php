<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
} 