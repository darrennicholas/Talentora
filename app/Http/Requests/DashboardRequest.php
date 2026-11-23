<?php

namespace App\Http\Requests;

class DashboardRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d'];
    }
}
