<?php

namespace App\Models;

use App\Contracts\Chatable;
use App\Traits\HasChat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WalkingGroup extends Model implements Chatable
{
    use HasFactory, SoftDeletes, HasChat;

    protected $fillable = [
        'name',
        'description',
        'cover_image',
        'target_steps',
        'start_date',
        'end_date',
        'is_public',
        'status',
        'archived_at',
        'archived_reason',
        'completion_notified_at',
        'invite_code',
        'created_by_person_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_public'              => 'boolean',
        'target_steps'           => 'integer',
        'start_date'             => 'date',
        'end_date'               => 'date',
        'archived_at'            => 'datetime',
        'completion_notified_at' => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Auto-generate a unique invite code on creation for all groups
        // (even public ones may share it for direct deep-links)
        static::creating(function (self $group) {
            if (empty($group->invite_code)) {
                do {
                    $code = strtoupper(Str::random(8));
                } while (self::where('invite_code', $code)->exists());

                $group->invite_code = $code;
            }
        });
    }

    public function getInviteCodeAttribute(?string $value): string
    {
        if (empty($value)) {
            do {
                $code = strtoupper(Str::random(8));
            } while (self::where('invite_code', $code)->exists());

            if ($this->exists) {
                $this->attributes['invite_code'] = $code;
                $this->saveQuietly();
            }

            return $code;
        }

        return $value;
    }

    // ── Relationships ──────────────────────────────────────────────

    public function creatorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WalkingGroupMember::class);
    }

    public function dailyScores(): HasMany
    {
        return $this->hasMany(WalkingGroupDailyScore::class, 'walking_group_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'walking_group_members', 'walking_group_id', 'person_id')
            ->withPivot('role', 'status', 'is_anonymous', 'share_details', 'notifications_enabled', 'joined_at')
            ->withTimestamps()
            ->wherePivot('status', 'accepted');
    }

    // ── Scopes ────────────────────────────────────────────────────

    /**
     * Returns groups that a given person is allowed to see:
     *  - Public groups are always visible.
     *  - Private groups are visible only if the person is an accepted member.
     */
    public function scopeVisibleToPerson(Builder $query, int $personId): Builder
    {
        return $query->where(function (Builder $q) use ($personId) {
            $q->where('is_public', true)
              ->orWhereHas('memberships', function (Builder $m) use ($personId) {
                  $m->where('person_id', $personId)->where('status', 'accepted');
              });
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeActiveOnly(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'upcoming']);
    }

    /**
     * Re-usable scope for genuinely active / running challenges
     * (active status AND end_date is in the future or null).
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Scope for challenges that have completed (either marked completed OR end_date has elapsed).
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'completed')
              ->orWhere(function (Builder $sq) {
                  $sq->where('status', 'active')
                     ->whereNotNull('end_date')
                     ->whereDate('end_date', '<', now()->toDateString());
              });
        });
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Filter by status query string parameter ('active', 'completed', 'archived', 'all').
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'active'    => $query->running(),
            'completed' => $query->completed(),
            'archived'  => $query->archived(),
            'all'       => $query,
            default     => $query->running(),
        };
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Check if challenge has passed its end date. */
    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->lt(now()->startOfDay());
    }

    /** Check whether a person is an accepted member of this group. */
    public function hasMember(int $personId): bool
    {
        return $this->memberships()
            ->where('person_id', $personId)
            ->where('status', 'accepted')
            ->exists();
    }

    /** Check whether a person is the admin / creator of this group. */
    public function isAdmin(int $personId): bool
    {
        if ($this->created_by_person_id && (int) $this->created_by_person_id === $personId) {
            return true;
        }

        return $this->memberships()
            ->where('person_id', $personId)
            ->where('role', 'admin')
            ->exists();
    }

    // ── Chatable Implementation ───────────────────────────────────

    /**
     * Determines whether a given person has permission to view the chat and read messages.
     * Public groups are open to all; private groups require accepted membership.
     */
    public function canAccessChat(int $personId): bool
    {
        if ($this->is_public) {
            return true;
        }

        return $this->hasMember($personId);
    }

    /**
     * Determines whether a given person has permission to send messages.
     * Public groups allow any active user; private groups require accepted membership.
     */
    public function canSendMessage(int $personId): bool
    {
        if ($this->status === 'archived') {
            return false;
        }

        if ($this->is_public) {
            return true;
        }

        return $this->hasMember($personId);
    }

    /**
     * Returns recipient person IDs who should receive push notifications for new chat messages.
     * الشات مكتوم افتراضياً (Muted by default). الإشعار خارج التطبيق (Push) يذهب فقط للأعضاء
     * المقبولين الذين ألغوا كتم المحادثة صراحة (is_muted = false) وفعّلوا إشعارات المجموعة.
     *
     * @return Collection<int, int>
     */
    public function getChatNotificationRecipients(int $senderPersonId): Collection
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return collect();
        }

        // جلب المشاركين الذين ألغوا الكتم صراحة ومستبعد منهم المُرسِل
        $unmutedPersonIds = $conversation->participants()
            ->where('is_muted', false)
            ->where('person_id', '!=', $senderPersonId)
            ->pluck('person_id');

        if ($unmutedPersonIds->isEmpty()) {
            return collect();
        }

        // التأكد من أنهم أعضاء مقبولون وإشعارات المجموعة مفعلة لديهم
        return $this->memberships()
            ->where('status', 'accepted')
            ->where('notifications_enabled', true)
            ->whereIn('person_id', $unmutedPersonIds)
            ->pluck('person_id')
            ->values();
    }

    /**
     * Display title for chat notifications and headers.
     */
    public function getChatTitle(): string
    {
        return "تحدي: {$this->name}";
    }

    /**
     * الحمولة المرسلة مع إشعار push لتوجيه التطبيق إلى محادثة التحدي.
     */
    public function getChatNotificationPayload(): array
    {
        return [
            'type'       => 'walking_group_chat',
            'action_url' => "/(tabs)/walking?tab=groups&groupId={$this->id}&openChat=1",
            'group_id'   => (int) $this->id,
        ];
    }
}
