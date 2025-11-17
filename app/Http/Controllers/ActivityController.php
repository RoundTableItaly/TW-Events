<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Support\Facades\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityController extends Controller
{
    /**
     * Export all activities as an ICS calendar file.
     */
    public function ics()
    {
        $cacheKey = 'ics_calendar_file';
        $cacheTtl = now()->addMinutes(60);

        $ics = Cache::remember($cacheKey, $cacheTtl, function () {
            $activities = Activity::all();

            $calendar = Calendar::create('Round Table Italia Events');
            foreach ($activities as $activity) {
                $calendar->event(Event::create()
                    ->name($activity->name)
                    ->description($activity->description)
                    ->address($activity->location)
                    ->startsAt($activity->start_date)
                    ->endsAt($activity->end_date)
                );
            }
            return $calendar->get();
        });

        return Response::make($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="events.ics"',
        ]);
    }

    /**
     * Get statistics data for API endpoint
     */
    public function statistics()
    {
        $cacheKey = 'statistics_data';
        $cacheTtl = now()->addMinutes(30);

        $statistics = Cache::remember($cacheKey, $cacheTtl, function () {
            $now = now();
            $startOfMonth = $now->copy()->startOfMonth();
            
            $totalEvents = Activity::count();
            $activeEvents = Activity::whereNull('deleted_at')->count();
            $canceledEvents = Activity::whereNotNull('deleted_at')->count();
            $currentMonthEvents = Activity::where('start_date', '>=', $startOfMonth)
                                        ->where('start_date', '<=', $now)
                                        ->count();

            return [
                'overview' => [
                    'total_events' => $totalEvents,
                    'active_events' => $activeEvents,
                    'canceled_events' => $canceledEvents,
                    'current_month_events' => $currentMonthEvents,
                ]
            ];
        });

        return response()->json($statistics);
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
        
        // Start from June 1st
        $juneFirst = Carbon::create($year, 6, 1);
        
        // Find the first Saturday
        // Carbon dayOfWeek: 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        $dayOfWeek = $juneFirst->dayOfWeek;
        
        // Calculate days to add to reach Saturday
        // If it's already Saturday (6), add 0 days
        // If it's Sunday (0), add 6 days
        // If it's Monday (1), add 5 days, etc.
        // Formula: (6 - dayOfWeek + 7) % 7 ensures positive result
        $daysToAdd = (6 - $dayOfWeek + 7) % 7;
        
        return $juneFirst->copy()->addDays($daysToAdd);
    }

    /**
     * Show statistics page with overview data
     */
    public function statisticsPage()
    {
        $cacheKey = 'statistics_page_data';
        $cacheTtl = now()->addMinutes(30);

        $statistics = Cache::remember($cacheKey, $cacheTtl, function () {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            
            // Calculate social year dates
            $currentYear = $now->year;
            $socialYearStart = $this->getSocialYearStart($currentYear);
            
            // If we're before the social year start, the current social year started last year
            if ($now->lt($socialYearStart)) {
                $currentSocialYearStart = $this->getSocialYearStart($currentYear - 1);
                $currentSocialYearEnd = $socialYearStart->copy()->subDay();
                $previousSocialYearStart = $this->getSocialYearStart($currentYear - 2);
                $previousSocialYearEnd = $currentSocialYearStart->copy()->subDay();
            } else {
                $currentSocialYearStart = $socialYearStart;
                $currentSocialYearEnd = $this->getSocialYearStart($currentYear + 1)->copy()->subDay();
                $previousSocialYearStart = $this->getSocialYearStart($currentYear - 1);
                $previousSocialYearEnd = $currentSocialYearStart->copy()->subDay();
            }
            
            $totalEvents = Activity::count();
            $futureEvents = Activity::whereNull('deleted_at')
                                    ->where('start_date', '>', $now)
                                    ->count();
            $pastEvents = Activity::whereNull('deleted_at')
                                  ->where('start_date', '<=', $now)
                                  ->count();
            $canceledEvents = Activity::whereNotNull('deleted_at')->count();
            $currentMonthEvents = Activity::where('start_date', '>=', $startOfMonth)
                                        ->where('start_date', '<=', $now)
                                        ->count();
            
            // Events in current social year
            $currentSocialYearEvents = Activity::whereNull('deleted_at')
                                            ->where('start_date', '>=', $currentSocialYearStart)
                                            ->where('start_date', '<=', $currentSocialYearEnd)
                                            ->count();
            
            // Events in previous social year
            $previousSocialYearEvents = Activity::whereNull('deleted_at')
                                                ->where('start_date', '>=', $previousSocialYearStart)
                                                ->where('start_date', '<=', $previousSocialYearEnd)
                                                ->count();

            // Top tables by event count - only Tavole, exclude Zone and Nazionale
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
            $daysFromCreationEvents = Activity::with(['apiEndpoint:id,description'])
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

            // Return raw data for bell curve - filtering will be done in JavaScript
            // This allows users to configure filters dynamically

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

            // Tables without activities - tables with few or no events
            // Include only tables (type = 'Tavola'), exclude Nazionale and Zona
            $tablesWithoutActivities = DB::table('api_endpoints')
                ->select(
                    'api_endpoints.id',
                    'api_endpoints.description as table_name',
                    'api_endpoints.area',
                    DB::raw('COUNT(activities.id) as event_count')
                )
                ->leftJoin('activities', function($join) {
                    $join->on('api_endpoints.id', '=', 'activities.api_endpoint_id')
                         ->whereNull('activities.deleted_at');
                })
                ->where('api_endpoints.type', 'Tavola') // Only actual tables
                ->groupBy('api_endpoints.id', 'api_endpoints.description', 'api_endpoints.area')
                ->orderBy('event_count', 'asc')
                ->orderBy('api_endpoints.description', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'table_name' => $item->table_name,
                        'area' => $item->area,
                        'event_count' => (int)$item->event_count,
                    ];
                });

            // Group by area for zone-based table
            $tablesWithoutActivitiesByZone = $tablesWithoutActivities
                ->groupBy('area')
                ->map(function ($tables, $zone) {
                    return [
                        'zone' => $zone ?? 'Senza zona',
                        'tables' => $tables->values()->all(),
                        'total_tables' => $tables->count(),
                        'tables_with_no_events' => $tables->where('event_count', 0)->count(),
                    ];
                })
                ->sortBy('zone')
                ->values();

            // Next event
            $nextEvent = Activity::with(['apiEndpoint:id,description,area'])
                ->whereNull('deleted_at')
                ->where('start_date', '>', $now)
                ->orderBy('start_date', 'asc')
                ->first();

            return [
                'total_events' => $totalEvents,
                'future_events' => $futureEvents,
                'past_events' => $pastEvents,
                'canceled_events' => $canceledEvents,
                'current_month_events' => $currentMonthEvents,
                'current_social_year_events' => $currentSocialYearEvents,
                'previous_social_year_events' => $previousSocialYearEvents,
                'current_social_year_start' => $currentSocialYearStart->format('d/m/Y'),
                'current_social_year_end' => $currentSocialYearEnd->format('d/m/Y'),
                'previous_social_year_start' => $previousSocialYearStart->format('d/m/Y'),
                'previous_social_year_end' => $previousSocialYearEnd->format('d/m/Y'),
                'top_tables' => $topTables,
                'events_by_zone' => $eventsByZone,
                'monthly_distribution' => $monthlyDistribution,
                'event_types' => $eventTypes,
                'multi_day_events' => $multiDayEvents,
                'single_day_events' => $singleDayEvents,
                'multi_day_percentage' => $multiDayPercentage,
                'single_day_percentage' => $singleDayPercentage,
                'day_of_week_distribution' => $dayOfWeekDistribution,
                'days_from_creation' => $daysFromCreationEvents,
                'best_organized_tables' => $bestOrganizedTables,
                'tables_without_activities' => $tablesWithoutActivities,
                'tables_without_activities_by_zone' => $tablesWithoutActivitiesByZone,
                'next_event' => $nextEvent ? [
                    'name' => $nextEvent->name,
                    'start_date' => $nextEvent->start_date,
                    'location' => $nextEvent->location,
                    'table' => $nextEvent->apiEndpoint->description ?? null
                ] : null,
            ];
        });

        return view('statistics', compact('statistics'));
    }
}