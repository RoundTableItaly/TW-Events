<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Indicates if the model's ID is auto-incrementing.
     * The ID comes from the external API.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'level_id',
        'name',
        'type',
        'description',
        'start_date',
        'end_date',
        'rt_type',
        'rt_visibility',
        'location',
        'cover_picture',
        'canceled',
        'latitude',
        'longitude',
        'api_endpoint_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'canceled' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the API endpoint that this activity belongs to.
     */
    public function apiEndpoint(): BelongsTo
    {
        return $this->belongsTo(ApiEndpoint::class);
    }

    /**
     * Scope a query to only include active (non-deleted) activities.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('activities.deleted_at');
    }

    /**
     * Scope a query to only include activities within a social year date range.
     */
    public function scopeInSocialYear(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('activities.start_date', '>=', $start)
                     ->where('activities.start_date', '<=', $end);
    }

    /**
     * Scope a query to filter activities by API endpoint types.
     * Requires a join with api_endpoints table.
     */
    public function scopeFromTableTypes(Builder $query, array $types): Builder
    {
        return $query->join('api_endpoints', 'activities.api_endpoint_id', '=', 'api_endpoints.id')
                     ->whereIn('api_endpoints.type', $types);
    }

    /**
     * Scope a query to only include activities with valid start and end dates.
     */
    public function scopeWithValidDates(Builder $query): Builder
    {
        return $query->whereNotNull('activities.start_date')
                     ->whereNotNull('activities.end_date');
    }

    /**
     * Scope a query to only include single-day events.
     */
    public function scopeSingleDay(Builder $query): Builder
    {
        return $query->whereRaw('DATE(activities.start_date) = DATE(activities.end_date)');
    }

    /**
     * Scope a query to only include multi-day events.
     */
    public function scopeMultiDay(Builder $query): Builder
    {
        return $query->whereRaw('DATE(activities.start_date) != DATE(activities.end_date)');
    }
} 