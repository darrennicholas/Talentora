<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'];
        $user = $this->resource['user'];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'app_timezone' => $settings['timezone'] ?? 'Asia/Jakarta',
            'company_name' => $settings['company_name'] ?? 'Talentora Demo Company',
        ];
    }
}
