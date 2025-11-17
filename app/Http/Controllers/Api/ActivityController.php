<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
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

    /**
     * Get statistics and KPIs for activities
     */
    public function statistics()
    {
        $now = Carbon::now();
        $currentMonth = $now->startOfMonth();
        
        // Base query for active activities
        $baseQuery = Activity::with(['apiEndpoint:id,description,area'])
            ->whereNull('deleted_at');
        
        // Overview KPIs
        $totalEvents = $baseQuery->count();
        $activeEvents = $baseQuery->where('canceled', false)->count();
        $canceledEvents = $baseQuery->where('canceled', true)->count();
        $currentMonthEvents = $baseQuery->where('start_date', '>=', $currentMonth)->count();
        $nextEvent = $baseQuery->where('start_date', '>', $now)
            ->orderBy('start_date', 'asc')
            ->first();

        // Top tables by events
        $topTables = Activity::select('api_endpoints.description as table_name', DB::raw('count(*) as event_count'))
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->whereNull('activities.deleted_at')
            ->groupBy('api_endpoints.id', 'api_endpoints.description')
            ->orderBy('event_count', 'desc')
            ->limit(10)
            ->get();

        // Events by zone
        $eventsByZone = Activity::select('api_endpoints.area as zone', DB::raw('count(*) as event_count'))
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->whereNull('activities.deleted_at')
            ->groupBy('api_endpoints.area')
            ->orderBy('event_count', 'desc')
            ->get();

        // Monthly distribution (last 12 months)
        $monthlyDistribution = Activity::select(
                DB::raw('DATE_FORMAT(start_date, "%Y-%m") as month'),
                DB::raw('count(*) as event_count')
            )
            ->whereNull('deleted_at')
            ->where('start_date', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Event types distribution
        $eventTypes = Activity::select('rt_type', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('rt_type')
            ->orderBy('count', 'desc')
            ->get();

        // Event visibility distribution
        $eventVisibility = Activity::select('rt_visibility', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('rt_visibility')
            ->orderBy('count', 'desc')
            ->get();

        // Multi-day vs single-day events
        $multiDayEvents = Activity::whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereRaw('DATE(start_date) != DATE(end_date)')
            ->count();
        
        $singleDayEvents = Activity::whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereRaw('DATE(start_date) = DATE(end_date)')
            ->count();
        
        $totalEventsWithDates = $multiDayEvents + $singleDayEvents;
        $multiDayPercentage = $totalEventsWithDates > 0 ? round(($multiDayEvents / $totalEventsWithDates) * 100, 1) : 0;
        $singleDayPercentage = $totalEventsWithDates > 0 ? round(($singleDayEvents / $totalEventsWithDates) * 100, 1) : 0;

        // Most common day of week for single-day events
        $dayOfWeekDistribution = Activity::select(
                DB::raw('DAYOFWEEK(start_date) as day_of_week'),
                DB::raw('DAYNAME(start_date) as day_name'),
                DB::raw('count(*) as event_count')
            )
            ->whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereRaw('DATE(start_date) = DATE(end_date)')
            ->groupBy('day_of_week', 'day_name')
            ->orderBy('event_count', 'desc')
            ->get();

        // Days from creation to event start (for bell curve)
        // Get events with their details for tooltip
        $daysFromCreation = Activity::with(['apiEndpoint:id,description'])
            ->select('activities.*', DB::raw('DATEDIFF(start_date, created_at) as days_diff'))
            ->whereNull('activities.deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('created_at')
            ->whereRaw('start_date >= created_at')
            ->orderBy('days_diff')
            ->get()
            ->groupBy('days_diff')
            ->map(function ($events) {
                return [
                    'days_diff' => $events->first()->days_diff,
                    'event_count' => $events->count(),
                    'events' => $events->map(function ($event) {
                        return [
                            'name' => $event->name,
                            'table' => $event->apiEndpoint->description ?? null,
                            'start_date' => $event->start_date ? $event->start_date->format('d/m/Y') : null,
                            'created_at' => $event->created_at ? $event->created_at->format('d/m/Y') : null,
                        ];
                    })->toArray()
                ];
            })
            ->values();

        return response()->json([
            'overview' => [
                'total_events' => $totalEvents,
                'active_events' => $activeEvents,
                'canceled_events' => $canceledEvents,
                'current_month_events' => $currentMonthEvents,
                'next_event' => $nextEvent ? [
                    'name' => $nextEvent->name,
                    'start_date' => $nextEvent->start_date,
                    'location' => $nextEvent->location,
                    'table' => $nextEvent->apiEndpoint->description ?? null
                ] : null
            ],
            'top_tables' => $topTables,
            'events_by_zone' => $eventsByZone,
            'monthly_distribution' => $monthlyDistribution,
            'event_types' => $eventTypes,
            'event_visibility' => $eventVisibility,
            'multi_day_events' => $multiDayEvents,
            'single_day_events' => $singleDayEvents,
            'multi_day_percentage' => $multiDayPercentage,
            'single_day_percentage' => $singleDayPercentage,
            'day_of_week_distribution' => $dayOfWeekDistribution,
            'days_from_creation' => $daysFromCreation
        ]);

        // Best organized tables - average days from publication to event start
        $bestOrganizedTables = Activity::select(
                'api_endpoints.description as table_name',
                DB::raw('AVG(DATEDIFF(activities.start_date, activities.created_at)) as avg_days'),
                DB::raw('COUNT(*) as event_count')
            )
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->whereNull('activities.deleted_at')
            ->whereNotNull('activities.start_date')
            ->whereNotNull('activities.created_at')
            ->whereRaw('activities.start_date >= activities.created_at')
                ->groupBy('api_endpoints.id', 'api_endpoints.description')
                ->havingRaw('COUNT(*) >= 5') // At least 5 events for meaningful average
                ->orderBy('avg_days', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'table_name' => $item->table_name,
                    'avg_days' => round($item->avg_days, 1),
                    'event_count' => $item->event_count,
                ];
            });

        $responseData = [
            'overview' => [
                'total_events' => $totalEvents,
                'active_events' => $activeEvents,
                'canceled_events' => $canceledEvents,
                'current_month_events' => $currentMonthEvents,
                'next_event' => $nextEvent ? [
                    'name' => $nextEvent->name,
                    'start_date' => $nextEvent->start_date,
                    'location' => $nextEvent->location,
                    'table' => $nextEvent->apiEndpoint->description ?? null
                ] : null
            ],
            'top_tables' => $topTables,
            'events_by_zone' => $eventsByZone,
            'monthly_distribution' => $monthlyDistribution,
            'event_types' => $eventTypes,
            'event_visibility' => $eventVisibility,
            'multi_day_events' => $multiDayEvents,
            'single_day_events' => $singleDayEvents,
            'multi_day_percentage' => $multiDayPercentage,
            'single_day_percentage' => $singleDayPercentage,
            'day_of_week_distribution' => $dayOfWeekDistribution,
            'days_from_creation' => $daysFromCreation,
            'best_organized_tables' => $bestOrganizedTables
        ];
        
        return response()->json($responseData);
    }
} 