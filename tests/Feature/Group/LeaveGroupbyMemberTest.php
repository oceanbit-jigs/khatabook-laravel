<?php

namespace Tests\Feature\Group;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\Group;
use App\Model\GroupUser;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LeaveGroupByMemberTest extends TestCase
{
    protected string $url = '/api/group/user/leave';

    protected function authenticateUser(): User
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        return $user;
    }

    public function test_user_can_leave_group_successfully()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => Messages::RECORD_DELETED_SUCCESSFULLY,
            ]);

        $this->assertDatabaseMissing('group_users', [
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_leave_group_with_pending_or_declined_transactions()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $bill = Bill::factory()->create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            Columns::bill_create_date => null
        ]);

        BillSplit::create([
            'bill_id' => $bill->id,
            'borrow_by' => $user->id,
            'paid_by' => $user->id,
            'payment_status_id' => 1, // Pending
            'amount' => 100
        ]);

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => Messages::USER_HAS_PENDING_TRANSACTIONS,
            ]);

        $this->assertDatabaseHas('group_users', [
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_leave_group_if_not_a_member()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => Messages::USER_DOES_NOT_EXISTS,
            ]);
    }

    public function test_validation_fails_when_group_id_is_missing()
    {
        $this->authenticateUser();

        $response = $this->deleteJson($this->url, []);

        $response->assertStatus(422); // Laravel validation failure
    }

    public function test_validation_fails_when_group_id_is_invalid()
    {
        $this->authenticateUser();

        $response = $this->deleteJson($this->url, [
            'group_id' => 9999, // Non-existing group
        ]);

        $response->assertStatus(status: 422);
    }

    public function test_unauthenticated_user_cannot_leave_group()
    {
        $group = Group::factory()->create();

        $response = $this->deleteJson($this->url, [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(401); // Unauthorized
    }
}
