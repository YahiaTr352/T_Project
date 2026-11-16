<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkingTrekWaypoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'walking_trek_id',
        'sort_order',
        'city_name',
        'location_title',
        'location_description',
        'latitude',
        'longitude',
        'distance_from_prev_km',
        'cumulative_km',
        'icon_type',
        'metadata',
        'route_polyline',
        'route_metadata',
    ];

    protected $casts = [
        'sort_order'            => 'integer',
        'latitude'              => 'float',
        'longitude'             => 'float',
        'distance_from_prev_km' => 'float',
        'cumulative_km'         => 'float',
        'metadata'              => 'array',
        'route_metadata'        => 'array',
    ];

    public function trek(): BelongsTo
    {
        return $this->belongsTo(WalkingTrek::class, 'walking_trek_id');
    }
}
