<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Select2 person search dropdowns with rich UI preview data.
 */
class PersonSelectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = $this->full_name ?? trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return [
            'id'                      => $this->id,
            'text'                    => $fullName,
            'full_name'               => $fullName,
            'full_name_with_relations'=> $this->full_name_with_relations,
            'first_name'              => $this->first_name ?? '',
            'last_name'               => $this->last_name ?? '',
            'gender'                  => $this->gender ?? 'male',
            'gender_label'            => $this->gender === 'female' ? 'أنثى' : 'ذكر',
            'avatar'                  => $this->avatar,
            'photo_url'               => $this->avatar,
            'age'                     => $this->age,
            'birth_date'              => $this->birth_date ? $this->birth_date->format('Y-m-d') : null,
            'death_date'              => $this->death_date ? $this->death_date->format('Y-m-d') : null,
            'is_alive'                => is_null($this->death_date),
            'from_outside_the_family' => (bool) ($this->from_outside_the_family ?? false),
            'location'                => $this->location ? ($this->location->name ?? $this->location->display_name) : null,
            'occupation'              => $this->occupation ?? null,
        ];
    }
}


