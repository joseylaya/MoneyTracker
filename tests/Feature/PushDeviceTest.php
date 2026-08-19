<?php

namespace Tests\Feature;

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
}
