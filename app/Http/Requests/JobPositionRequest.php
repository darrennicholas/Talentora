<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class JobPositionRequest extends ApiRequest
{
    use HasListQueryRules;
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) $this->merge(['code' => strtoupper(trim($this->input('code')))]);
    }

    public function rules(): array
    {
        $job = $this->route('job');
        if ($this->route()->getActionMethod() === 'index') {
            return [
                'department' => 'nullable|string|max:150',
                'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
                'status' => ['nullable', Rule::in(['draft', 'open', 'closed'])],
            ] + $this->listQueryRules(['created_at', 'title', 'code']);
        }
        if ($this->route()->getActionMethod() === 'updateStatus') {
            return ['status' => ['required', Rule::in(['draft', 'open', 'closed'])]];
        }

        $required = $job ? 'sometimes' : 'required';
        return [
            'code' => [$required, 'string', 'min:3', 'max:50', Rule::unique('job_positions', 'code')->ignore($job?->id)],
            'title' => "{$required}|string|min:3|max:200",
            'department' => "{$required}|string|min:2|max:150",
            'employment_type' => [$required, Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'location' => "{$required}|string|min:2|max:200",
            'openings' => "{$required}|integer|min:1",
            'description' => "{$required}|string|min:1",
            'requirements' => "{$required}|string|min:1",
            'status' => $job ? 'prohibited' : ['required', Rule::in(['draft', 'open', 'closed'])],
        ];
    }
}
