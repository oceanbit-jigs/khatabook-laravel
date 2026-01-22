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

class DeleteGroupTest extends TestCase
{
    protected string $url = '/api/group/delete';
    protected User $user;

    protected function authenticate(): void
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_admin_can_delete_group_successfully()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create([
            Columns::created_by => $user->id,
        ]);

        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => true,
        ]);

        $response = $this->actingAs($user)->deleteJson($this->url, [
            Columns::group_id => $group->id,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => Messages::GROUP_DELETED_SUCCESSFULLY,
            ]);

        $this->assertDatabaseMissing(Tables::GROUPS, [
            Columns::id => $group->id,
        ]);
    }

    public function test_validation_fails_when_group_id_missing()
    {
        $this->authenticate();

        $response = $this->actingAs($this->user)->deleteJson($this->url, []);

        $response->assertStatus(406);
        $this->assertStringContainsString('group_id', $response->getContent());
    }

    public function test_delete_fails_if_group_not_found()
    {
        $this->authenticate();

        $response = $this->actingAs($this->user)->deleteJson($this->url, [
            Columns::group_id => 999999,
        ]);

        $response->assertStatus(406)
            ->assertJsonFragment([
                'key' => 'group_id',
                'error' => 'The selected group id is invalid.',
            ]);
    }

    public function test_non_admin_cannot_delete_group()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create();
        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => false,
        ]);

        $response = $this->actingAs($user)->deleteJson($this->url, [
            Columns::group_id => $group->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
                'error' => Messages::ALLOW_ONLY_GROUP_ADMIN,
            ]);

        $this->assertDatabaseHas(Tables::GROUPS, [
            Columns::id => $group->id,
        ]);
    }

    public function test_group_with_pending_bills_cannot_be_deleted()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create([
            Columns::created_by => $user->id,
        ]);

        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => true,
        ]);

        $bill = Bill::factory()->create([
            Columns::group_id => $group->id, 
            Columns::bill_create_date => null,
        ]);

        BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::payment_status_id => 1, // Pending
        ]);

        $response = $this->actingAs($user)->deleteJson($this->url, [
            Columns::group_id => $group->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
                'error' => Messages::GROUP_HAS_PENDING_BILLS,
            ]);

        $this->assertDatabaseHas(Tables::GROUPS, [
            Columns::id => $group->id,
        ]);
    }
}
