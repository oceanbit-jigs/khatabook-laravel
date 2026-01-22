<?php

namespace Tests\Feature\PaymentRequest;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\PaymentRequest;
use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ListPaymentRequestsSentByUserTest extends TestCase
{
    protected string $url = '/api/payment/request/senderlist';

    protected function authenticate(): User
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        return $user;
    }

    public function test_validation_fails_for_invalid_page_and_limit()
    {
        $user = $this->authenticate();

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::page => 'invalid',
            Columns::limit => 200,
        ]));

        $response->assertStatus(406)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
            ])
            ->assertJsonFragment(['key' => Columns::page])
            ->assertJsonFragment(['key' => Columns::limit]);
    }

    public function test_returns_no_data_if_user_has_no_sent_requests()
    {
        $user = $this->authenticate();

        $response = $this->actingAs($user)->getJson($this->url);

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJson([
                Keys::DATA => [],
            ]);
    }

    public function test_returns_paginated_sent_requests()
    {
        $user = $this->authenticate();
        $toUser = User::factory()->create();
        $bill = Bill::factory()->create(
            [
                Columns::bill_create_date => null
            ]
        );
        // Ensure bill split has both required foreign key references
        $billSplit = BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::borrow_by => $user->id,      // from_user_id must match this
            Columns::paid_by => $toUser->id,      // to_user_id must match this
        ]);

        // Now safely create payment requests
        PaymentRequest::factory()->count(3)->create([
            Columns::from_user_id => $user->id,
            Columns::to_user_id => $toUser->id,
            Columns::bill_split_id => $billSplit->id,
        ]);

        $limit = 5;

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::page => 1,
            Columns::limit => $limit,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'current_page',
                'per_page',
                'total',
                'data' => [
                    '*' => [
                        Columns::id,
                        Columns::amount,
                        Columns::status,
                        Columns::to_user_id,
                        Columns::created_at,
                        Columns::updated_at,
                        // You may include other expected fields
                    ]
                ]
            ]);
    }

    public function test_returns_complete_structure_of_sent_request()
{
    $user = $this->authenticate();
    $toUser = User::factory()->create();

    $bill = Bill::factory()->create([
        Columns::bill_create_date => null,
    ]);

    $billSplit = BillSplit::factory()->create([
        Columns::bill_id => $bill->id,
        Columns::borrow_by => $user->id,
        Columns::paid_by => $toUser->id,
    ]);

    // Create 3 payment requests
    $paymentRequests = PaymentRequest::factory()->count(3)->create([
        Columns::from_user_id => $user->id,
        Columns::to_user_id => $toUser->id,
        Columns::bill_split_id => $billSplit->id,
    ]);

    $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
        Columns::page => 1,
    ]));

    $response->assertStatus(200)
        ->assertJson([
            Keys::STATUS => true,
            Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
        ])
        ->assertJsonStructure([
            Keys::DATA => [
                '*' => [
                    Columns::id,
                    Columns::title,
                    Columns::amount,
                    Columns::status,
                    Columns::to_user_id => [
                        Columns::id,
                        Columns::first_name,
                        Columns::last_name,
                        Columns::email,
                        Columns::phone,
                        Columns::contact_name,
                        Columns::image_url,
                    ],
                    Columns::from_user_id,
                    Columns::bill_splits,
                    Columns::created_at,
                    Columns::updated_at,
                ]
            ]
        ]);  

    // Check data of the first created payment request
    $firstRequest = $paymentRequests->first();
    $jsonData = $response->json(Keys::DATA);

    $matched = collect($jsonData)->firstWhere(Columns::id, $firstRequest->id);

    $this->assertEquals($firstRequest->amount, $matched[Columns::amount]);
    $this->assertEquals($toUser->id, $matched[Columns::to_user_id][Columns::id]);
}

}
