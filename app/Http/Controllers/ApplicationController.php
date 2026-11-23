<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsPaginatedResources;
use App\Http\Requests\ApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\ApplicationStageHistoryResource;
use App\Models\Application;
use App\Models\JobPosition;
use App\Services\ApplicationWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    use ReturnsPaginatedResources;

    private const STAGES = ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'];

    public function __construct(private ApplicationWorkflowService $workflow) {}

    public function index(ApplicationRequest $request)
    {
        $data = $request->validated();
        $this->validateDateRange($data);

        $query = Application::with(['candidate', 'jobPosition']);
        foreach (['candidate_id', 'job_position_id', 'stage'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($search = trim($request->query('q', ''))) {
            $query->where(fn ($where) => $where->where('application_code', 'like', "%{$search}%")
                ->orWhereHas('candidate', fn ($candidate) => $candidate->where('full_name', 'like', "%{$search}%")));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('applied_at', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('applied_at', '<=', $request->query('end_date'));
        }

        return $this->paginatedResource($request, $query, ['applied_at', 'created_at', 'updated_at'], ApplicationResource::class);
    }

    private function validateDateRange(array $data): void
    {
        abort_if(isset($data['start_date']) !== isset($data['end_date']), 422, 'Both dates are required.');
        abort_if(isset($data['start_date']) && $data['start_date'] > $data['end_date'], 422, 'Invalid date range.');
    }

    public function show(Application $app)
    {
        return new ApplicationResource($app->load(['candidate', 'jobPosition', 'histories.changedBy', 'interviews.interviewer']));
    }

    public function history(Application $app)
    {
        return ApplicationStageHistoryResource::collection($app->histories()->with('changedBy')->get());
    }

    public function store(ApplicationRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            $position = JobPosition::whereKey($data['job_position_id'])->lockForUpdate()->firstOrFail();
            abort_unless($position->status === 'open', 422, 'Position is not open.');
            abort_if(
                Application::where('candidate_id', $data['candidate_id'])
                    ->where('job_position_id', $data['job_position_id'])
                    ->exists(),
                409,
                'Candidate already applied to this position.',
            );

            $application = Application::create($data + [
                'application_code' => 'APP-'.Str::upper((string) Str::ulid()),
                'stage' => 'applied',
                'stage_updated_at' => now(),
                'created_by' => $request->user()->id,
            ]);
            $application->histories()->create([
                'from_stage' => null,
                'to_stage' => 'applied',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            return (new ApplicationResource($application->load(['candidate', 'jobPosition', 'histories'])))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function update(ApplicationRequest $request, Application $app)
    {
        abort_if(in_array($app->stage, ['hired', 'rejected'], true), 422, 'Terminal applications are read only.');
        $data = $request->validated();
        $app->update($data);

        return new ApplicationResource($app->load(['candidate', 'jobPosition', 'histories', 'interviews']));
    }

    public function updateStage(ApplicationRequest $request, Application $app)
    {
        $data = $request->validated();
        $updated = $this->workflow->transition(
            $app->id,
            $data['from_stage'],
            $data['to_stage'],
            $data['note'] ?? null,
            $request->user()->id,
        );

        if (! $updated) {
            return response()->json([
                'message' => 'Application stage changed. Refresh and try again.',
                'code' => 'STAGE_CONFLICT',
                'data' => ['current_stage' => Application::findOrFail($app->id)->stage],
            ], 409);
        }

        return new ApplicationResource($updated);
    }
}
