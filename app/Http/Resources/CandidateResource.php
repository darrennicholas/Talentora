<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'education' => $this->education,
            'years_experience' => $this->years_experience,
            'skills' => $this->skills,
            'experience_summary' => $this->experience_summary,
            'linkedin_url' => $this->linkedin_url,
            'portfolio_url' => $this->portfolio_url,
            'has_cv' => $this->has_cv,
            'cv_original_name' => $this->cv_original_name,
            'applications_count' => $this->whenCounted('applications'),
            'applications' => $this->whenLoaded('applications', fn () => ApplicationResource::collection($this->applications)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
