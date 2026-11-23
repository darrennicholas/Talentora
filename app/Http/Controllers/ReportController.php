<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Interview;
use App\Models\JobPosition;
use App\Http\Requests\ReportRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const STAGES = ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'];

    public function show(ReportRequest $request, string $type)
    {
        abort_unless(in_array($type, ['summary', 'stages', 'trends', 'positions', 'metrics'], true), 404);
        $data = $request->validated();
        abort_if(isset($data['start_date']) !== isset($data['end_date']), 422, 'Both dates are required.');

        $timezone = DB::table('app_settings')->where('setting_key', 'timezone')->value('setting_value') ?? 'Asia/Jakarta';
        $end = $data['end_date'] ?? now($timezone)->toDateString();
        $start = $data['start_date'] ?? now($timezone)->subDays(29)->toDateString();
        abort_if($start > $end, 422, 'Invalid date range.');

        $base = $this->cohort($data, $start, $end);
        $total = (clone $base)->count();
        $hired = (clone $base)->where('stage', 'hired')->count();

        return match ($type) {
            'summary' => $this->summary($data, $base, $total, $hired, $start, $end, $timezone),
            'stages' => $this->stages($base),
            'metrics' => $this->metrics($base, $total, $hired, $timezone),
            'positions' => $this->positions($request, $data, $start, $end),
            'trends' => $this->trends($base, $data),
        };
    }

    private function cohort(array $data, string $start, string $end): Builder
    {
        return Application::whereBetween('applied_at', [$start, $end])
            ->when(isset($data['job_position_id']), fn ($query) => $query->where('job_position_id', $data['job_position_id']))
            ->when(isset($data['department']), fn ($query) => $query->whereHas(
                'jobPosition', fn ($position) => $position->where('department', $data['department']),
            ));
    }

    private function summary(array $data, Builder $base, int $total, int $hired, string $start, string $end, string $timezone)
    {
        $from = Carbon::parse($start, $timezone)->startOfDay()->utc();
        $to = Carbon::parse($end, $timezone)->endOfDay()->utc();
        $completed = Interview::where('status', 'completed')
            ->whereBetween('start_at', [$from, $to])
            ->whereHas('application', fn ($application) => $application
                ->when(isset($data['job_position_id']), fn ($query) => $query->where('job_position_id', $data['job_position_id']))
                ->when(isset($data['department']), fn ($query) => $query->whereHas(
                    'jobPosition', fn ($position) => $position->where('department', $data['department']),
                )));
        $open = JobPosition::where('status', 'open')
            ->when(isset($data['job_position_id']), fn ($query) => $query->whereKey($data['job_position_id']))
            ->when(isset($data['department']), fn ($query) => $query->where('department', $data['department']));

        return response()->json(['data' => [
            'total_applications' => $total,
            'hired_applications' => $hired,
            'rejected_applications' => (clone $base)->where('stage', 'rejected')->count(),
            'completed_interviews' => $completed->count(),
            'active_job_positions' => $open->count(),
        ]]);
    }

    private function stages(Builder $base)
    {
        return response()->json(['data' => collect(self::STAGES)->map(fn ($stage) => [
            'stage' => $stage,
            'total' => (clone $base)->where('stage', $stage)->count(),
        ])]);
    }

    private function metrics(Builder $base, int $total, int $hired, string $timezone)
    {
        $days = (clone $base)->where('stage', 'hired')->whereNotNull('hired_at')
            ->get(['applied_at', 'hired_at'])
            ->map(function ($application) use ($timezone) {
                $hiredAt = Carbon::parse($application->hired_at)->utc()->getTimestamp();
                $appliedAt = Carbon::parse($application->applied_at->toDateString(), $timezone)
                    ->startOfDay()->utc()->getTimestamp();

                return ($hiredAt - $appliedAt) / 86400;
            });

        return response()->json(['data' => [
            'hiring_rate_percent' => $total ? round($hired / $total * 100, 2) : 0,
            'average_time_to_hire_days' => $days->isEmpty() ? null : round($days->avg(), 2),
        ]]);
    }

    private function positions(Request $request, array $data, string $start, string $end)
    {
        $query = JobPosition::query()
            ->when(isset($data['job_position_id']), fn ($builder) => $builder->whereKey($data['job_position_id']))
            ->when(isset($data['department']), fn ($builder) => $builder->where('department', $data['department']));
        $page = $query->orderBy('title')->paginate($data['per_page'] ?? 10);
        $ids = $page->getCollection()->modelKeys();
        $counts = Application::query()
            ->select('job_position_id', 'stage', DB::raw('COUNT(*) as total'))
            ->whereIn('job_position_id', $ids)
            ->whereBetween('applied_at', [$start, $end])
            ->groupBy('job_position_id', 'stage')
            ->get()
            ->groupBy('job_position_id');

        $page->getCollection()->transform(function (JobPosition $position) use ($counts) {
            $stages = $counts->get($position->id, collect())->pluck('total', 'stage');

            return [
                'job_position_id' => $position->id,
                'title' => $position->title,
                'department' => $position->department,
                'total' => (int) $stages->sum(),
                'in_progress' => collect(['applied', 'screening', 'interview', 'offered'])
                    ->sum(fn ($stage) => (int) ($stages[$stage] ?? 0)),
                'hired' => (int) ($stages['hired'] ?? 0),
                'rejected' => (int) ($stages['rejected'] ?? 0),
            ];
        });

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    private function trends(Builder $base, array $data)
    {
        $format = match ($data['group_by'] ?? 'daily') {
            'weekly' => '%x-W%v',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };
        $rows = (clone $base)->selectRaw('DATE_FORMAT(applied_at, ?) period, COUNT(*) total', [$format])
            ->groupBy('period')->orderBy('period')->get();

        return response()->json(['data' => $rows]);
    }
}
