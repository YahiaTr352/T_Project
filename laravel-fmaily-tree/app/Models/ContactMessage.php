<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContactMessage extends Model
{
    use HasFactory;

    protected $table = 'contact_messages';

    protected $fillable = [
        'person_id',
        'user_id',
        'name',
        'phone',
        'email',
        'type',
        'subject',
        'message',
        'media_type',
        'media_path',
        'media_mime',
        'media_size',
        'source',
        'ip_address',
        'user_agent',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'media_size' => 'integer',
    ];

    protected $appends = [
        'full_name',
        'sender_display_name',
        'media_url',
        'type_label',
        'status_label',
        'media_type_label',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    // ── Accessors & Helpers ────────────────────────────────────────

    public function getMediaUrlAttribute(): ?string
    {
        if (empty($this->media_path)) {
            return null;
        }

        if (str_starts_with($this->media_path, 'http://') || str_starts_with($this->media_path, 'https://')) {
            return $this->media_path;
        }

        return Storage::disk('public')->url($this->media_path);
    }

    /**
     * Accessor: الاسم الكامل للمرسل (full_name)
     * يعتمد على full_name للشخص المرتبط إذا كان الحساب موثقاً في شجرة العائلة،
     * وإلا يعيد الاسم المُدخل للزائر.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->person && !empty($this->person->full_name)) {
            return $this->person->full_name;
        }

        return !empty($this->name) ? $this->name : 'زائر (بدون اسم)';
    }

    /**
     * Accessor: اسم العرض للمرسل
     * يعتمد مباشرة على full_name لضمان عرض الاسم الكامل دائماً.
     */
    public function getSenderDisplayNameAttribute(): string
    {
        return $this->full_name;
    }

    public function getSenderDisplayPhoneAttribute(): ?string
    {
        return $this->phone ?: ($this->person?->phone ?? null);
    }

    public static function typeLabels(): array
    {
        return [
            'problem' => 'مشكلة أو بلاغ',
            'inquiry' => 'استفسار',
            'suggestion' => 'اقتراح تطوير',
            'complaint' => 'شكوى',
            'other' => 'أخرى',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? 'عام';
    }

    public static function statusLabels(): array
    {
        return [
            'pending' => 'قيد الانتظار',
            'in_progress' => 'قيد المعالجة',
            'resolved' => 'تم الحل / الإنجاز',
            'archived' => 'مؤرشف',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getMediaTypeLabelAttribute(): string
    {
        return match ($this->media_type) {
            'image' => 'صورة',
            'video' => 'فيديو',
            'voice' => 'تسجيل صوتي',
            default => 'بدون مرفق',
        };
    }
}
