<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SettingsRequest extends ApiRequest
{
    use HasListQueryRules;
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) $this->merge(['email' => strtolower(trim($this->input('email')))]);
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        return match ($action) {
            'users' => $this->listQueryRules(['created_at', 'name', 'email']),
            'updateProfile' => [
                'name' => 'sometimes|string|min:2|max:150',
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            ],
            'updatePassword' => ['current_password' => 'required|string', 'new_password' => 'required|string|min:8|confirmed'],
            'updateAppSettings' => [
                'company_name' => 'sometimes|string|min:2|max:200', 'timezone' => 'sometimes|string|timezone:all',
            ],
            'createUser' => [
                'name' => 'required|string|min:2|max:150', 'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8', 'role' => 'missing',
            ],
            'updateUser' => [
                'name' => 'sometimes|string|min:2|max:150',
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user')->id)],
                'role' => 'missing',
            ],
            'updateUserStatus' => ['is_active' => 'required|boolean'],
            default => [],
        };
    }
}
