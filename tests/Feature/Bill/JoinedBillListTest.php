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
use Laravel\Passport\Passport;
use Tests\TestCase;

class JoinedBillListTest extends TestCase
{
    protected string $url = '/api/v2/bill/byfriend';

    protected User $user;

    protected function authenticate()
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_validation_fails_for_invalid_page_and_limit()
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

    public function test_returns_400_if_no_joined_bills_found()
    {
        $this->authenticate();

        $response = $this->getJson($this->url);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
                'error' => Messages::NO_DATA_FOUND,
            ]);
    }

    public function test_excludes_payment_transaction_type_bills()
    {
        $this->authenticate();
        $user = $this->user;

        $excludedTypeId = MasterBillType::where('title', 'payment_transaction')->value(Columns::id);
        $includedTypeId = MasterBillType::where('title', 'default')->value(Columns::id) ?? 1; // fallback

        // Bill with excluded type - should NOT appear
        Bill::factory()->create([
            'paid_by' => 2,
            'bill_type_id' => $excludedTypeId,
            Columns::bill_create_date => null,
        ])->billSplits()->create([
            Columns::borrow_by => $user->id,
            Columns::payment_status_id => MasterPaymentStatus::where('title', 'Pending')->value(Columns::id),
            'paid_by' => 2,
            'amount' => 100,
        ]);

        // Bill with included type - SHOULD appear
        Bill::factory()->create([
            'paid_by' => 2,
            'bill_type_id' => $includedTypeId,
            Columns::bill_create_date => null,
        ])->billSplits()->create([
            Columns::borrow_by => $user->id,
            Columns::payment_status_id => MasterPaymentStatus::where('title', 'Pending')->value(Columns::id),
            'paid_by' => 2,
            'amount' => 100
        ]);

        $response = $this->getJson($this->url);

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertCount(1, $json['data']);
    }

    public function test_pagination_returns_correct_structure()
    {
        $this->authenticate();
        $user = $this->user;

        // Create a bill that user has joined (is a borrower)
        $bill = Bill::factory()->create([
            'paid_by' => 2,  // someone else paid
            'bill_type_id' => MasterBillType::first()->id ?? 1,
            Columns::bill_create_date => now(),
        ]);

        $bill->billSplits()->create([
            Columns::borrow_by => $user->id, // user is borrower
            Columns::payment_status_id => MasterPaymentStatus::where('title', 'Pending')->value(Columns::id),
            'paid_by' => 2,
            'amount' => 100,
        ]);

        $response = $this->getJson($this->url . '?page=1&limit=10');

        $response->assertStatus(200);

        $response->assertJsonStructure([
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
                    Columns::paid_by_user,
                    Columns::created_by_user,
                    Columns::bill_create_date,
                    Columns::created_at,
                ],
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

    public function test_status_reflects_paid_and_pending_correctly()
    {
        $this->authenticate();
        $user = $this->user;

        $paidStatusId = MasterPaymentStatus::where('title', 'Paid')->value(Columns::id);
        $pendingStatusId = MasterPaymentStatus::where('title', 'Pending')->value(Columns::id);

        $bill = Bill::factory()->create([
            'paid_by' => 2,
            'bill_type_id' => MasterBillType::first()->id,
            Columns::bill_create_date => now()
        ]);

        $bill->billSplits()->createMany([
            [
                Columns::borrow_by => $user->id,
                Columns::payment_status_id => $pendingStatusId,
                'paid_by' => 2,
                'amount' => 100,
            ],
            [
                Columns::borrow_by => 3,
                Columns::payment_status_id => $paidStatusId,
                'paid_by' => 2,
                'amount' => 100,
            ],
        ]);

        $response = $this->getJson($this->url);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('Pending', $json['data'][0][Columns::status]);

        // Mark all paid
        $bill->billSplits()->update([
            Columns::payment_status_id => $paidStatusId,
        ]);

        $response = $this->getJson($this->url);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('Paid', $json['data'][0][Columns::status]);
    }

    public function test_only_bills_joined_by_authenticated_user_and_not_paid_by_user_returned()
    {
        $this->authenticate();
        $user = $this->user;

        // Bill paid by the user => should NOT be returned
        $bill1 = Bill::factory()->create([
            'paid_by' => $user->id,
            'bill_type_id' => MasterBillType::first()->id,
            Columns::bill_create_date => now()
        ]);
        $bill1->billSplits()->create([
            Columns::borrow_by => $user->id,
            Columns::payment_status_id => MasterPaymentStatus::where('title', 'Pending')->value(Columns::id),
            'paid_by' => 2,
            'amount' => 100,
        ]);

        // Bill not paid by user, user joined => SHOULD be returned
        $payer = User::factory()->create(); // Create a payer user dynamically

        $bill2 = Bill::factory()->create([
            'paid_by' => $payer->id,
            'bill_type_id' => MasterBillType::first()->id,
            Columns::bill_create_date => now()
        ]);
        $bill2->billSplits()->create([
            Columns::borrow_by => $user->id,
            Columns::payment_status_id => MasterPaymentStatus::where('title', 'Pending')->value(Columns::id),
            'paid_by' => $payer->id,
            'amount' => 100,
        ]);

        $response = $this->getJson($this->url);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals($bill2->id, $json['data'][0][Columns::id]);
    }
}
