<?php

namespace App\Services\AI\DTO;

class ChatMessage
{
    public function __construct(
        public readonly string $role,
        public readonly ?string $content = null,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
        public readonly ?string $name = null,
    ) {
    }

    public function toArray(): array
    {
        $data = ['role' => $this->role];
        if ($this->content !== null) {
            $data['content'] = mb_convert_encoding($this->content, 'UTF-8', 'UTF-8');
        } else {
            $data['content'] = null;
        }
        if ($this->toolCalls !== null) {
            $data['tool_calls'] = $this->toolCalls;
        }
        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        return $data;
    }
}
