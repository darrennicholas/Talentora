<?php

namespace App\Http\Requests;

class PipelineRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['job_position_id' => 'nullable|exists:job_positions,id', 'limit_per_stage' => 'nullable|integer|min:1|max:100', 'q' => 'nullable|string|max:100'];
    }
}
