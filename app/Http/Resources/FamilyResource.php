<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'family_number' => 'F-' . str_pad($this->id, 5, '0', STR_PAD_LEFT),

            'nid' => $this->national_id,

            'name' => $this->head_name,

            'camp' => optional($this->camp)->name,

            'gov' => $this->original_governorate,

            'phone' => $this->phone,

            'shelter' => $this->shelter_number,

            'total' => $this->members_count,

            'adults' => $this->adults_count,

            'children' => $this->children_count,

            'pwd' => $this->pwd_count,

            'fhh' => (bool) $this->is_female_headed,

            'fhhR' => $this->fhh_reason,

            'score' => $this->vulnerability_score,

            'level' => $this->vulnerability_level,

            'date' => optional($this->created_at)->format('Y-m-d'),

           'members' => $this->whenLoaded('members'),

        ];
    }
}
