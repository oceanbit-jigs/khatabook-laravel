<?php

namespace Tests\Feature\BillList;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\BillUser;
use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BillListHistoryFriendTest extends TestCase
{
    protected string $url = '/api/v2/billList/history/friend';

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

    public function test_validation_fails_when_missing_id()
    {
        $user = $this->authenticate();

        $response = $this->actingAs($user)->getJson($this->url);
        $response->assertStatus(406);

        $errors = collect($response->json('error'));
        $this->assertTrue($errors->contains(fn($e) => $e['key'] === 'id'));
    }

    public function test_validation_fails_for_invalid_id_page_limit()
    {
        $user = $this->authenticate();

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::id => 'notanint',
            Columns::page => 'invalid',
            Columns::limit => 200,
        ]));

        $response->assertStatus(406);

        $errors = collect($response->json('error'));

        $this->assertTrue($errors->contains(fn($e) => $e['key'] === Columns::id));
        $this->assertTrue($errors->contains(fn($e) => $e['key'] === Columns::page));
        $this->assertTrue($errors->contains(fn($e) => $e['key'] === Columns::limit));
    }


    public function test_no_bills_found_returns_fail_response()
    {
        $user = $this->authenticate();

        $friend = User::factory()->create();

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::id => $friend->id,
            Columns::page => 1,
            Columns::limit => 10,
        ]));

        $response->assertStatus(400); // Assuming your controller returns 400 on no data
        $response->assertJson([
            Keys::STATUS => false,
            Keys::ERROR => Messages::NO_DATA_FOUND,
        ]);
    }

    public function test_returns_bills_with_friend_data_paginated()
    {
        $user = $this->authenticate();
        $friend = User::factory()->create();

        // Create bill and bill splits where user and friend are payer and borrower alternatively
        $bill1 = Bill::factory()->create([
            Columns::bill_create_date => now()->subDays(5)->toDateString(),
        ]);
        BillUser::factory()->createMany([
            [Columns::bill_id => $bill1->id, Columns::user_id => $user->id],
            [Columns::bill_id => $bill1->id, Columns::user_id => $friend->id],
        ]);
        BillSplit::factory()->create([
            Columns::bill_id => $bill1->id,
            Columns::paid_by => $user->id,
            Columns::borrow_by => $friend->id,
            Columns::amount => 1000,
            'payment_status_id' => 1,
        ]);

        $bill2 = Bill::factory()->create([
            Columns::bill_create_date => now()->subDays(3)->toDateString(),
        ]);
        BillUser::factory()->createMany([
            [Columns::bill_id => $bill2->id, Columns::user_id => $user->id],
            [Columns::bill_id => $bill2->id, Columns::user_id => $friend->id],
        ]);
        BillSplit::factory()->create([
            Columns::bill_id => $bill2->id,
            Columns::paid_by => $friend->id,
            Columns::borrow_by => $user->id,
            Columns::amount => 500,
            'payment_status_id' => 1,
        ]);

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::id => $friend->id,
            Columns::page => 1,
            Columns::limit => 10,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJsonStructure([
                Keys::DATA => [
                    '*' => [
                        Columns::bill_id,
                        Columns::title,
                        Columns::paid_by => [
                            Columns::id,
                            Columns::first_name,
                            Columns::last_name,
                            Columns::email,
                            Columns::contact_name,
                            Columns::phone,
                            Columns::image_url,
                        ],
                        Columns::bill_create_date,
                        Columns::created_at,
                        Columns::total_amount,
                        Columns::amount,
                        Columns::status,
                    ],
                ],
                'current_page',
                'per_page',
                'total',
                'first_page_url',
                'last_page_url',
                'next_page_url',
                'prev_page_url',
                'from',
                'to',
                'last_page',
                'path',
                'links',
            ]);

        $data = $response->json(Keys::DATA);

        $this->assertCount(2, $data);
        $this->assertEquals($bill2->id, $data[0][Columns::bill_id]); // newest bill first
        $this->assertEquals($bill1->id, $data[1][Columns::bill_id]);
    }

    public function test_returns_all_bills_without_pagination_when_page_is_0()
    {
        $user = $this->authenticate();
        $friend = User::factory()->create();

        // Create 3 bills with bill splits
        foreach (range(1, 3) as $i) {
            $bill = Bill::factory()->create(
                [
                    Columns::bill_create_date => null
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
                Columns::amount => 1000 * $i,
                'payment_status_id' => 1,
            ]);
        }

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::id => $friend->id,
            Columns::page => 0,
        ]));

        $response->assertStatus(200);
        $data = $response->json(Keys::DATA);
        $this->assertCount(3, $data);
    }

    public function test_amount_and_status_calculation_logic()
    {
        $user = $this->authenticate();
        $friend = User::factory()->create();

        $bill = Bill::factory()->create([
            Columns::bill_create_date => null,
        ]);

        BillUser::factory()->createMany([
            [Columns::bill_id => $bill->id, Columns::user_id => $user->id],
            [Columns::bill_id => $bill->id, Columns::user_id => $friend->id],
        ]);

        // Friend is payer, user is borrower, split is pending
        BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::paid_by => $friend->id,
            Columns::borrow_by => $user->id,
            Columns::amount => 1500,
            'payment_status_id' => 1, // Pending
        ]);

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::id => $friend->id,
            Columns::page => 1,
        ]));

        $response->assertStatus(200);

        $data = $response->json(Keys::DATA)[0];
        $this->assertEquals(1500, $data[Columns::amount]);
        $this->assertEquals(Messages::PENDING, $data[Columns::status]);
    }


    public function test_status_when_user_is_not_payer()
    {
        $user = $this->authenticate();
        $friend = User::factory()->create();

        $bill = Bill::factory()->create(
            [
                Columns::bill_create_date => null
            ]
        );

        BillUser::factory()->createMany([
            [Columns::bill_id => $bill->id, Columns::user_id => $user->id],
            [Columns::bill_id => $bill->id, Columns::user_id => $friend->id],
        ]);

        // Friend is payer, user is borrower, individual payment status pending
        BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::paid_by => $friend->id,
            Columns::borrow_by => $user->id,
            Columns::amount => 700,
            'payment_status_id' => 1,
        ]);

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::id => $friend->id,
            Columns::page => 1,
        ]));

        $response->assertStatus(200);

        $data = $response->json(Keys::DATA)[0];

        $this->assertEquals(Messages::PENDING, $data[Columns::status]);
    }
}
