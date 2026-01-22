<?php

namespace Tests\Feature\Bill;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\MasterBillType;
use App\Model\MasterPaymentStatus;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HostedBillListTest extends TestCase
{
    protected string $url = '/api/v2/bill/hostedby';
    protected User $user;
    protected function authenticate()
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_validation_fails_for_invalid_input()
    {
        $this->authenticate();

        $response = $this->getJson($this->url . '?page=abc&limit=200');

        $response->assertStatus(406)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
            ])
            ->assertJsonStructure(['error']);
    }

    public function test_returns_400_if_no_hosted_bills_found()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $response = $this->getJson($this->url);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
                'error' =>  Messages::NO_DATA_FOUND,
            ]);
    }

    public function test_paginated_results_are_returned_correctly()
    {
        $this->authenticate();
        $user = $this->user;

        Bill::factory()->count(20)->create([
            'paid_by' => $user->id,
            Columns::bill_create_date => null
        ]);

        $response = $this->getJson($this->url . '?page=1&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'current_page',
                'data' => [
                    '*' => [
                        Columns::id,
                        Columns::title,
                        Columns::total_amount,
                        Columns::total_users,
                        Columns::status,
                        Columns::total_pending_count,
                    ]
                ],
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]);
    }

    public function test_status_shows_correct_based_on_paid_status()
{
    $this->authenticate();
    $user = $this->user;

    $friend1 = User::factory()->create(); // pending
    $friend2 = User::factory()->create(); // paid (same as paid_by)

    $billPayload = [
        Columns::group_id => 1,
        Columns::bill_type_id => 1,
        Columns::created_by => $user->id,
        Columns::paid_by => $user->id,
        Columns::is_split_equally => false,
        Columns::title => 'Test Bill',
        Columns::amount => 300,
        Columns::bill_create_date => null,
        'friends' => [
            [Columns::user_id => $friend1->id, Columns::amount => 100],
            [Columns::user_id => $friend2->id, Columns::amount => 100],
            [Columns::user_id => $user->id, Columns::amount => 100], // paid by himself
        ],
    ];

    $response = $this->postJson('/api/bill/add', $billPayload);
    $response->assertStatus(200);
    
    $json = $response->json();
    $splits = $json['data'][Columns::bill_splits];
    
    // Find the status of the split where borrow_by == $friend1 (should be Pending)
    $status = collect($splits)->firstWhere(Columns::borrow_by, $friend1->id)['status'];
    $this->assertEquals('Pending', $status);
}

    public function test_excludes_payment_transaction_type_bills()
    {
        $this->authenticate();
        $user = $this->user;

        $excludedType = MasterBillType::where('title', 'payment_transaction')->value(Columns::id);
        $includedType = MasterBillType::where(Columns::id, 1)->value(Columns::id);

        // Create bill that should be excluded
        Bill::factory()->create([
            'paid_by' => $user->id,
            'bill_type_id' => $excludedType,
            Columns::bill_create_date => null, // or correct date format if needed
        ]);

        // Create bill that should be included
        Bill::factory()->create([
            'paid_by' => $user->id,
            'bill_type_id' => $includedType,
            Columns::bill_create_date => null, // or correct date format
        ]);

        $response = $this->getJson($this->url);
        $json = $response->json();

        $response->assertStatus(200);
        $this->assertCount(1, $json['data']); // Only 1 (non-excluded) bill shown
    }
}
