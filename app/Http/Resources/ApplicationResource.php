<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_code' => $this->application_code,
            'candidate_id' => $this->candidate_id,
            'candidate' => $this->whenLoaded('candidate', fn () => new CandidateResource($this->candidate)),
            'job_position_id' => $this->job_position_id,
            'job_position' => $this->whenLoaded('jobPosition', fn () => new JobPositionResource($this->jobPosition)),
            'source' => $this->source,
            'stage' => $this->stage,
            'applied_at' => $this->applied_at,
            'stage_updated_at' => $this->stage_updated_at,
            'hired_at' => $this->hired_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'histories' => $this->whenLoaded('histories', fn () => ApplicationStageHistoryResource::collection($this->histories)),
            'interviews' => $this->whenLoaded('interviews', fn () => InterviewResource::collection($this->interviews)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
