<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicationResource;
use App\Http\Resources\InterviewResource;
use App\Http\Requests\DashboardRequest;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const STAGES = ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'];

    public function __invoke(DashboardRequest $request)
    {
        $dates = $request->validated();
        abort_if(isset($dates['start_date']) !== isset($dates['end_date']), 422, 'Both dates are required.');

        $timezone = DB::table('app_settings')->where('setting_key', 'timezone')->value('setting_value') ?? 'Asia/Jakarta';
        $end = $dates['end_date'] ?? now($timezone)->toDateString();
        $start = $dates['start_date'] ?? now($timezone)->subDays(29)->toDateString();
        abort_if($start > $end, 422, 'start_date must not be after end_date.');

        $cohort = Application::whereBetween('applied_at', [$start, $end]);
        $upcoming = Interview::with(['application.candidate', 'application.jobPosition', 'interviewer'])
            ->where('status', 'scheduled')
            ->whereBetween('start_at', [now(), now()->addDays(14)])
            ->orderBy('start_at')
            ->orderBy('id');
        $stages = collect(self::STAGES)->map(fn ($stage) => [
            'stage' => $stage,
            'total' => (clone $cohort)->where('stage', $stage)->count(),
        ]);
        $byPosition = (clone $cohort)
            ->select('job_position_id', DB::raw('COUNT(*) total'))
            ->groupBy('job_position_id')
            ->with('jobPosition')
            ->get()
            ->map(fn ($row) => [
                'job_position_id' => $row->job_position_id,
                'title' => $row->jobPosition->title,
                'total' => $row->total,
            ]);

        return response()->json(['data' => [
            'period' => ['start_date' => $start, 'end_date' => $end],
            'total_candidates' => Candidate::count(),
            'active_job_positions' => JobPosition::where('status', 'open')->count(),
            'total_applications' => (clone $cohort)->count(),
            'upcoming_scheduled_interviews' => (clone $upcoming)->count(),
            'hired_applications' => (clone $cohort)->where('stage', 'hired')->count(),
            'rejected_applications' => (clone $cohort)->where('stage', 'rejected')->count(),
            'stage_distribution' => $stages,
            'applications_by_position' => $byPosition,
            'upcoming_interviews' => InterviewResource::collection($upcoming->limit(5)->get())->resolve($request),
            'recent_applications' => ApplicationResource::collection(
                (clone $cohort)->with(['candidate', 'jobPosition'])->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
            )->resolve($request),
        ]]);
    }
}
