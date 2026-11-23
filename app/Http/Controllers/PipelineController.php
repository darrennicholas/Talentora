<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicationResource;
use App\Http\Requests\PipelineRequest;
use App\Models\Application;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    private const STAGES = ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'];

    public function index(PipelineRequest $request)
    {
        $request->validated();
        $limit = $request->integer('limit_per_stage', 50);
        $data = [];

        foreach (self::STAGES as $stage) {
            $query = Application::with(['candidate', 'jobPosition'])->where('stage', $stage);
            if ($request->filled('job_position_id')) {
                $query->where('job_position_id', $request->integer('job_position_id'));
            }
            if ($request->filled('q')) {
                $search = $request->query('q');
                $query->where(fn ($where) => $where->where('application_code', 'like', "%{$search}%")
                    ->orWhereHas('candidate', fn ($candidate) => $candidate->where('full_name', 'like', "%{$search}%")));
            }

            $applications = (clone $query)->limit($limit)->get();
            $data[$stage] = [
                'applications' => ApplicationResource::collection($applications)->resolve($request),
                'total' => $query->count(),
            ];
        }

        return response()->json(['data' => $data]);
    }
}
