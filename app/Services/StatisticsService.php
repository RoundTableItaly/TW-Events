<?php

namespace App\Services;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public function __construct(
        private SocialYearService $socialYearService
    ) {
    }

    /**
     * Get all statistics for the current social year
     */
    public function getAllStatistics(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        
        $currentSocialYear = $this->socialYearService->getCurrentSocialYearDates();
        $previousSocialYear = $this->socialYearService->getPreviousSocialYearDates();
        
        $currentSocialYearStart = $currentSocialYear['start'];
        $currentSocialYearEnd = $currentSocialYear['end'];
        $previousSocialYearStart = $previousSocialYear['start'];
        $previousSocialYearEnd = $previousSocialYear['end'];

        // Basic KPIs
        $totalEvents = Activity::count();
        $futureEvents = Activity::active()
            ->where('start_date', '>', $now)
            ->count();
        $pastEvents = Activity::active()
            ->where('start_date', '<=', $now)
            ->count();
        $canceledEvents = Activity::whereNotNull('deleted_at')->count();
        $currentMonthEvents = Activity::where('start_date', '>=', $startOfMonth)
            ->where('start_date', '<=', $now)
            ->count();

        // Social year events
        $currentSocialYearEvents = Activity::active()
            ->inSocialYear($currentSocialYearStart, $currentSocialYearEnd)
            ->count();
        $previousSocialYearEvents = Activity::active()
            ->inSocialYear($previousSocialYearStart, $previousSocialYearEnd)
            ->count();

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
            'top_tables' => $this->getTopTables($currentSocialYearStart, $currentSocialYearEnd),
            'events_by_zone' => $this->getEventsByZone($currentSocialYearStart, $currentSocialYearEnd),
            'monthly_distribution' => $this->getMonthlyDistribution($currentSocialYearStart, $currentSocialYearEnd),
            'event_types' => $this->getEventTypes($currentSocialYearStart, $currentSocialYearEnd),
            'multi_day_events' => $this->getMultiDayVsSingleDay($currentSocialYearStart, $currentSocialYearEnd)['multi_day'],
            'single_day_events' => $this->getMultiDayVsSingleDay($currentSocialYearStart, $currentSocialYearEnd)['single_day'],
            'multi_day_percentage' => $this->getMultiDayVsSingleDay($currentSocialYearStart, $currentSocialYearEnd)['multi_day_percentage'],
            'single_day_percentage' => $this->getMultiDayVsSingleDay($currentSocialYearStart, $currentSocialYearEnd)['single_day_percentage'],
            'day_of_week_distribution' => $this->getDayOfWeekDistribution($currentSocialYearStart, $currentSocialYearEnd),
            'days_from_creation' => $this->getDaysFromCreation($currentSocialYearStart, $currentSocialYearEnd),
            'best_organized_tables' => $this->getBestOrganizedTables($currentSocialYearStart, $currentSocialYearEnd),
            'tables_without_activities' => $this->getTablesWithoutActivities(),
            'tables_without_activities_by_zone' => $this->getTablesWithoutActivitiesByZone(),
        ];
    }

    /**
     * Get top tables by event count
     */
    private function getTopTables(Carbon $start, Carbon $end): Collection
    {
        return Activity::select('api_endpoints.description as table_name', DB::raw('count(*) as event_count'))
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->active()
            ->where('api_endpoints.type', 'Tavola')
            ->inSocialYear($start, $end)
            ->groupBy('api_endpoints.id', 'api_endpoints.description')
            ->orderBy('event_count', 'desc')
            ->get();
    }

    /**
     * Get events grouped by zone
     */
    private function getEventsByZone(Carbon $start, Carbon $end): Collection
    {
        return Activity::select('api_endpoints.area as zone', DB::raw('count(*) as event_count'))
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->active()
            ->whereIn('api_endpoints.type', ['Tavola', 'Zona'])
            ->inSocialYear($start, $end)
            ->groupBy('api_endpoints.area')
            ->orderBy('event_count', 'desc')
            ->get();
    }

    /**
     * Get monthly distribution of events
     */
    private function getMonthlyDistribution(Carbon $start, Carbon $end): Collection
    {
        return Activity::select(
                DB::raw('DATE_FORMAT(start_date, "%Y-%m") as month'),
                DB::raw('count(*) as event_count')
            )
            ->active()
            ->inSocialYear($start, $end)
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Get event types distribution
     */
    private function getEventTypes(Carbon $start, Carbon $end): Collection
    {
        return Activity::select('rt_type', DB::raw('count(*) as count'))
            ->active()
            ->inSocialYear($start, $end)
            ->groupBy('rt_type')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Get multi-day vs single-day events statistics
     */
    private function getMultiDayVsSingleDay(Carbon $start, Carbon $end): array
    {
        $multiDayEvents = Activity::active()
            ->withValidDates()
            ->inSocialYear($start, $end)
            ->multiDay()
            ->count();

        $singleDayEvents = Activity::active()
            ->withValidDates()
            ->inSocialYear($start, $end)
            ->singleDay()
            ->count();

        $totalEventsWithDates = $multiDayEvents + $singleDayEvents;
        $multiDayPercentage = $totalEventsWithDates > 0 
            ? round(($multiDayEvents / $totalEventsWithDates) * 100, 1) 
            : 0;
        $singleDayPercentage = $totalEventsWithDates > 0 
            ? round(($singleDayEvents / $totalEventsWithDates) * 100, 1) 
            : 0;

        return [
            'multi_day' => $multiDayEvents,
            'single_day' => $singleDayEvents,
            'multi_day_percentage' => $multiDayPercentage,
            'single_day_percentage' => $singleDayPercentage,
        ];
    }

    /**
     * Get day of week distribution for single-day events
     */
    private function getDayOfWeekDistribution(Carbon $start, Carbon $end): Collection
    {
        return Activity::select(
                DB::raw('DAYOFWEEK(start_date) as day_of_week'),
                DB::raw('DAYNAME(start_date) as day_name'),
                DB::raw('count(*) as event_count')
            )
            ->active()
            ->withValidDates()
            ->inSocialYear($start, $end)
            ->singleDay()
            ->groupBy('day_of_week', 'day_name')
            ->orderBy('event_count', 'desc')
            ->get();
    }

    /**
     * Get days from creation to event start
     */
    private function getDaysFromCreation(Carbon $start, Carbon $end): Collection
    {
        return Activity::with(['apiEndpoint:id,description'])
            ->select('activities.*', DB::raw('DATEDIFF(start_date, created_at) as days_diff'))
            ->active()
            ->whereNotNull('start_date')
            ->whereNotNull('created_at')
            ->inSocialYear($start, $end)
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
    }

    /**
     * Get best organized tables (highest average days from publication to event)
     */
    private function getBestOrganizedTables(Carbon $start, Carbon $end): Collection
    {
        return Activity::select(
                'api_endpoints.description as table_name',
                DB::raw('AVG(DATEDIFF(activities.start_date, activities.created_at)) as avg_days'),
                DB::raw('COUNT(*) as event_count')
            )
            ->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
            ->active()
            ->whereNotNull('activities.start_date')
            ->whereNotNull('activities.created_at')
            ->inSocialYear($start, $end)
            ->whereRaw('activities.start_date >= activities.created_at')
            ->where('api_endpoints.type', 'Tavola')
            ->groupBy('api_endpoints.id', 'api_endpoints.description')
            ->havingRaw('COUNT(*) >= 5')
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
    }

    /**
     * Get tables without activities (tables with few or no events)
     */
    private function getTablesWithoutActivities(): Collection
    {
        return DB::table('api_endpoints')
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
            ->where('api_endpoints.type', 'Tavola')
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
    }

    /**
     * Get tables without activities grouped by zone
     */
    private function getTablesWithoutActivitiesByZone(): Collection
    {
        $tables = $this->getTablesWithoutActivities();
        
        return $tables
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
    }

}

