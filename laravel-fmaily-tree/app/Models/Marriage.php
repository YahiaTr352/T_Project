<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Marriage extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'husband_id',
        'wife_id',
        'married_at',
        'divorced_at',
        'is_divorced',
        'sort_order',
    ];
    protected $casts = [
        'married_at' => 'date',
        'divorced_at' => 'date',
        'is_divorced' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * الترتيب الموحد للزيجات:
     * 1. تاريخ الزواج (married_at) للأقدم أولاً في حال وجوده.
     * 2. في حال غياب تاريخ الزواج (أو تساويه): الترتيب اليدوي (sort_order) عبر السحب والإفلات.
     * 3. الفاصل الأخير: id.
     */
    public function scopeOrdered($query)
    {
        return $query
            ->orderByRaw('CASE WHEN married_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('married_at', 'asc')
            ->orderByRaw('CASE WHEN sort_order IS NULL OR sort_order = 0 THEN 1 ELSE 0 END ASC')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }

    public function husband()
    {
        return $this->belongsTo(Person::class, 'husband_id');
    }

    public function wife()
    {
        return $this->belongsTo(Person::class, 'wife_id');
    }

    /**
     * تحديد ما إذا كان الزواج مطلق
     */
    public function isDivorced(): bool
    {
        return $this->is_divorced || !is_null($this->divorced_at);
    }

    /**
     * تحديد حالة الزواج
     */
    public function getStatusAttribute(): string
    {
        if ($this->isDivorced()) {
            return 'divorced';
        } elseif (!is_null($this->married_at)) {
            return 'active';
        } else {
            return 'unknown';
        }
    }

    /**
     * الحصول على نص الحالة باللغة العربية
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'divorced' => 'منفصل',
            'active' => 'نشط',
            'unknown' => 'غير محدد',
            default => 'غير محدد'
        };
    }
}
