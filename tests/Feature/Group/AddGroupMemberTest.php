<?php

namespace Tests\Feature\Group;

use App\Constants\Messages;
use App\Model\Group;
use App\Model\GroupUser;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AddGroupMemberTest extends TestCase
{
    protected string $url = '/api/group/user/add';
    protected User $user;

    protected function authenticate()
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_admin_can_add_users_to_group()
    {
        $this->authenticate();
        $admin = $this->user;

        $group = Group::factory()->create(['created_by' => $admin->id]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $admin->id, 'is_admin' => true]);

        $users = User::factory()->count(2)->create();

        $payload = [
            'group_id' => $group->id,
            'user_list' => $users->map(fn($u) => ['user_id' => $u->id])->toArray(),
        ];

        $response = $this->postJson($this->url, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => Messages::ADDED_SUCCESSFULLY,
            ]);

        foreach ($users as $user) {
            $this->assertDatabaseHas('group_users', [
                'group_id' => $group->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function test_validation_fails_if_group_id_is_missing()
    {
        $this->authenticate();
        $response = $this->postJson($this->url, [
            'user_list' => [['user_id' => 1]],
        ]);
        $response->assertStatus(406);
    }

    public function test_validation_fails_if_user_list_is_missing()
    {
        $this->authenticate();
        $group = Group::factory()->create();
        $response = $this->postJson($this->url, ['group_id' => $group->id]);
        $response->assertStatus(406);
    }

    public function test_validation_fails_if_user_list_is_not_array()
    {
        $this->authenticate();
        $group = Group::factory()->create();
        $response = $this->postJson($this->url, [
            'group_id' => $group->id,
            'user_list' => 'not_an_array',
        ]);
        $response->assertStatus(406);
    }

    public function test_validation_fails_if_user_list_item_has_no_user_id()
    {
        $this->authenticate();
        $group = Group::factory()->create();
        $response = $this->postJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [[]],
        ]);
        $response->assertStatus(406);
    }

    public function test_validation_fails_if_group_id_is_invalid()
    {
        $this->authenticate();
        $response = $this->postJson($this->url, [
            'group_id' => 999999,
            'user_list' => [['user_id' => 1]],
        ]);
        $response->assertStatus(406);
    }

    public function test_validation_fails_if_user_id_in_user_list_does_not_exist()
    {
        $this->authenticate();
        $group = Group::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $this->user->id, 'is_admin' => true]);

        $response = $this->postJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [['user_id' => 999999]],
        ]);
        $response->assertStatus(406);
    }

    public function test_non_admin_cannot_add_users_to_group()
    {
        $this->authenticate();
        $group = Group::factory()->create();

        $userToAdd = User::factory()->create();

        $response = $this->postJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $userToAdd->id]],
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => Messages::ALLOW_ONLY_GROUP_ADMIN
            ]);
    }

    public function test_already_existing_user_in_group_is_not_added_again()
    {
        $this->authenticate();
        $group = Group::factory()->create();
        $admin = $this->user;
        $user = User::factory()->create();

        GroupUser::create(['group_id' => $group->id, 'user_id' => $admin->id, 'is_admin' => true]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'is_admin' => false]);

        $response = $this->postJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $user->id]],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => "Request Success",
            ]);
    }

    public function test_unauthenticated_user_cannot_add_users()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        $response = $this->postJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $user->id]],
        ]);

        $response->assertStatus(401);
    }
}
