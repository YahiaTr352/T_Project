<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Check if read status is loaded or determined from recipient pivot
        $recipient = $this->relationLoaded('recipients') ? $this->recipients->first() : null;
        $isRead = isset($this->read_at) || $recipient?->read_at !== null;
        $respondingPersonId = $request->attributes->get('invitation_response_person_id');
        $isReceivedInvitation = $respondingPersonId !== null
            && $recipient !== null
            && (int) $recipient->person_id === $respondingPersonId
            && $this->person_id !== null
            && (int) $this->person_id !== $respondingPersonId
            && $this->resource->acceptsInvitationResponses();

        $filters = is_array($this->targeting_filters) ? $this->targeting_filters : [];
        $actionUrl = $this->action_url ?? ($filters['action_url'] ?? null);
        $data = $this->data ?? ($filters['data'] ?? null);

        $viewerPerson = $request->attributes->get('viewer_person');
        if (! $viewerPerson && $request->attributes->has('identity_person')) {
            $viewerPerson = $request->attributes->get('identity_person');
        }

        $messageService = app(\App\Services\Invitations\InvitationMessageService::class);
        $templateVars = is_array($filters['template_vars'] ?? null) ? $filters['template_vars'] : [];
        $sender = $this->person_id !== null ? $this->person : null;
        $resolvedTitle = $messageService->replacePlaceholders($this->title ?? 'تنبيه عائلي', $viewerPerson, $sender, $templateVars);
        $resolvedBody = $messageService->replacePlaceholders($this->body ?? '', $viewerPerson, $sender, $templateVars);

        return [
            'id' => $this->id,
            'title' => $resolvedTitle,
            'body' => $resolvedBody,
            'media_type' => $this->media_type,
            'media_url' => $this->media_path ? asset('storage/' . $this->media_path) : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'time_ago' => $this->created_at ? $this->created_at->diffForHumans() : '',
            'read' => $isRead,
            'action_url' => $actionUrl,
            'data' => $data,
            'invitation' => $isReceivedInvitation ? [
                'id' => $this->id,
                'response' => $recipient->invitation_response,
            ] : null,
        ];
    }
}
