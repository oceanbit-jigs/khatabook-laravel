<?php

namespace Tests\Feature\Group;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\Group;
use App\Model\GroupUser;
use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ListofGroupTest extends TestCase
{
    protected string $url = '/api/group/user/listofGroup';

    protected function authenticateUser(): User
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        return $user;
    }

    public function test_user_can_fetch_group_list_successfully()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $bill = Bill::factory()->create([
            'group_id' => $group->id,
            Columns::bill_create_date => null
        ]);
        BillSplit::create([
            'bill_id' => $bill->id,
            'paid_by' => $user->id,
            'borrow_by' => $user->id,
            'amount' => 100,
            'payment_status_id' => 2, // Paid
        ]);

        $response = $this->getJson($this->url . '?user_id=' . $user->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [['id', 'name', 'bill_status', 'total_bill_count']],
                'message',
            ])
            ->assertJsonFragment([
                'message' => Messages::RECORD_FOUND_SUCCESSFULLY,
            ]);
    }

    public function test_validation_fails_when_user_id_missing()
    {
        $this->authenticateUser();

        $response = $this->getJson($this->url);

        $response->assertStatus(406)
            ->assertJsonFragment([
                'key' => Columns::user_id,
                'error' => 'The user id field is required.',
            ]);
    }

    public function test_validation_fails_when_user_id_invalid()
    {
        $this->authenticateUser();

        $response = $this->getJson($this->url . '?user_id=999999');

        $response->assertStatus(406)
            ->assertJsonFragment([
                'key' => Columns::user_id,
                'error' => 'The selected user id is invalid.',
            ]);
    }

    public function test_returns_empty_list_if_user_has_no_groups()
    {
        $user = $this->authenticateUser();

        $response = $this->getJson($this->url . '?user_id=' . $user->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJsonCount(0, 'data');
    }

    public function test_pagination_works_correctly()
    {
        $user = $this->authenticateUser();

        Group::factory()->count(5)->create()->each(function ($group) use ($user) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);
        });

        $response = $this->getJson($this->url . '?user_id=' . $user->id . '&page=1&limit=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    public function test_bill_status_is_pending_if_any_split_pending_or_declined()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $bill = Bill::factory()->create(['group_id' => $group->id]);
        BillSplit::create([
            'bill_id' => $bill->id,
            'paid_by' => $user->id,
            'borrow_by' => $user->id,
            'amount' => 50,
            'payment_status_id' => 1, // Pending
        ]);

        $response = $this->getJson($this->url . '?user_id=' . $user->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.bill_status', Messages::PENDING);
    }

    public function test_group_status_paid_if_all_splits_paid()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $bill = Bill::factory()->create([
            'group_id' => $group->id,
            Columns::bill_create_date => null
        ]);
        BillSplit::create([
            'bill_id' => $bill->id,
            'paid_by' => $user->id,
            'borrow_by' => $user->id,
            'amount' => 100,
            'payment_status_id' => 2, 
        ]);

        $response = $this->getJson($this->url . '?user_id=' . $user->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.bill_status', Messages::PAID);
    }

    public function test_bill_amount_replaced_with_pending_declined_total()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $bill = Bill::factory()->create(['group_id' => $group->id]);

        BillSplit::insert([
            [
                'bill_id' => $bill->id,
                'paid_by' => $user->id,
                'borrow_by' => $user->id,
                'amount' => 100,
                'payment_status_id' => 1, // Pending
            ],
            [
                'bill_id' => $bill->id,
                'paid_by' => $user->id,
                'borrow_by' => $user->id,
                'amount' => 200,
                'payment_status_id' => 2, // Paid
            ],
        ]);

        $response = $this->getJson($this->url . '?user_id=' . $user->id);
        $responseData = $response->json('data')[0]['bills'][0]['amount'];

        $this->assertEquals(100, $responseData); // Only pending/declined amount should be returned
    }

    public function test_unauthenticated_user_cannot_access()
    {
        $user = User::factory()->create();

        $response = $this->getJson($this->url . '?user_id=' . $user->id);

        $response->assertStatus(401);
    }
}
