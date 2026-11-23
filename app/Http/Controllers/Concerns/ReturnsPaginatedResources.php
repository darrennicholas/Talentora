<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

trait ReturnsPaginatedResources
{
    protected function paginatedResource(FormRequest $request, Builder $query, array $sorts, string $resourceClass)
    {
        $data = $request->validated();
        $page = $query
            ->orderBy($data['sort_by'] ?? $sorts[0], $data['sort_dir'] ?? 'desc')
            ->paginate($data['per_page'] ?? 10);

        return $resourceClass::collection($page->getCollection())->additional([
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
