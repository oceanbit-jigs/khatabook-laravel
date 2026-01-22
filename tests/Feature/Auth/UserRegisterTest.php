<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRegisterTest extends TestCase
{
    protected string $registerUrl = '/api/user/register';

    // Success Register
    public function test_register_success()
    {
        $user = User::factory()->make();

        $response = $this->postJson($this->registerUrl, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'password',
            'confirm_password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'token',
                'data' => ['id', 'first_name', 'last_name', 'email']
            ]);
    }

    // Missing Required Fields
    public function test_register_missing_required_fields()
    {
        $response = $this->postJson($this->registerUrl, []);

        $response->assertStatus(406)
            ->assertJsonStructure([
                'status',
                'message',
                'error' => [['key', 'error']]
            ]);

        $errors = collect($response->json('error'))->pluck('key')->toArray();

        $this->assertContains('first_name', $errors);
        $this->assertContains('last_name', $errors);
        $this->assertContains('email', $errors);
        $this->assertContains('phone', $errors);
        $this->assertContains('password', $errors);
        $this->assertContains('confirm_password', $errors);
    }

    // Invalid Email
    public function test_register_invalid_email()
    {
        $user = User::factory()->make();

        $response = $this->postJson($this->registerUrl, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'invalidemail',
            'phone' => $user->phone,
            'password' => 'password',
            'confirm_password' => 'password',
        ]);

        $response->assertStatus(406);
        $this->assertContains('email', collect($response->json('error'))->pluck('key')->toArray());
    }

    // Password Mismatch
    public function test_register_password_mismatch()
    {
        $user = User::factory()->make();

        $response = $this->postJson($this->registerUrl, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'password',
            'confirm_password' => 'wrongpassword',
        ]);

        $response->assertStatus(406);
        $this->assertContains('confirm_password', collect($response->json('error'))->pluck('key')->toArray());
    }

    // Email Already Exists
    public function test_register_email_already_exists()
    {
        $existing = User::factory()->create();

        $response = $this->postJson($this->registerUrl, [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $existing->email,
            'phone' => User::factory()->make()->phone,
            'password' => 'password',
            'confirm_password' => 'password',
        ]);

        $response->assertStatus(406);
        $this->assertContains('email', collect($response->json('error'))->pluck('key')->toArray());
    }

    // Phone Already Exists
    public function test_register_phone_already_exists()
    {
        $existing = User::factory()->create();

        $response = $this->postJson($this->registerUrl, [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => User::factory()->make()->email,
            'phone' => $existing->phone,
            'password' => 'password',
            'confirm_password' => 'password',
        ]);

        $response->assertStatus(406);
        $this->assertContains('phone', collect($response->json('error'))->pluck('key')->toArray());
    }

    // Register with FCM Token
    public function test_register_with_fcm_token()
    {
        $user = User::factory()->make();

        $response = $this->postJson($this->registerUrl, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'password',
            'confirm_password' => 'password',
            'fcm_token' => 'some-fcm-token',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'fcm_token' => 'some-fcm-token',
        ]);
    }

    // Assign Default Image
    public function test_register_assigns_default_image()
    {
        $user = User::factory()->make(['image_url' => null]);

        $response = $this->postJson($this->registerUrl, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'password',
            'confirm_password' => 'password',
        ]);

        $response->assertStatus(200);

        $createdUser = User::where('email', $user->email)->first();

        $this->assertNotNull($createdUser->image_url);
        $this->assertStringContainsString('def_user.png', $createdUser->image_url);
    }
}
