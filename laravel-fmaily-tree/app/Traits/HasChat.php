<?php

namespace App\Traits;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

/**
 * Trait providing chat/conversation relationships and helpers to any Model.
 */
trait HasChat
{
    /**
     * Boot the trait to synchronize chat lifecycle with the host model.
     */
    public static function bootHasChat(): void
    {
        static::deleting(function (Model $model) {
            $isForce = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

            if ($isForce) {
                $conversation = $model->conversation()->withTrashed()->first();
                if ($conversation) {
                    // Wipe attachment files from disk
                    foreach ($conversation->messages()->withTrashed()->whereNotNull('attachment_url')->get() as $msg) {
                        try {
                            $path = parse_url($msg->attachment_url, PHP_URL_PATH);
                            if ($path && str_contains($path, 'chat_attachments/')) {
                                Storage::disk('public')->delete('chat_attachments/' . basename($path));
                            }
                        } catch (\Throwable $e) {
                            // ignore cleanup exceptions during cascade
                        }
                    }
                    $conversation->messages()->withTrashed()->forceDelete();
                    $conversation->participants()->delete();
                    $conversation->forceDelete();
                }
            } else {
                $conversation = $model->conversation()->first();
                if ($conversation) {
                    $conversation->update(['is_active' => false]);
                    $conversation->delete();
                }
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $conversation = $model->conversation()->onlyTrashed()->first();
                if ($conversation) {
                    $conversation->restore();
                    $conversation->update(['is_active' => true]);
                }
            });
        }
    }
    /**
     * Polymorphic relation to the single main conversation for this entity.
     */
    public function conversation(): MorphOne
    {
        return $this->morphOne(Conversation::class, 'conversable');
    }

    /**
     * Retrieve the existing conversation or auto-provision one if it does not yet exist.
     */
    public function getOrCreateConversation(?string $type = 'group'): Conversation
    {
        return $this->conversation()->firstOrCreate(
            [],
            [
                'title'     => method_exists($this, 'getChatTitle') ? $this->getChatTitle() : ($this->name ?? $this->title ?? 'محادثة'),
                'type'      => $type,
                'is_active' => true,
            ]
        );
    }
}
