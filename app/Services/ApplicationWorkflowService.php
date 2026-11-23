<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Support\Facades\DB;

class ApplicationWorkflowService
{
    private const ALLOWED = [
        'applied' => ['screening', 'rejected'],
        'screening' => ['rejected'],
        'interview' => ['offered', 'rejected'],
        'offered' => ['hired', 'rejected'],
        'hired' => [],
        'rejected' => [],
    ];

    public function transition(int $applicationId, string $from, string $to, ?string $note, int $userId): ?Application
    {
        return DB::transaction(function () use ($applicationId, $from, $to, $note, $userId) {
            $application = Application::whereKey($applicationId)->lockForUpdate()->firstOrFail();
            if ($application->stage !== $from) return null;
            abort_unless(in_array($to, self::ALLOWED[$from] ?? [], true), 422, 'Stage transition is not allowed.');
            if ($to === 'rejected') abort_if(blank($note), 422, 'Rejection reason is required.');
            if ($to === 'offered' && (
                ! $application->interviews()->where('status', 'completed')->whereNotNull('rating')->whereNotNull('recommendation')->whereNotNull('feedback')->exists()
                || $application->interviews()->where('status', 'scheduled')->exists()
            )) abort(422, 'Complete evaluation and clear scheduled interviews first.');
            if ($to === 'hired' && $application->interviews()->where('status', 'scheduled')->exists()) abort(422, 'Cancel or complete scheduled interviews first.');

            $now = now();
            $application->stage = $to;
            $application->stage_updated_at = $now;
            if ($to === 'hired') $application->hired_at = $now;
            if ($to === 'rejected') {
                $application->rejected_at = $now;
                $application->rejection_reason = $note;
                $application->interviews()->where('status', 'scheduled')->update(['status' => 'cancelled', 'updated_at' => $now]);
            }
            $application->save();
            $application->histories()->create([
                'from_stage' => $from,
                'to_stage' => $to,
                'note' => $note,
                'changed_by' => $userId,
                'changed_at' => $now,
            ]);

            return $application->load(['candidate', 'jobPosition', 'histories', 'interviews']);
        });
    }

    public function interviewScheduled(Application $application, int $userId): void
    {
        if ($application->stage !== 'screening') return;
        $now = now();
        $application->update(['stage' => 'interview', 'stage_updated_at' => $now]);
        $application->histories()->create([
            'from_stage' => 'screening',
            'to_stage' => 'interview',
            'changed_by' => $userId,
            'changed_at' => $now,
        ]);
    }
}
