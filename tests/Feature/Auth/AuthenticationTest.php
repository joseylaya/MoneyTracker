<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('trackers.index'));
    }

    public function test_users_can_authenticate_with_their_nickname(): void
    {
        $user = User::factory()->create(['username' => 'travel_buddy']);
        $this->post('/login', ['login' => 'travel_buddy', 'password' => 'password'])->assertRedirect(route('trackers.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_stale_protected_destination_is_not_reused_after_login(): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => url('/trackers/not-my-tracker')])
            ->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('trackers.index'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
