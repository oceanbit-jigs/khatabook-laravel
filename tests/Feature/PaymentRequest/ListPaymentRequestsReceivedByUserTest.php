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

class ListPaymentRequestsReceivedByUserTest extends TestCase
{
    protected string $url = '/api/payment/request/receiverlist';

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

    public function test_returns_no_data_if_user_has_no_received_requests()
    {
        $user = $this->authenticate();

        $response = $this->actingAs($user)->getJson($this->url);

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
                Keys::DATA => [],
            ]);
    }

    public function test_returns_paginated_received_requests()
    {
        $toUser = $this->authenticate();
        $fromUser = User::factory()->create();
        $bill = Bill::factory()->create([
            Columns::bill_create_date => null,
        ]);

        $billSplit = BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::borrow_by => $fromUser->id,
            Columns::paid_by => $toUser->id,
        ]);

        PaymentRequest::factory()->count(3)->create([
            Columns::from_user_id => $fromUser->id,
            Columns::to_user_id => $toUser->id,
            Columns::bill_split_id => $billSplit->id,
        ]);

        $limit = 2;

        $response = $this->actingAs($toUser)->getJson($this->url . '?' . http_build_query([
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
                        Columns::from_user_id,
                        Columns::created_at,
                        Columns::updated_at,
                        // other fields...
                    ]
                ]
            ]);
    }

    public function test_returns_complete_structure_of_received_request()
    {
        $toUser = $this->authenticate();
        $fromUser = User::factory()->create();

        $bill = Bill::factory()->create([
            Columns::bill_create_date => null,
        ]);

        $billSplit = BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::borrow_by => $fromUser->id,
            Columns::paid_by => $toUser->id,
        ]);

        $paymentRequests = PaymentRequest::factory()->count(2)->create([
            Columns::from_user_id => $fromUser->id,
            Columns::to_user_id => $toUser->id,
            Columns::bill_split_id => $billSplit->id,
        ]);

        $response = $this->actingAs($toUser)->getJson($this->url . '?' . http_build_query([
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
                        Columns::from_user_id => [
                            Columns::id,
                            Columns::first_name,
                            Columns::last_name,
                            Columns::email,
                            Columns::phone,
                            Columns::contact_name,
                            Columns::image_url,
                        ],
                        Columns::to_user_id,
                        Columns::bill_splits,
                        Columns::created_at,
                        Columns::updated_at,
                    ]
                ]
            ]);

        // Verify actual data
        $firstRequest = $paymentRequests->first();
        $jsonData = $response->json(Keys::DATA);
        $matched = collect($jsonData)->firstWhere(Columns::id, $firstRequest->id);

        $this->assertEquals($firstRequest->amount, $matched[Columns::amount]);
        $this->assertEquals($fromUser->id, $matched[Columns::from_user_id][Columns::id]);
    }
}
