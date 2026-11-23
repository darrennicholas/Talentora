<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsPaginatedResources;
use App\Http\Requests\CandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidateController extends Controller
{
    use ReturnsPaginatedResources;

    public function index(CandidateRequest $request)
    {
        $request->validated();
        $query = Candidate::withCount('applications');
        if ($search = trim($request->query('q', ''))) {
            $query->where(fn ($where) => $where->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('location')) {
            $query->where('location', $request->query('location'));
        }

        return $this->paginatedResource($request, $query, ['created_at', 'full_name'], CandidateResource::class);
    }

    public function show(Candidate $candidate)
    {
        return new CandidateResource($candidate->load([
            'applications.jobPosition',
            'applications.histories.changedBy',
            'applications.interviews.interviewer',
        ]));
    }

    public function store(CandidateRequest $request)
    {
        return $this->save($request);
    }

    public function update(CandidateRequest $request, Candidate $candidate)
    {
        return $this->save($request, $candidate);
    }

    private function save(CandidateRequest $request, ?Candidate $candidate = null)
    {
        $isNew = ! $candidate;
        $data = $request->validated();

        $candidate ??= new Candidate;
        if ($isNew) {
            $data['created_by'] = $request->user()->id;
        }
        $candidate->fill($data)->save();

        return (new CandidateResource($candidate->load('applications.jobPosition')))
            ->response()
            ->setStatusCode($isNew ? 201 : 200);
    }

    public function destroy(Candidate $candidate)
    {
        abort_if($candidate->applications()->exists(), 409, 'Candidate has applications.');
        $path = $candidate->cv_path;
        $candidate->delete();
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return response()->noContent();
    }

    public function uploadCv(CandidateRequest $request, Candidate $candidate)
    {
        $request->validated();
        $oldPath = $candidate->cv_path;
        $file = $request->file('cv');
        $path = $file->store('cvs', 'local');

        try {
            $candidate->update([
                'cv_path' => $path,
                'cv_original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'cv_mime_type' => $file->getMimeType(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['data' => ['has_cv' => true, 'original_name' => $candidate->cv_original_name]]);
    }

    public function downloadCv(Candidate $candidate)
    {
        abort_unless($candidate->cv_path && Storage::disk('local')->exists($candidate->cv_path), 404);

        return Storage::disk('local')->download($candidate->cv_path, $candidate->cv_original_name);
    }

    public function deleteCv(Candidate $candidate)
    {
        $path = $candidate->cv_path;
        $candidate->update(['cv_path' => null, 'cv_original_name' => null, 'cv_mime_type' => null]);
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return response()->noContent();
    }
}
