<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Support\Facades\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Illuminate\Support\Facades\Cache;

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
} 