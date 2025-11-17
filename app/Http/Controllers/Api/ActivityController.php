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
     * Calculate the start date of the current social year
     * The social year starts on the first Saturday of June
     */
    private function getSocialYearStart($year = null)
    {
        if ($year === null) {
            $year = Carbon::now()->year;
        }
        
        $juneFirst = Carbon::create($year, 6, 1);
        $dayOfWeek = $juneFirst->dayOfWeek;
        $daysToAdd = (6 - $dayOfWeek + 7) % 7;
        
        return $juneFirst->copy()->addDays($daysToAdd);
    }

    public function statistics()
    {
        $now = Carbon::now();
        $currentMonth = $now->startOfMonth();
        
        // Calculate social year dates
        $currentYear = $now->year;
        $socialYearStart = $this->getSocialYearStart($currentYear);
        
        // If we're before the social year start, the current social year started last year
        if ($now->lt($socialYearStart)) {
            $currentSocialYearStart = $this->getSocialYearStart($currentYear - 1);
            $currentSocialYearEnd = $socialYearStart->copy()->subDay();
        } else {
            $currentSocialYearStart = $socialYearStart;
            $currentSocialYearEnd = $this->getSocialYearStart($currentYear + 1)->copy()->subDay();
        }
        
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

        // Top tables by events - only Tavole, exclude Zone and Nazionale
        // Filter by current social year
        // No limit - show all tables (top 10 is shown separately above)
        $topTables = Activity::select('api_endpoints.description as table_name', DB::raw('count(*) as event_count'))
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->whereNull('activities.deleted_at')
            ->where('api_endpoints.type', 'Tavola') // Only Tavole, exclude Zone and Nazionale
            ->where('activities.start_date', '>=', $currentSocialYearStart)
            ->where('activities.start_date', '<=', $currentSocialYearEnd)
            ->groupBy('api_endpoints.id', 'api_endpoints.description')
            ->orderBy('event_count', 'desc')
            ->get();

        // Events by zone - only from Tavole and Zone endpoints, exclude Nazionale (RT Italia)
        // Filter by current social year
        $eventsByZone = Activity::select('api_endpoints.area as zone', DB::raw('count(*) as event_count'))
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->whereNull('activities.deleted_at')
            ->whereIn('api_endpoints.type', ['Tavola', 'Zona']) // Only Tavole and Zone, exclude Nazionale
            ->where('activities.start_date', '>=', $currentSocialYearStart)
            ->where('activities.start_date', '<=', $currentSocialYearEnd)
            ->groupBy('api_endpoints.area')
            ->orderBy('event_count', 'desc')
            ->get();

        // Monthly distribution - current social year only
        $monthlyDistribution = Activity::select(
                DB::raw('DATE_FORMAT(start_date, "%Y-%m") as month'),
                DB::raw('count(*) as event_count')
            )
            ->whereNull('deleted_at')
            ->where('start_date', '>=', $currentSocialYearStart)
            ->where('start_date', '<=', $currentSocialYearEnd)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Event types distribution - current social year only
        $eventTypes = Activity::select('rt_type', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->where('start_date', '>=', $currentSocialYearStart)
            ->where('start_date', '<=', $currentSocialYearEnd)
            ->groupBy('rt_type')
            ->orderBy('count', 'desc')
            ->get();

        // Event visibility distribution
        $eventVisibility = Activity::select('rt_visibility', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('rt_visibility')
            ->orderBy('count', 'desc')
            ->get();

        // Multi-day vs single-day events - current social year only
        $multiDayEvents = Activity::whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '>=', $currentSocialYearStart)
            ->where('start_date', '<=', $currentSocialYearEnd)
            ->whereRaw('DATE(start_date) != DATE(end_date)')
            ->count();
        
        $singleDayEvents = Activity::whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '>=', $currentSocialYearStart)
            ->where('start_date', '<=', $currentSocialYearEnd)
            ->whereRaw('DATE(start_date) = DATE(end_date)')
            ->count();
        
        $totalEventsWithDates = $multiDayEvents + $singleDayEvents;
        $multiDayPercentage = $totalEventsWithDates > 0 ? round(($multiDayEvents / $totalEventsWithDates) * 100, 1) : 0;
        $singleDayPercentage = $totalEventsWithDates > 0 ? round(($singleDayEvents / $totalEventsWithDates) * 100, 1) : 0;

        // Most common day of week for single-day events - current social year only
        $dayOfWeekDistribution = Activity::select(
                DB::raw('DAYOFWEEK(start_date) as day_of_week'),
                DB::raw('DAYNAME(start_date) as day_name'),
                DB::raw('count(*) as event_count')
            )
            ->whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '>=', $currentSocialYearStart)
            ->where('start_date', '<=', $currentSocialYearEnd)
            ->whereRaw('DATE(start_date) = DATE(end_date)')
            ->groupBy('day_of_week', 'day_name')
            ->orderBy('event_count', 'desc')
            ->get();

        // Days from creation to event start (for bell curve) - current social year only
        // Get events with their details for tooltip
        $daysFromCreation = Activity::with(['apiEndpoint:id,description'])
            ->select('activities.*', DB::raw('DATEDIFF(start_date, created_at) as days_diff'))
            ->whereNull('activities.deleted_at')
            ->whereNotNull('start_date')
            ->whereNotNull('created_at')
            ->where('start_date', '>=', $currentSocialYearStart)
            ->where('start_date', '<=', $currentSocialYearEnd)
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
        // Only Tavole, exclude Zone and Nazionale
        // Filter by current social year
        $bestOrganizedTables = Activity::select(
                'api_endpoints.description as table_name',
                DB::raw('AVG(DATEDIFF(activities.start_date, activities.created_at)) as avg_days'),
                DB::raw('COUNT(*) as event_count')
            )
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->whereNull('activities.deleted_at')
            ->whereNotNull('activities.start_date')
            ->whereNotNull('activities.created_at')
            ->where('activities.start_date', '>=', $currentSocialYearStart)
            ->where('activities.start_date', '<=', $currentSocialYearEnd)
            ->whereRaw('activities.start_date >= activities.created_at')
            ->where('api_endpoints.type', 'Tavola') // Only Tavole, exclude Zone and Nazionale
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