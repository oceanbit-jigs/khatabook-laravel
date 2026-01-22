<?php

namespace Tests\Feature\Auth;

use App\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserLoginTest extends TestCase
{
    protected string $loginUrl = '/api/user/login';

    /** @test */
    public function test_login_successful()
    {
        $password = 'secret123';

        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $response = $this->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'email'],
                'token',
                'message',
            ]);
    }

    /** @test */
    public function test_login_fails_with_invalid_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct_password'),
        ]);

        $response = $this->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'error' => 'Invalid UserId Password',
            ]);
    }

    /** @test */
    public function test_login_fails_with_non_existing_email()
    {
        $response = $this->postJson($this->loginUrl, [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'error' => 'Invalid UserId Password',
            ]);
    }

    /** @test */
    public function test_login_fails_with_missing_email()
    {
        $response = $this->postJson($this->loginUrl, [
            'password' => 'secret123',
        ]);

        $response->assertStatus(406)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
            ])
            ->assertJsonFragment([
                'key' => 'email',
                'error' => 'The email field is required.'
            ]);
    }

    /** @test */
    public function test_login_fails_with_missing_password()
    {
        $user = User::factory()->create();

        $response = $this->postJson($this->loginUrl, [
            'email' => $user->email,
        ]);

        $response->assertStatus(406)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
            ])
            ->assertJsonFragment([
                'key' => 'password',
                'error' => 'The password field is required.'
            ]);
    }

    /** @test */
    public function test_login_with_fcm_token_updates_user()
    {
        $password = 'secret123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $fcmToken = 'test-fcm-token-123';

        $response = $this->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => $password,
            'fcm_token' => $fcmToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'email'],
                'token',
                'message',
            ]);

        $this->assertEquals($fcmToken, $user->fresh()->fcm_token);
    }

    /** @test */
    public function test_login_response_contains_token()
    {
        $password = 'secret123';

        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $response = $this->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertArrayHasKey('token', $data, 'Token key is missing in response.');
        $this->assertNotEmpty($data['token'], 'Token is empty.');
    }
}
