<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ReportRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d',
            'job_position_id' => 'nullable|exists:job_positions,id', 'department' => 'nullable|string|max:150',
            'group_by' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
