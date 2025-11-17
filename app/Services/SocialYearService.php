<?php

namespace App\Services;

use Carbon\Carbon;

class SocialYearService
{
    /**
     * Calculate the start date of a social year
     * The social year starts on the first Saturday of June
     *
     * @param int|null $year The year to calculate for. If null, uses current year.
     * @return Carbon The first Saturday of June for the given year
     */
    public function getSocialYearStart(int $year = null): Carbon
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
     * Get the current social year start and end dates
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getCurrentSocialYearDates(): array
    {
        $now = Carbon::now();
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
        
        return [
            'start' => $currentSocialYearStart,
            'end' => $currentSocialYearEnd,
        ];
    }

    /**
     * Get the previous social year start and end dates
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getPreviousSocialYearDates(): array
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $socialYearStart = $this->getSocialYearStart($currentYear);
        
        // If we're before the social year start, the previous social year started two years ago
        if ($now->lt($socialYearStart)) {
            $previousSocialYearStart = $this->getSocialYearStart($currentYear - 2);
            $currentSocialYearStart = $this->getSocialYearStart($currentYear - 1);
            $previousSocialYearEnd = $currentSocialYearStart->copy()->subDay();
        } else {
            $previousSocialYearStart = $this->getSocialYearStart($currentYear - 1);
            $currentSocialYearStart = $socialYearStart;
            $previousSocialYearEnd = $currentSocialYearStart->copy()->subDay();
        }
        
        return [
            'start' => $previousSocialYearStart,
            'end' => $previousSocialYearEnd,
        ];
    }
}

