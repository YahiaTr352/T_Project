<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkingDailyReset extends Model
{
    protected $fillable = [
        'date',
        'person_id',
        'scope',
        'reset_by',
        'source',
        'reason',
        'affected_records',
    ];

    protected $casts = [
        'date'             => 'date',
        'affected_records' => 'integer',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function resetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by');
    }

    /**
     * آخر تصفير سارٍ لشخص في يوم معين (شخصي أو عام للكل).
     */
    public static function latestFor(string $date, ?int $personId = null): ?self
    {
        return static::where('date', $date)
            ->where(function ($q) use ($personId) {
                $q->where('scope', 'all');
                if ($personId) {
                    $q->orWhere(function ($qq) use ($personId) {
                        $qq->where('scope', 'person')->where('person_id', $personId);
                    });
                }
            })
            ->orderByDesc('id')
            ->first();
    }
}