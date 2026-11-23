<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'department' => $this->department,
            'employment_type' => $this->employment_type,
            'location' => $this->location,
            'openings' => $this->openings,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'status' => $this->status,
            'applicants_count' => $this->when(
                array_key_exists('applicants_count', $this->resource->getAttributes()),
                fn () => (int) $this->resource->getAttribute('applicants_count'),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
