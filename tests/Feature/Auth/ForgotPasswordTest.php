<?php

namespace Tests\Feature\Auth;

use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    protected string $forgotPasswordUrl = '/api/user/forgotPassword';

    protected function createTestUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    public function test_forgot_password_success()
    {
        $user = $this->createTestUser([
            'password' => bcrypt('OldPass123'),
        ]);

        $response = $this->postJson($this->forgotPasswordUrl, [
            'user_id' => $user->id,
            'new_password' => 'NewSecure123',
            'confirm_password' => 'NewSecure123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['id', 'first_name', 'last_name', 'email'],
            ]);

        $this->assertTrue(Hash::check('NewSecure123', $user->fresh()->password));
    }

    public function test_forgot_password_missing_required_fields()
    {
        $response = $this->postJson($this->forgotPasswordUrl, []);

        $response->assertStatus(406)
            ->assertJsonStructure([
                'status',
                'message',
                'error' => [['key', 'error']],
            ]);

        $errors = collect($response->json('error'))->pluck('key')->toArray();

        $this->assertContains('user_id', $errors);
        $this->assertContains('new_password', $errors);
        $this->assertContains('confirm_password', $errors);
    }

    public function test_forgot_password_user_id_does_not_exist()
    {
        $response = $this->postJson($this->forgotPasswordUrl, [
            'user_id' => 999999,
            'new_password' => 'NewSecure123',
            'confirm_password' => 'NewSecure123',
        ]);

        $response->assertStatus(406)
            ->assertJsonStructure([
                'status',
                'message',
                'error' => [['key', 'error']],
            ])
            ->assertJsonFragment([
                'key' => 'user_id',
                'error' => 'The selected user id is invalid.',
            ]);
    }

    public function test_forgot_password_passwords_do_not_match()
    {
        $user = $this->createTestUser();

        $response = $this->postJson($this->forgotPasswordUrl, [
            'user_id' => $user->id,
            'new_password' => 'NewSecure123',
            'confirm_password' => 'MismatchPass',
        ]);

        $response->assertStatus(406)
            ->assertJsonStructure([
                'status',
                'message',
                'error' => [['key', 'error']],
            ]);

        $errors = collect($response->json('error'))->pluck('key')->toArray();
        $this->assertContains('confirm_password', $errors);
    }

    public function test_forgot_password_password_too_short()
    {
        $user = $this->createTestUser();

        $response = $this->postJson($this->forgotPasswordUrl, [
            'user_id' => $user->id,
            'new_password' => '123',
            'confirm_password' => '123',
        ]);

        $response->assertStatus(406)
            ->assertJsonStructure([
                'status',
                'message',
                'error' => [['key', 'error']],
            ]);

        $errors = collect($response->json('error'))->pluck('key')->toArray();
        $this->assertContains('new_password', $errors);
    }
}
