<?php

namespace App\Models;

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
} 