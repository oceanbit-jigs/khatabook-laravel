<?php

namespace Tests\Feature\Group;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\Group;
use App\Model\GroupUser;
use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ListofUserTest extends TestCase
{
    protected string $url = '/api/group/user/listofUser';

    protected function authenticateUser(): User
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        return $user;
    }

    public function test_user_can_fetch_group_users_with_balances()
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

        $response = $this->getJson($this->url . '?group_id=' . $group->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [[
                    Columns::user_id,
                    Columns::name,
                    Columns::email,
                    Columns::phone,
                    Columns::image_url,
                    Columns::contact_name,
                    Columns::total_received_amount,
                    Columns::total_paid_amount,
                    Columns::total_remaining_amount,
                    Columns::status,
                ]],
            ]);
    }

    public function test_returns_no_data_found_when_group_has_no_users()
    {
        $this->authenticateUser();
        $group = Group::factory()->create();

        $response = $this->getJson($this->url . '?group_id=' . $group->id);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => Messages::NO_DATA_FOUND,
            ]);
    }

    public function test_validation_fails_when_group_id_missing()
    {
        $this->authenticateUser();

        $response = $this->getJson($this->url, []);

        $response->assertStatus(406)
            ->assertJsonFragment([
                'key' => Columns::group_id,
                'error' => 'The group id field is required.',
            ]);
    }

    public function test_validation_fails_when_group_id_invalid()
    {
        $this->authenticateUser();

        $response = $this->getJson($this->url . '?group_id=' . 9999);

        $response->assertStatus(406)
            ->assertJsonFragment([
                'key' => Columns::group_id,
                'error' => 'The selected group id is invalid.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_endpoint()
    {
        $group = Group::factory()->create();

        $response = $this->getJson($this->url . '?group_id=' . $group->id);

        $response->assertStatus(401);
    }

    public function test_balance_calculation_for_multiple_users()
{
    // Step 1: Authenticate user1 and create user2
    $user1 = $this->authenticateUser(); // Also sets $this->user
    $user2 = User::factory()->create();

    // Step 2: Create group and add both users
    $group = Group::factory()->create();
    GroupUser::insert([
        ['group_id' => $group->id, 'user_id' => $user1->id],
        ['group_id' => $group->id, 'user_id' => $user2->id],
    ]);

    // Step 3: Create a bill with user1 paying and user2 borrowing
    $bill = Bill::factory()->create([
        'group_id' => $group->id,
        Columns::bill_create_date => null,
    ]);

    BillSplit::create([
        'bill_id' => $bill->id,
        'paid_by' => $user1->id,
        'borrow_by' => $user2->id,
        'amount' => 200,
        'payment_status_id' => 1,
    ]);

    // Step 4: Hit the API
    $response = $this->getJson($this->url . '?group_id=' . $group->id);

    $response->assertStatus(200);

    // Step 5: Validate response structure
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            '*' => [
                Columns::user_id,
                Columns::name,
                Columns::email,
                Columns::phone,
                Columns::image_url,
                Columns::contact_name,
                Columns::total_received_amount,
                Columns::total_paid_amount,
                Columns::total_remaining_amount,
                Columns::status,
            ]
        ]
    ]);

    // Step 6: Extract response data for dynamic assertions
    $responseData = $response->json('data');

    $user1Data = collect($responseData)->firstWhere(Columns::user_id, $user1->id);
    $user2Data = collect($responseData)->firstWhere(Columns::user_id, $user2->id);

    $this->assertNotNull($user1Data, 'User1 data not found in response');
    $this->assertNotNull($user2Data, 'User2 data not found in response');

    $this->assertEquals( 200,$user1Data[Columns::total_remaining_amount]);
    $this->assertEquals(Messages::YOU_WILL_RECIEVE, $user1Data[Columns::status]);

    $this->assertEquals(-200, $user2Data[Columns::total_remaining_amount]);
    $this->assertEquals(Messages::YOU_NEED_TO_PAY, $user2Data[Columns::status]);
}


    public function test_status_is_settled_if_no_transactions_exist()
    {
        $user = $this->authenticateUser();
        $group = Group::factory()->create();

        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);

        $response = $this->getJson($this->url . '?group_id=' . $group->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                Columns::user_id => $user->id,
                Columns::status => Messages::SETTLED,
            ]);
    }
}
