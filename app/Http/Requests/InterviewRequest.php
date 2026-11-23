<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class InterviewRequest extends ApiRequest
{
    use HasListQueryRules;
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'index' => [
                'application_id' => 'nullable|integer|exists:applications,id', 'job_position_id' => 'nullable|integer|exists:job_positions,id',
                'interviewer_id' => 'nullable|integer|exists:users,id', 'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled'])],
                'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d', 'upcoming' => 'nullable|boolean',
            ] + $this->listQueryRules(['start_at', 'created_at']),
            'calendar' => ['start_at' => 'required|date', 'end_at' => 'required|date|after:start_at'],
            'complete' => ['rating' => 'required|integer|min:1|max:5', 'recommendation' => ['required', Rule::in(['recommended', 'not_recommended', 'hold'])], 'feedback' => 'required|string|min:1|max:10000'],
            'cancel' => ['reason' => 'nullable|string|max:2000'],
            default => [
                'application_id' => [$this->route('interview') ? 'prohibited' : 'required', 'exists:applications,id'],
                'interviewer_id' => 'required|exists:users,id', 'start_at' => 'required|date', 'end_at' => 'required|date|after:start_at',
                'mode' => ['required', Rule::in(['online', 'onsite'])],
                'meeting_link' => [Rule::requiredIf($this->input('mode') === 'online'), 'nullable', 'url:http,https', 'max:500'],
                'location' => [Rule::requiredIf($this->input('mode') === 'onsite'), 'nullable', 'string', 'max:255'],
                'notes' => 'nullable|string|max:10000',
            ],
        };
    }
}
