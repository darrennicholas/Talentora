<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApplicationRequest extends ApiRequest
{
    use HasListQueryRules;
    private const STAGES = ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'];

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if ($action === 'index') return [
            'candidate_id' => 'nullable|integer|exists:candidates,id',
            'job_position_id' => 'nullable|integer|exists:job_positions,id',
            'stage' => ['nullable', Rule::in(self::STAGES)],
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ] + $this->listQueryRules(['applied_at', 'created_at', 'updated_at']);
        if ($action === 'updateStage') return [
            'from_stage' => ['required', Rule::in(self::STAGES)],
            'to_stage' => ['required', Rule::in(self::STAGES)],
            'note' => 'nullable|string|max:10000',
        ];

        $timezone = DB::table('app_settings')->where('setting_key', 'timezone')->value('setting_value') ?? 'Asia/Jakarta';
        $date = ['date_format:Y-m-d', 'before_or_equal:'.now($timezone)->toDateString()];
        $updating = $action === 'update';
        return [
            'candidate_id' => [$updating ? 'prohibited' : 'required', 'exists:candidates,id'],
            'job_position_id' => [$updating ? 'prohibited' : 'required', 'exists:job_positions,id'],
            'application_code' => 'prohibited', 'stage' => 'prohibited', 'stage_updated_at' => 'prohibited',
            'hired_at' => 'prohibited', 'rejected_at' => 'prohibited', 'rejection_reason' => 'prohibited',
            'source' => [$updating ? 'sometimes' : 'required', Rule::in(['manual', 'referral', 'job_portal', 'other'])],
            'applied_at' => array_merge([$updating ? 'sometimes' : 'required'], $date),
            'notes' => 'nullable|string|max:10000',
        ];
    }
}
