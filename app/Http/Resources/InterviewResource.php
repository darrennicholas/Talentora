<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'application' => $this->whenLoaded('application', fn () => new ApplicationResource($this->application)),
            'interviewer_id' => $this->interviewer_id,
            'interviewer' => $this->whenLoaded('interviewer', fn () => new UserResource($this->interviewer)),
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'mode' => $this->mode,
            'meeting_link' => $this->meeting_link,
            'location' => $this->location,
            'status' => $this->status,
            'rating' => $this->rating,
            'recommendation' => $this->recommendation,
            'feedback' => $this->feedback,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
