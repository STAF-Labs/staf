<?php

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_session_cookie_authentication(): void
    {
        $user = User::query()->create([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'tester@example.com');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::query()->create([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'tester@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_be_loaded_from_cookie_session(): void
    {
        $user = User::query()->create([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => 'password',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'tester@example.com');
    }

    public function test_user_can_logout_and_session_is_invalidated(): void
    {
        $user = User::query()->create([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => 'password',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/logout')
            ->assertOk();

        $this->assertGuest();

        $this
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
