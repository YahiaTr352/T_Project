<?php

namespace App\Http\Resources\Walking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkingRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'date'             => $this->date?->toDateString(),
            'steps'            => (int) $this->steps,
            'distance_km'      => (float) $this->distance_km,
            'duration_minutes' => (int) $this->duration_minutes,
            'calories'         => $this->calories,
            'source'           => $this->source,
            'notes'            => $this->notes,
            'person_id'        => $this->person_id,
            'user_id'          => $this->user_id,
        ];
    }
}
