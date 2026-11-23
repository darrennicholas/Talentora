<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsPaginatedResources;
use App\Http\Requests\InterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Http\Resources\UserResource;
use App\Models\Interview;
use App\Models\User;
use App\Services\InterviewSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterviewController extends Controller
{
    use ReturnsPaginatedResources;

    public function __construct(private InterviewSchedulingService $scheduler) {}

    public function index(InterviewRequest $request)
    {
        $data = $request->validated();
        $this->validateDateRange($data);

        $query = Interview::with(['application.candidate', 'application.jobPosition', 'interviewer']);
        foreach (['application_id', 'interviewer_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('job_position_id')) {
            $query->whereHas('application', fn ($application) => $application->where('job_position_id', $request->integer('job_position_id')));
        }
        if ($request->boolean('upcoming')) {
            $query->whereBetween('start_at', [now(), now()->addDays(14)]);
        }
        if ($request->filled('start_date')) {
            $timezone = DB::table('app_settings')->where('setting_key', 'timezone')->value('setting_value') ?? 'Asia/Jakarta';
            $from = Carbon::parse($data['start_date'], $timezone)->startOfDay()->utc();
            $to = Carbon::parse($data['end_date'], $timezone)->endOfDay()->utc();
            $query->whereBetween('start_at', [$from, $to]);
        }
        if ($search = trim($request->query('q', ''))) {
            $query->whereHas('application.candidate', fn ($candidate) => $candidate->where('full_name', 'like', "%{$search}%"));
        }

        return $this->paginatedResource($request, $query, ['start_at', 'created_at'], InterviewResource::class);
    }

    private function validateDateRange(array $data): void
    {
        abort_if(isset($data['start_date']) !== isset($data['end_date']), 422, 'Both dates are required.');
        abort_if(isset($data['start_date']) && $data['start_date'] > $data['end_date'], 422, 'Invalid date range.');
    }

    public function show(Interview $interview)
    {
        return new InterviewResource($interview->load(['application.candidate', 'application.jobPosition', 'interviewer']));
    }

    public function interviewers()
    {
        return UserResource::collection(User::where('is_active', true)
            ->where('role', 'admin')->orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']));
    }

    public function calendar(InterviewRequest $request)
    {
        $data = $request->validated();
        $this->assertOffsetTimestamps($data);
        abort_if(Carbon::parse($data['start_at'])->diffInDays(Carbon::parse($data['end_at'])) > 93, 422, 'Range is limited to 93 days.');

        $interviews = Interview::with(['application.candidate', 'application.jobPosition', 'interviewer'])
            ->where('start_at', '>=', Carbon::parse($data['start_at'])->utc())
            ->where('start_at', '<', Carbon::parse($data['end_at'])->utc())
            ->get();

        return InterviewResource::collection($interviews);
    }

    private function assertOffsetTimestamps(array $data): void
    {
        foreach (['start_at', 'end_at'] as $field) {
            abort_unless(preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $data[$field]), 422, 'Timestamps must include a timezone offset.');
        }
    }

    public function store(InterviewRequest $request)
    {
        return $this->save($request);
    }

    public function update(InterviewRequest $request, Interview $interview)
    {
        return $this->save($request, $interview);
    }

    private function save(InterviewRequest $request, ?Interview $interview = null)
    {
        $data = $request->validated();
        $this->assertOffsetTimestamps($data);
        abort_if(Carbon::parse($data['start_at'])->lessThanOrEqualTo(now()), 422, 'Interview must be in the future.');
        abort_if($data['mode'] === 'online' && blank($data['meeting_link'] ?? null), 422, 'Meeting link is required.');
        abort_if($data['mode'] === 'onsite' && blank($data['location'] ?? null), 422, 'Location is required.');

        $saved = $this->scheduler->save($data, $interview, $request->user()->id);

        return (new InterviewResource($saved))->response()->setStatusCode($interview ? 200 : 201);
    }

    public function complete(InterviewRequest $request, Interview $interview)
    {
        $data = $request->validated();
        abort_if(blank($data['feedback']), 422, 'Feedback is required.');

        return DB::transaction(function () use ($interview, $data) {
            $locked = Interview::whereKey($interview->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'scheduled', 409, 'Only scheduled interviews can be completed.');
            abort_if($locked->end_at->isFuture(), 422, 'Interview has not ended.');
            $locked->update($data + ['status' => 'completed']);

            return new InterviewResource($locked->fresh()->load(['application.candidate', 'application.jobPosition', 'interviewer']));
        });
    }

    public function cancel(InterviewRequest $request, Interview $interview)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($interview, $data) {
            $locked = Interview::whereKey($interview->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'scheduled', 409, 'Only scheduled interviews can be cancelled.');
            $locked->update([
                'status' => 'cancelled',
                'notes' => trim(($locked->notes ?? '').(filled($data['reason'] ?? null) ? "\nCancellation: {$data['reason']}" : '')),
            ]);

            return new InterviewResource($locked->fresh()->load(['application.candidate', 'application.jobPosition', 'interviewer']));
        });
    }
}
