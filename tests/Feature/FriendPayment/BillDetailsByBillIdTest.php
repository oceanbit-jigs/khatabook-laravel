<?php

namespace Tests\Feature\Bill;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\BillUser;
use App\Model\Group;
use App\Model\GroupUser;
use App\Model\MasterPaymentStatus;
use App\User;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BillDetailsByBillIdTest extends TestCase
{
    protected string $url = '/api/v2/billDetails/byBillId';

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

    public function test_validation_error_when_bill_id_missing_or_invalid()
    {
        $user = $this->authenticate();
    
        // Missing bill_id (GET with no parameters)
        $response = $this->actingAs($user)->getJson($this->url);
    
        $response->assertStatus(406);
    
        $json = $response->json();
        $this->assertFalse($json[Keys::STATUS]);
        $this->assertEquals('Request Failed', $json[Keys::MESSAGE]);
    
        $this->assertTrue(
            collect($json[Keys::ERROR])->contains(function ($error) {
                return $error['key'] === Columns::bill_id && $error['error'] === 'The bill id field is required.';
            }),
            'Expected missing bill_id validation error not found.'
        );
    
        // Invalid bill_id (GET with invalid id as query param)
        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::bill_id => 9999999,
        ]));
    
        $response->assertStatus(406);
    
        $json = $response->json();
        $this->assertFalse($json[Keys::STATUS]);
        $this->assertEquals('Request Failed', $json[Keys::MESSAGE]);
    
        $this->assertTrue(
            collect($json[Keys::ERROR])->contains(function ($error) {
                return $error['key'] === Columns::bill_id && $error['error'] === 'The selected bill id is invalid.';
            }),
            'Expected invalid bill_id validation error not found.'
        );
    }

    public function test_bill_not_accessible_by_user()
    {
        $user = $this->authenticate();

        $otherUser = User::factory()->create();
        $group = Group::factory()->create();
        $bill = Bill::factory()->create([
            Columns::group_id => $group->id,
        ]);

        // Associate bill only with another user
        GroupUser::factory()->create([
            Columns::group_id => $group->id,
            Columns::user_id => $otherUser->id,
        ]);
        BillUser::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::user_id => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::bill_id => $bill->id,
        ]));

        $response->assertStatus(400)
            ->assertJson([
                Keys::STATUS => false,
                Keys::MESSAGE => "Request Failed",
                Keys::ERROR => Messages::NO_DATA_FOUND,
            ]);
    }

    public function test_bill_detail_successfully_returned()
    {
        $user = $this->authenticate();
        $borrower = User::factory()->create();
        $group = Group::factory()->create();

        GroupUser::factory()->createMany([
            [Columns::group_id => $group->id, Columns::user_id => $user->id],
            [Columns::group_id => $group->id, Columns::user_id => $borrower->id],
        ]);

        $bill = Bill::factory()->create([
            Columns::group_id => $group->id,
            Columns::amount => 3000,
            Columns::paid_by => $user->id,
            Columns::bill_create_date => Carbon::now()->subDay(),
        ]);

        BillUser::factory()->createMany([
            [Columns::bill_id => $bill->id, Columns::user_id => $user->id],
            [Columns::bill_id => $bill->id, Columns::user_id => $borrower->id],
        ]);

        $paidStatusId = MasterPaymentStatus::where(Columns::title, Messages::PAID)->value(Columns::id);
        $pendingStatusId = MasterPaymentStatus::where(Columns::title, 'Pending')->value(Columns::id);

        BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::paid_by => $user->id,
            Columns::borrow_by => $borrower->id,
            Columns::amount => 1000,
            Columns::payment_status_id => $paidStatusId,
        ]);

        BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::paid_by => $user->id,
            Columns::borrow_by => $user->id, // self
            Columns::amount => 2000,
            Columns::payment_status_id => $pendingStatusId,
        ]);

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::bill_id => $bill->id,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJsonStructure([
                Keys::DATA => [
                    Columns::bill_id,
                    Columns::name,
                    Columns::notes,
                    Columns::address,
                    Columns::latitude,
                    Columns::longitude,
                    Columns::total_amount,
                    Columns::total_received_amount,
                    Columns::bill_create_date,
                    Columns::created_at,
                    Columns::paid_by => [
                        Columns::id,
                        Columns::first_name,
                        Columns::last_name,
                        Columns::email,
                        Columns::contact_name,
                        Columns::phone,
                        Columns::image_url,
                    ],
                    Columns::bill_splits => [
                        '*' => [
                            Columns::id,
                            Columns::user_id,
                            Columns::first_name,
                            Columns::last_name,
                            Columns::email,
                            Columns::contact_name,
                            Columns::phone,
                            Columns::image_url,
                            Columns::amount,
                            Columns::status,
                        ]
                    ]
                ]
            ]);

        $responseData = $response->json(Keys::DATA);
        $this->assertEquals(1000, $responseData[Columns::total_received_amount]);
    }
}
