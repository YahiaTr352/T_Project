<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerPersonId = $request->attributes->get('viewer_person_id');
        $sender = $this->sender;
        $senderName = $sender?->full_name ?: trim("{$sender?->first_name} {$sender?->last_name}");

        // رسائل الإدارة الرسمية
        $isSystem = $this->sender_user_id !== null || $this->type === 'system';
        $systemName = $this->senderUser?->name;

        return [
            'id'               => $this->id,
            'conversation_id'  => $this->conversation_id,
            'type'             => $this->type ?? 'text',
            'message'          => $this->message,
            'attachment_url'   => $this->attachment_url,
            'metadata'         => $this->metadata,
            'is_me'            => $viewerPersonId ? ((int) $this->sender_person_id === (int) $viewerPersonId) : false,
            'is_system'        => $isSystem,
            'is_edited'        => (bool) $this->is_edited,
            'edited_at'        => $this->edited_at?->toIso8601String(),
            'reply_to'         => ($this->reply_to_message_id && $this->replyTo && ! $this->replyTo->is_hidden) ? [
                'id'      => (int) $this->reply_to_message_id,
                'preview' => $this->replyTo->message
                    ? \Illuminate\Support\Str::limit((string) $this->replyTo->message, 80)
                    : ($this->replyTo->type === 'video' ? '🎥 مقطع فيديو' : ($this->replyTo->type === 'audio' ? '🎤 رسالة صوتية' : '📷 صورة')),
                'sender'  => $this->replyTo->sender?->full_name,
            ] : null,
            'reactions'        => $this->reaction_summary ?? [],
            'sender'           => [
                'id'         => $sender?->id,
                'name'       => $isSystem
                    ? ($systemName ?: 'إدارة التطبيق')
                    : ($senderName ?: 'عضو في التحدي'),
                'first_name' => $sender?->first_name,
                'avatar_url' => $sender?->avatar,
                'gender'     => $sender?->gender,
                'is_admin'   => $isSystem,
            ],
            'created_at'       => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'time_formatted'   => $this->created_at ? $this->created_at->format('H:i') : null,
        ];
    }
}
