<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function __construct($resource, protected array $extraData = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'title'                => $this->title,
            'type'                 => $this->type ?? 'group',
            'is_active'            => (bool) $this->is_active,
            'unread_count'         => (int) ($this->extraData['unread_count'] ?? 0),
            'is_muted'             => (bool) ($this->extraData['is_muted'] ?? false),
            'can_send'             => (bool) ($this->extraData['can_send'] ?? true),
            'send_blocked_reason'  => $this->extraData['send_blocked_reason'] ?? null,
            'send_blocked_message' => $this->extraData['send_blocked_message'] ?? null,
            'retry_after'          => isset($this->extraData['retry_after']) ? (int) $this->extraData['retry_after'] : null,
            'is_locked'            => (bool) $this->is_locked,
            'lock_reason'          => $this->lock_reason,
            'slow_mode_seconds'    => (int) ($this->slow_mode_seconds ?? 0),
            'allow_attachments'    => (bool) ($this->allow_attachments ?? true),
            'pinned_message_id'    => $this->last_pinned_message_id ? (int) $this->last_pinned_message_id : null,
            'pinned_message'       => $this->whenLoaded('pinnedMessage', fn () => $this->pinnedMessage ? [
                'id'      => (int) $this->pinnedMessage->id,
                'message' => $this->pinnedMessage->message,
                'type'    => $this->pinnedMessage->type,
                'sender'  => $this->pinnedMessage->sender?->full_name,
            ] : null),
            'last_message_at'      => $this->last_message_at?->toIso8601String(),
            'last_message_preview' => $this->last_message_preview,
            'messages_count'       => (int) $this->messages_count,
        ];
    }
}
