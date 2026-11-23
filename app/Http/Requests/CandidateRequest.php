<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CandidateRequest extends ApiRequest
{
    use HasListQueryRules;
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) $this->merge(['email' => strtolower(trim($this->input('email')))]);
    }

    public function rules(): array
    {
        $candidate = $this->route('candidate');
        return match ($this->route()->getActionMethod()) {
            'index' => ['location' => 'nullable|string|max:200'] + $this->listQueryRules(['created_at', 'full_name']),
            'uploadCv' => ['cv' => 'required|file|mimes:pdf,doc,docx|max:5120'],
            default => [
                'full_name' => [$candidate ? 'sometimes' : 'required', 'string', 'min:2', 'max:150'],
                'email' => [$candidate ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('candidates', 'email')->ignore($candidate?->id)],
                'phone' => [$candidate ? 'sometimes' : 'required', 'string', 'min:6', 'max:30'],
                'location' => 'nullable|string|max:200',
                'education' => 'nullable|string|max:200',
                'years_experience' => 'nullable|integer|min:0|max:70',
                'skills' => 'nullable|string|max:5000',
                'experience_summary' => 'nullable|string|max:10000',
                'linkedin_url' => 'nullable|url:http,https|max:500',
                'portfolio_url' => 'nullable|url:http,https|max:500',
            ],
        };
    }
}
