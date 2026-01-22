<?php

namespace Tests\Feature\Group;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Constants\Tables;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\Group;
use App\Model\GroupUser;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RemoveGroupMemberTest extends TestCase
{
    protected string $url = '/api/group/user/remove';
    protected User $admin;

    protected function authenticateAsAdmin(Group $group = null): Group
    {
        $this->admin = User::factory()->create();
        Passport::actingAs($this->admin);

        $group ??= Group::factory()->create(['created_by' => $this->admin->id]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $this->admin->id,
            'is_admin' => true,
        ]);

        return $group;
    }

    public function test_admin_can_remove_group_user()
    {
        $group = $this->authenticateAsAdmin();

        $user = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $payload = [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $user->id]],
        ];

        $response = $this->deleteJson($this->url, $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => Messages::RECORD_DELETED_SUCCESSFULLY,
            ]);

        $this->assertDatabaseMissing('group_users', [
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_cannot_remove_user_with_pending_transactions()
    {
        $group = $this->authenticateAsAdmin();
        $user = User::factory()->create();

        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $bill = Bill::factory()->create([
            'group_id' => $group->id,
            'created_by' => $this->admin->id,
            Columns::bill_create_date => null
        ]);

        BillSplit::create([
            'bill_id' => $bill->id,
            'borrow_by' => $user->id,
            'paid_by' => $this->admin->id,
            'payment_status_id' => 1,
            'amount' => 100
        ]);

        $payload = [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $user->id]],
        ];

        $response = $this->deleteJson($this->url, $payload);
        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => [[
                    'user_id' => $user->id,
                    'error' => Messages::USER_HAS_PENDING_TRANSACTIONS,
                ]]
            ]);

        $this->assertDatabaseHas('group_users', [
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_cannot_remove_user_not_in_group()
    {
        $group = $this->authenticateAsAdmin();
        $user = User::factory()->create();

        $payload = [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $user->id]],
        ];

        $response = $this->deleteJson($this->url, $payload);
        $response->assertStatus(400)
            ->assertJsonFragment([
                'user_id' => $user->id,
                'error' => Messages::USER_DOES_NOT_EXISTS,
            ]);
    }

    public function test_non_admin_cannot_remove_users()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $group = Group::factory()->create();
        $anotherUser = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $anotherUser->id]);

        $payload = [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $anotherUser->id]],
        ];

        $response = $this->deleteJson($this->url, $payload);
        $response->assertStatus(400)
            ->assertJsonFragment(['error' => Messages::ALLOW_ONLY_GROUP_ADMIN]);
    }

    public function test_validation_fails_for_missing_group_id()
    {
        Passport::actingAs(User::factory()->create());

        $response = $this->deleteJson($this->url, [
            'user_list' => [['user_id' => 1]],
        ]);

        $response->assertStatus(406);
    }

    public function test_validation_fails_for_missing_user_list()
    {
        $group = $this->authenticateAsAdmin();

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(406);
    }

    public function test_validation_fails_if_user_list_is_not_array()
    {
        $group = $this->authenticateAsAdmin();

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
            'user_list' => 'invalid',
        ]);

        $response->assertStatus(406);
    }

    public function test_validation_fails_if_user_id_not_present_in_user_list()
    {
        $group = $this->authenticateAsAdmin();

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [[]],
        ]);

        $response->assertStatus(406);
    }

    public function test_unauthenticated_user_cannot_remove_user()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
            'user_list' => [['user_id' => $user->id]],
        ]);

        $response->assertStatus(401);
    }
}
