<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\StatisticsService;
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
     * Show statistics page with overview data
     */
    public function statisticsPage(StatisticsService $statisticsService)
    {
        $cacheKey = 'statistics_page_data';
        $cacheTtl = now()->addMinutes(30);

        $statistics = Cache::remember($cacheKey, $cacheTtl, function () use ($statisticsService) {
            return $statisticsService->getAllStatistics();
        });

        return view('statistics', compact('statistics'));
    }
}