<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class InvitationResponseService
{
    public const RESPONSE_ALREADY_RECORDED = 'INVITATION_RESPONSE_ALREADY_RECORDED';

    public function save(Notification $invitation, int $personId, string $response): void
    {
        DB::transaction(function () use ($invitation, $personId, $response) {
            $received = Notification::receivedInvitations($personId)->lockForUpdate()->find($invitation->id);
            $recipient = $received?->recipients()->where('person_id', $personId)->lockForUpdate()->first();

            if (! $recipient) {
                throw new InvitationException('هذه الدعوة غير متاحة للرد.', 'INVITATION_NOT_RESPONDABLE', 403);
            }

            if ($recipient->invitation_response === $response) {
                return;
            }

            if ($recipient->invitation_response !== null) {
                throw new InvitationException('سبق أن سجّلت ردك على هذه الدعوة. الرد نهائي.', self::RESPONSE_ALREADY_RECORDED, 409);
            }

            $recipient->invitation_response = $response;
            $recipient->save();

            $this->notifySender($received, $recipient->person?->full_name ?: 'أحد المدعوين', $response);
        });
    }

    private function notifySender(Notification $invitation, string $memberName, string $response): void
    {
        $invitationTitle = trim((string) $invitation->title) ?: 'دعوة عائلية';
        $accepted = $response === 'accepted';
        $notification = Notification::create([
            'title' => $accepted ? 'قبول الدعوة' : 'رفض الدعوة',
            'body' => $accepted
                ? "تأكيد الحضور من {$memberName} لدعوتك «{$invitationTitle}»."
                : "اعتذار {$memberName} عن حضور دعوتك «{$invitationTitle}».",
            'status' => 'sent',
            'sent_at' => now(),
            'targeting_filters' => [
                'data' => ['type' => Notification::INVITATION_RESPONSE_TYPE],
            ],
        ]);

        $notification->recipients()->create(['person_id' => $invitation->person_id]);
    }
}
