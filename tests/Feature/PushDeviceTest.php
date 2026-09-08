<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_json_request_can_save_a_web_push_subscription_without_a_page_response(): void
    {
        $user = User::factory()->create();

        $subscription = [
            'endpoint' => 'https://push.example.test/subscription/1',
            'keys' => ['p256dh' => str_repeat('a', 120), 'auth' => str_repeat('b', 24)],
        ];

        $this->actingAs($user)->postJson(route('push-devices.store'), ['subscription' => $subscription])
            ->assertNoContent();

        $this->assertDatabaseHas('web_push_subscriptions', ['user_id' => $user->id, 'endpoint' => $subscription['endpoint']]);
    }

    public function test_a_browser_can_register_multiple_unique_fcm_tokens(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-devices.store'), ['token' => 'fcm-token-one'])->assertNoContent();
        $this->actingAs($user)->postJson(route('push-devices.store'), ['token' => 'fcm-token-two'])->assertNoContent();
        $this->actingAs($user)->postJson(route('push-devices.store'), ['token' => 'fcm-token-one'])->assertNoContent();

        $this->assertSame(2, PushDevice::where('user_id', $user->id)->count());
    }

    public function test_registering_the_same_browser_token_moves_it_to_the_current_user(): void
    {
        $first = User::factory()->create(); $second = User::factory()->create();
        $this->actingAs($first)->postJson(route('push-devices.store'), ['token' => 'shared-browser-token'])->assertNoContent();
        $this->actingAs($second)->postJson(route('push-devices.store'), ['token' => 'shared-browser-token'])->assertNoContent();

        $this->assertDatabaseMissing('push_devices', ['user_id' => $first->id, 'token' => 'shared-browser-token']);
        $this->assertDatabaseHas('push_devices', ['user_id' => $second->id, 'token' => 'shared-browser-token']);
    }

    public function test_user_can_detach_only_the_current_browser_fcm_token(): void
    {
        $user = User::factory()->create();
        PushDevice::create(['user_id' => $user->id, 'token' => 'keep-token', 'platform' => 'web']);
        PushDevice::create(['user_id' => $user->id, 'token' => 'logout-token', 'platform' => 'web']);

        $this->actingAs($user)->deleteJson(route('push-devices.destroy'), ['token' => 'logout-token'])->assertNoContent();

        $this->assertDatabaseHas('push_devices', ['user_id' => $user->id, 'token' => 'keep-token']);
        $this->assertDatabaseMissing('push_devices', ['user_id' => $user->id, 'token' => 'logout-token']);
    }
}
