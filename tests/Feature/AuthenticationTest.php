<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_logout(): void
    {
        $this->withHeader('Origin', 'http://localhost');
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertOk();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.app_timezone', 'Asia/Jakarta');
        $this->postJson('/api/logout')->assertOk();
        app('auth')->forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login_or_use_existing_session(): void
    {
        $user = User::factory()->create(['password' => 'password', 'role' => 'admin', 'is_active' => false]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->withHeader('Origin', 'http://localhost')->actingAs($user)->getJson('/api/me')->assertUnauthorized();
    }
}
