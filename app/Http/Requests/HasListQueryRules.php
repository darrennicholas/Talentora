<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

trait HasListQueryRules
{
    protected function listQueryRules(array $sortableFields): array
    {
        return [
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => ['nullable', Rule::in($sortableFields)],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
