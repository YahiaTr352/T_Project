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
        $isRead = false;
        if (isset($this->read_at)) {
            $isRead = !is_null($this->read_at);
        } elseif ($this->relationLoaded('recipients') && $this->recipients->isNotEmpty()) {
            $recipient = $this->recipients->first();
            $isRead = ! is_null($recipient->read_at);
        }

        $filters = is_array($this->targeting_filters) ? $this->targeting_filters : [];
        $actionUrl = $this->action_url ?? ($filters['action_url'] ?? null);
        $data = $this->data ?? ($filters['data'] ?? null);

        $viewerPerson = $request->attributes->get('viewer_person');
        if (! $viewerPerson && $request->attributes->has('identity_person')) {
            $viewerPerson = $request->attributes->get('identity_person');
        }

        $messageService = app(\App\Services\Invitations\InvitationMessageService::class);
        $resolvedTitle = $messageService->replacePlaceholders($this->title ?? 'تنبيه عائلي', $viewerPerson);
        $resolvedBody = $messageService->replacePlaceholders($this->body ?? '', $viewerPerson);

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
        ];
    }
}
