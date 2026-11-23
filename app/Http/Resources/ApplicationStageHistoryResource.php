<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationStageHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_stage' => $this->from_stage,
            'to_stage' => $this->to_stage,
            'note' => $this->note,
            'changed_by' => $this->whenLoaded('changedBy', fn () => new UserResource($this->changedBy)),
            'changed_at' => $this->changed_at,
        ];
    }
}
