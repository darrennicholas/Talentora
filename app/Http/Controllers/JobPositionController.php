<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsPaginatedResources;
use App\Http\Requests\JobPositionRequest;
use App\Http\Resources\JobPositionResource;
use App\Models\JobPosition;
use Illuminate\Http\Request;

class JobPositionController extends Controller
{
    use ReturnsPaginatedResources;

    public function index(JobPositionRequest $request)
    {
        $request->validated();

        $query = JobPosition::query()->withCount(['applications as applicants_count']);
        if ($search = trim($request->query('q', ''))) {
            $query->where(fn ($where) => $where->where('code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%"));
        }
        foreach (['department', 'employment_type', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return $this->paginatedResource($request, $query, ['created_at', 'title', 'code'], JobPositionResource::class);
    }

    public function show(JobPosition $job)
    {
        return new JobPositionResource($job->loadCount(['applications as applicants_count']));
    }

    public function store(JobPositionRequest $request)
    {
        return $this->save($request);
    }

    public function update(JobPositionRequest $request, JobPosition $job)
    {
        return $this->save($request, $job);
    }

    private function save(JobPositionRequest $request, ?JobPosition $job = null)
    {
        $data = $request->validated();

        if (! $job) {
            $data['created_by'] = $request->user()->id;
            $job = JobPosition::create($data);
        } else {
            $job->update($data);
        }

        return (new JobPositionResource($job->fresh()->loadCount(['applications as applicants_count'])))
            ->response()
            ->setStatusCode($job->wasRecentlyCreated ? 201 : 200);
    }

    public function updateStatus(JobPositionRequest $request, JobPosition $job)
    {
        $data = $request->validated();
        $job->update($data);

        return new JobPositionResource($job->fresh()->loadCount(['applications as applicants_count']));
    }

    public function destroy(JobPosition $job)
    {
        abort_if($job->applications()->exists(), 409, 'Position has applications.');
        $job->delete();

        return response()->noContent();
    }
}
