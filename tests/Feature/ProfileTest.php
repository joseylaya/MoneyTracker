<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'test_user',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => $user->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_photo_is_resized_and_stored_only_as_webp(): void
    {
        config(['filesystems.profile_photos_disk' => 'public']);
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('large-photo.png', 1200, 800)->size(900),
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertStringEndsWith('.webp', $user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $contents = Storage::disk('public')->get($user->avatar_path);
        $this->assertSame('RIFF', substr($contents, 0, 4));
        $this->assertSame('WEBP', substr($contents, 8, 4));
        [$width, $height] = getimagesizefromstring($contents);
        $this->assertLessThanOrEqual(512, max($width, $height));
        $this->assertLessThanOrEqual(250 * 1024, strlen($contents));
    }

    public function test_profile_photo_rejects_unsupported_files_and_can_be_removed(): void
    {
        config(['filesystems.profile_photos_disk' => 'public']);
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->create('avatar.gif', 20, 'image/gif'),
        ])->assertSessionHasErrors('photo');

        Storage::disk('public')->put('profile-photos/existing.webp', 'old');
        $user->forceFill(['avatar_path' => 'profile-photos/existing.webp'])->save();
        $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertRedirect(route('profile.edit'));
        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing('profile-photos/existing.webp');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
