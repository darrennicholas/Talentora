<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsPaginatedResources;
use App\Http\Requests\SettingsRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActiveAdminGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    use ReturnsPaginatedResources;

    public function profile(Request $request)
    {
        return new ProfileResource([
            'user' => $request->user(),
            'settings' => DB::table('app_settings')->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function updateProfile(SettingsRequest $request)
    {
        $data = $request->validated();
        $request->user()->update($data);

        return new UserResource($request->user()->fresh());
    }

    public function updatePassword(SettingsRequest $request)
    {
        $data = $request->validated();
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }
        $request->user()->update(['password' => $data['new_password']]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function appSettings(Request $request)
    {
        return response()->json(['data' => DB::table('app_settings')
            ->whereIn('setting_key', ['company_name', 'timezone'])->pluck('setting_value', 'setting_key')]);
    }

    public function updateAppSettings(SettingsRequest $request)
    {
        $unknown = array_diff(array_keys($request->all()), ['company_name', 'timezone']);
        abort_if($unknown, 422, 'Unknown settings: '.implode(', ', $unknown));
        $data = $request->validated();
        abort_if(! $data, 422);

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('app_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => $value, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        return $this->appSettings($request);
    }

    public function users(SettingsRequest $request)
    {
        $request->validated();
        $query = User::query()->select('id', 'name', 'email', 'role', 'is_active', 'created_at');

        return $this->paginatedResource($request, $query, ['created_at', 'name', 'email'], UserResource::class);
    }

    public function createUser(SettingsRequest $request)
    {
        $data = $request->validated();
        $data['role'] = 'admin';
        $data['is_active'] = true;

        return (new UserResource(User::create($data)))->response()->setStatusCode(201);
    }

    public function updateUser(SettingsRequest $request, User $user)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($user, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->update($data);

            return new UserResource($locked->fresh());
        });
    }

    public function updateUserStatus(SettingsRequest $request, User $user, ActiveAdminGuard $adminGuard)
    {
        $data = $request->validated();
        abort_if(! $data['is_active'] && $user->id === $request->user()->id, 422, 'Cannot deactivate yourself.');

        return DB::transaction(function () use ($request, $user, $data, $adminGuard) {
            User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $data['is_active'] && $locked->role === 'admin') {
                $adminGuard->assertAnotherActiveAdminExists($locked);
            }
            $locked->update($data);

            return new UserResource($locked->fresh());
        });
    }
}
