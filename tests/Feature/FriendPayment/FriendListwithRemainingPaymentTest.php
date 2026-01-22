<?php

namespace Tests\Feature\FriendPayment;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\GroupUser;
use App\Model\BillUser;
use App\Model\Group;
use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FriendListWithRemainingPaymentTest extends TestCase
{
    protected string $url = '/api/v2/friendlist/remainingpayments'; // Change to your real route

    protected function authenticate(): User
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        return $user;
    }

    public function test_authentication_required()
    {
        $response = $this->getJson($this->url);
        $response->assertStatus(401);
    }

    public function test_validation_fails_for_invalid_page_limit_filter()
    {
        $user = $this->authenticate();

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::page => 'invalid',
            Columns::limit => 200,
            Columns::filter => 5,
        ]));

        $response->assertStatus(406);

        $responseData = $response->json();

        $errors = collect($responseData['error']);

        $this->assertFalse($errors->contains(function ($e) {
            return $e['key'] === 'page' && str_contains($e['error'], 'The page must be at least 1.');
        }));

        $this->assertFalse($errors->contains(function ($e) {
            return $e['key'] === 'limit' && str_contains($e['error'], 'The limit must be at least 1.'); // your expected limit error message
        }));

        $this->assertTrue($errors->contains(function ($e) {
            return $e['key'] === 'filter' && str_contains($e['error'], 'The filter may not be greater than 3.'); // your expected filter error message
        }));
    }

    public function test_no_friends_found_returns_fail_response()
    {
        $user = $this->authenticate();

        // No GroupUser or BillUser entries created, so no friends

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::page => 1,
            Columns::limit => 5,
        ]));

        $response->assertStatus(400) // or 200 with error key false depends on your controller
            ->assertJson([
                Keys::STATUS => false,
                Keys::MESSAGE => 'Request Failed',
                Keys::ERROR => Messages::NO_DATA_FOUND
            ]);
    }

    public function test_returns_friends_with_remaining_payment_data()
    {
        $user = $this->authenticate();

        // Create friend user
        $friend = User::factory()->create();

        $group = Group::factory()->create(); // creates a valid group in DB
        $groupId = $group->id;
        GroupUser::factory()->createMany([
            [Columns::group_id => $groupId, Columns::user_id => $user->id],
            [Columns::group_id => $groupId, Columns::user_id => $friend->id],
        ]);

        // Create bill & bill splits with payment status pending
        $bill = Bill::factory()->create(
            [
                Columns::bill_create_date => null,
                Columns::group_id => $groupId,
            ]
        );
        BillUser::factory()->createMany([
            [Columns::bill_id => $bill->id, Columns::user_id => $user->id],
            [Columns::bill_id => $bill->id, Columns::user_id => $friend->id],
        ]);

        BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::paid_by => $user->id,
            Columns::borrow_by => $friend->id,
            Columns::amount => 1000,
            'payment_status_id' => 1,
        ]);

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::page => 1,
            Columns::limit => 5,
            Columns::filter => 0,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJsonStructure([
                Keys::DATA => [
                    '*' => [

                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'contact_name',
                        'difference_amount',
                        'status',
                    ],
                ],
                'current_page',
            ]);
        $data = $response->json(Keys::DATA);
        $matched = collect($data)->firstWhere('id', $friend->id);

        $this->assertNotNull($matched);
        $this->assertEquals(1000, $matched['difference_amount']);
        $this->assertEquals(Messages::YOU_WILL_RECIEVE, $matched['status']);
    }

    public function test_filter_applied_correctly()
    {
        $user = $this->authenticate();

        // Setup 3 friends with different payment states
        $friend1 = User::factory()->create();
        $friend2 = User::factory()->create();
        $friend3 = User::factory()->create();

        // Create a group
        $group = Group::factory()->create();
        $groupId = $group->id;
        GroupUser::factory()->createMany([
            [Columns::group_id => $groupId, Columns::user_id => $user->id],
            [Columns::group_id => $groupId, Columns::user_id => $friend1->id],
            [Columns::group_id => $groupId, Columns::user_id => $friend2->id],
            [Columns::group_id => $groupId, Columns::user_id => $friend3->id],
        ]);

        // Create a bill for that group
        $bill = Bill::factory()->create([
            Columns::group_id => $groupId,
            Columns::bill_create_date => null,
        ]);
        $billId = $bill->id;
        BillUser::factory()->createMany([
            [Columns::bill_id => $billId, Columns::user_id => $user->id],
            [Columns::bill_id => $billId, Columns::user_id => $friend1->id],
            [Columns::bill_id => $billId, Columns::user_id => $friend2->id],
            [Columns::bill_id => $billId, Columns::user_id => $friend3->id],
        ]);

        // friend1: userPaid > friendPaid (You will receive)
        BillSplit::factory()->create([
            Columns::bill_id => $billId,
            Columns::paid_by => $user->id,
            Columns::borrow_by => $friend1->id,
            Columns::amount => 1000,
            'payment_status_id' => 1,
        ]);

        // friend2: userPaid < friendPaid (You need to pay)
        BillSplit::factory()->create([
            Columns::bill_id => $billId,
            Columns::paid_by => $friend2->id,
            Columns::borrow_by => $user->id,
            Columns::amount => 500,
            'payment_status_id' => 1,
        ]);

        // friend3: settled (equal amounts)
        BillSplit::factory()->create([
            Columns::bill_id => $billId,
            Columns::paid_by => $user->id,
            Columns::borrow_by => $friend3->id,
            Columns::amount => 700,
            'payment_status_id' => 1,
        ]);
        BillSplit::factory()->create([
            Columns::bill_id => $billId,
            Columns::paid_by => $friend3->id,
            Columns::borrow_by => $user->id,
            Columns::amount => 700,
            'payment_status_id' => 1,
        ]);

        // filter=1 (Only "You will receive")
        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([Columns::filter => 1, Columns::page => 1, Columns::limit => 5]));
        $data = $response->json(Keys::DATA);
        $this->assertCount(1, $data);
        $this->assertEquals($friend1->id, $data[0][Columns::id]);

        // filter=2 (Only "You need to pay")
        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([Columns::filter => 2, Columns::page => 1, Columns::limit => 5]));
        $data = $response->json(Keys::DATA);
        $this->assertCount(1, $data);
        $this->assertEquals($friend2->id, $data[0][Columns::id]);

        // filter=3 (Only "Settled")
        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([Columns::filter => 3, Columns::page => 1, Columns::limit => 5]));
        $data = $response->json(Keys::DATA);
        $this->assertCount(1, $data);
        $this->assertEquals($friend3->id, $data[0][Columns::id]);
    }

    public function test_pagination_works()
    {
        $user = $this->authenticate();

        $group = Group::factory()->create();
        $groupId = $group->id;

        GroupUser::factory()->create([
            Columns::group_id => $groupId,
            Columns::user_id => $user->id,
        ]);

        // Create 10 friends connected by group
        $friends = User::factory()->count(10)->create();

        foreach ($friends as $friend) {
            GroupUser::factory()->create([
                Columns::group_id => $groupId,
                Columns::user_id => $friend->id,
            ]);

            BillUser::factory()->createMany([
                [Columns::bill_id => 1, Columns::user_id => $user->id],
                [Columns::bill_id => 1, Columns::user_id => $friend->id],
            ]);

            BillSplit::factory()->create([
                Columns::bill_id => 1,
                Columns::paid_by => $user->id,
                Columns::borrow_by => $friend->id,
                Columns::amount => 1000,
                'payment_status_id' => 1,
            ]);
        }

        $limit = 5;
        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::page => 2,
            Columns::limit => $limit,
        ]));

        $response->assertStatus(200);

        $data = $response->json(Keys::DATA);
        $this->assertCount($limit, $data);
        $this->assertEquals(2, $response->json('current_page'));
    }
}
