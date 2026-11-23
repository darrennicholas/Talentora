<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InterviewSchedulingService
{
    public function __construct(private ApplicationWorkflowService $workflow) {}

    public function save(array $data, ?Interview $interview, int $userId): Interview
    {
        return DB::transaction(function () use ($data, $interview, $userId) {
            if ($interview) {
                $interview = Interview::whereKey($interview->id)->lockForUpdate()->firstOrFail();
                abort_unless($interview->status === 'scheduled', 409, 'Only scheduled interviews can be changed.');
            }

            $application = Application::whereKey($interview?->application_id ?? $data['application_id'])->lockForUpdate()->firstOrFail();
            $interviewer = User::whereKey($data['interviewer_id'])->lockForUpdate()->firstOrFail();
            abort_unless($interviewer->is_active, 422, 'Interviewer must be active.');
            abort_unless(in_array($application->stage, ['screening', 'interview'], true), 422, 'Application must be screening or interview.');
            if (! $interview && $application->interviews()->where('status', 'scheduled')->exists()) {
                abort(409, 'Application already has a scheduled interview.');
            }

            $starts = Carbon::parse($data['start_at'])->utc();
            $ends = Carbon::parse($data['end_at'])->utc();
            $overlap = Interview::where('interviewer_id', $interviewer->id)
                ->where('status', 'scheduled')
                ->where('start_at', '<', $ends)
                ->where('end_at', '>', $starts)
                ->when($interview, fn ($query) => $query->where('id', '!=', $interview->id))
                ->exists();
            if ($overlap) abort(409, 'Interviewer has an overlapping interview.');

            $values = array_merge($data, ['start_at' => $starts, 'end_at' => $ends, 'created_by' => $userId]);
            if ($interview) {
                $interview->update(collect($values)->except(['application_id', 'created_by'])->all());
                $saved = $interview->fresh();
            } else {
                $saved = Interview::create($values + ['status' => 'scheduled']);
                $this->workflow->interviewScheduled($application, $userId);
            }

            return $saved->load(['application.candidate', 'application.jobPosition', 'interviewer']);
        });
    }
}
