<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials + ['is_active' => true])) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        $request->session()->regenerate();

        return new ProfileResource([
            'user' => $request->user(),
            'settings' => DB::table('app_settings')->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
