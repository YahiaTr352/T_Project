<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\NotificationDismissal;
use Illuminate\Database\Eloquent\Builder;

class MobileNotificationService
{
    /**
     * بناء استعلام الإشعارات الصالحة للعرض في صندوق الوارد
     * مع استبعاد إشعارات الشات نهائياً لضمان عدم ظهورها داخل التطبيق.
     */
    public function visibleInboxQuery(): Builder
    {
        return Notification::query()
            ->where(function ($q) {
                $q->whereIn('status', ['sending', 'partial', 'sent', 'published'])
                    ->orWhereNull('status')
                    ->orWhereNotNull('sent_at');
            })
            ->where(function ($q) {
                // منع إشعارات الشات نهائياً من الظهور في صندوق الوارد الداخلي للتطبيق
                $q->whereNull('targeting_filters')
                    ->orWhere(function ($sub) {
                        $sub->where('targeting_filters->data->type', 'not like', '%chat%')
                            ->orWhereNull('targeting_filters->data->type');
                    });
            })
            ->where(function (Builder $query) {
                // Private notices must not become broadcasts when their recipient is deleted.
                $query->whereNull('targeting_filters->data->type')
                    ->orWhereNotIn('targeting_filters->data->type', [Notification::INVITATION_RESPONSE_TYPE, Notification::CREATIVE_DECISION_TYPE])
                    ->orWhereHas('recipients');
            });
    }

    /**
     * استعلام الإشعارات الموجهة للعضو وغير المحذوفة من قبله
     */
    public function getInboxQueryForPerson(?int $personId): Builder
    {
        $query = $this->visibleInboxQuery();

        if ($personId) {
            $query->where(function ($q) use ($personId) {
                $q->whereHas('recipients', function ($r) use ($personId) {
                    $r->where('person_id', $personId);
                })->orWhereDoesntHave('recipients');
            })->whereDoesntHave('dismissals', function ($d) use ($personId) {
                $d->where('person_id', $personId);
            });
        } else {
            $query->whereDoesntHave('recipients');
        }

        return $query;
    }

    /**
     * حساب عدد الإشعارات غير المقروءة للعضو مع استبعاد المحذوفة
     */
    public function getUnreadCountForPerson(int $personId): int
    {
        return $this->visibleInboxQuery()
            ->whereHas('recipients', function ($r) use ($personId) {
                $r->where('person_id', $personId)->whereNull('read_at');
            })
            ->whereDoesntHave('dismissals', function ($d) use ($personId) {
                $d->where('person_id', $personId);
            })
            ->count();
    }

    /**
     * حذف/إخفاء إشعار فردي لعضو معين
     */
    public function dismiss(int|string $notificationId, int $personId): bool
    {
        NotificationDismissal::firstOrCreate([
            'notification_id' => (int) $notificationId,
            'person_id' => $personId,
        ]);

        return true;
    }

    /**
     * حذف/إخفاء مجموعة إشعارات دفعة واحدة
     *
     * @param array<int|string> $notificationIds
     */
    public function dismissMultiple(array $notificationIds, int $personId): int
    {
        if (empty($notificationIds)) {
            return 0;
        }

        $now = now();
        $records = [];
        foreach ($notificationIds as $id) {
            $records[] = [
                'notification_id' => (int) $id,
                'person_id' => $personId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        NotificationDismissal::upsert(
            $records,
            ['notification_id', 'person_id'],
            ['updated_at']
        );

        return count($records);
    }

    /**
     * مسح كافة الإشعارات الظاهرة حالياً في رصيد الوارد للعضو
     */
    public function clearAll(int $personId): int
    {
        $visibleIds = $this->getInboxQueryForPerson($personId)->pluck('id');

        if ($visibleIds->isEmpty()) {
            return 0;
        }

        return $this->dismissMultiple($visibleIds->all(), $personId);
    }
}
