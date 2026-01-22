<?php

namespace Tests\Feature\PaymentRequest;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Constants\Tables;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\MasterBillType;
use App\Model\MasterBillTypeCategory;
use App\Model\MasterPaymentStatus;
use App\Model\PaymentRequest;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcceptPaymentRequestWithBillTest extends TestCase
{
    protected string $url = '/api/payment-request/accept/createbill';

    protected function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        return $user;
    }

    protected function assertValidationError($response, array $fields)
    {
        $response->assertStatus(406);
        $response->assertJson(['status' => false, 'message' => 'Request Failed']);

        foreach ($fields as $field) {
            $this->assertNotNull(collect($response->json('error'))->firstWhere('key', $field));
        }
    }

    /** @test */
    public function it_fails_validation_when_id_is_missing()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->url, []);
        $this->assertValidationError($response, [Columns::id]);
    }

    /** @test */
    public function it_fails_when_payment_request_does_not_exist()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->url, [
            Columns::id => 999,
        ]);

        $response->assertStatus(406);
        $response->assertJson([
            'status' => false,
            'error' => [
                ['error' => "The selected id is invalid."]
            ],
        ]);
    }

    /** @test */
    public function it_fails_when_bill_type_is_missing()
    {
        $user = $this->actingAsUser();

        $fromUser = $user;  // from actingAsUser()
        $toUser = User::factory()->create();

        $billSplit = BillSplit::factory()->create([
            'borrow_by' => $fromUser->id,
            'paid_by' => $toUser->id,
        ]);

        $paymentRequest = PaymentRequest::factory()->create([
            Columns::from_user_id => $fromUser->id,
            Columns::to_user_id => $toUser->id,
            Columns::bill_split_id => $billSplit->id,
            Columns::amount => 100,
        ]);


        // Ensure 'payment_transaction' bill type doesn't exist
        MasterBillType::where('title', 'payment_transaction')->delete();

        $response = $this->postJson($this->url, [
            Columns::id => $paymentRequest->id,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
            'error' => 'Bill type for payment_transaction not found.',
        ]);
    }

    /** @test */
    public function it_creates_a_bill_and_marks_request_and_split_as_paid()
    {
        $fromUser = $this->actingAsUser();
        $toUser = User::factory()->create();

        $paidStatus = MasterPaymentStatus::firstOrCreate(['title' => Messages::PAID]);
        $pendingStatus = MasterPaymentStatus::firstOrCreate(['title' => 'pending']);

        $billTypeCategory = MasterBillTypeCategory::first() ??
            MasterBillTypeCategory::factory()->create();

        $billType = MasterBillType::firstOrCreate(
            ['title' => 'payment_transaction'],
            [
                'bill_type_category_id' => $billTypeCategory->id,
                'value' => 1, // required to avoid "Field 'value' doesn't have a default value" SQL error
            ]
        );


        $billSplit = BillSplit::factory()->create([
            Columns::paid_by => $fromUser->id,
            Columns::borrow_by => $toUser->id,
            Columns::payment_status_id => $pendingStatus->id,
        ]);

        $paymentRequest = PaymentRequest::factory()->create([
            Columns::from_user_id => $toUser->id,     // must match bill_splits.borrow_by
            Columns::to_user_id => $fromUser->id,     // must match bill_splits.paid_by
            Columns::amount => 150,
            Columns::bill_split_id => $billSplit->id,
        ]);

        $response = $this->postJson($this->url, [
            Columns::id => $paymentRequest->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => Messages::BILL_ADDED_SUCCESSFULLY,
        ]);

        $this->assertDatabaseHas('bill_splits', [
            Columns::bill_id => $response->json('data.id'),
            Columns::paid_by => $toUser->id,     // who pays now
            Columns::borrow_by => $fromUser->id, // who receives
            Columns::amount => 150,
            Columns::payment_status_id => $paidStatus->id,
        ]);
    }

    /** @test */
    public function it_handles_notification_skipping_if_fcm_token_missing()
    {
        $fromUser = $this->actingAsUser();
        $toUser = User::factory()->create(['fcm_token' => null]);

        $billType = MasterBillType::factory()->create(['title' => 'payment_transaction']);
        $paidStatus = MasterPaymentStatus::factory()->create(['title' => Messages::PAID]);

        $billSplit = BillSplit::factory()->create([
            Columns::borrow_by => $toUser->id,
            Columns::paid_by => $fromUser->id,
        ]);

        $paymentRequest = PaymentRequest::factory()->create([
            Columns::from_user_id => $toUser->id,     // must match bill_splits.borrow_by
            Columns::to_user_id => $fromUser->id,     // must match bill_splits.paid_by
            Columns::amount => 150,
            Columns::bill_split_id => $billSplit->id,
        ]);

        $response = $this->postJson($this->url, [
            Columns::id => $paymentRequest->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bill_splits', [
            Columns::bill_id => $response->json('data.id'),
            Columns::paid_by => $toUser->id,
            Columns::borrow_by => $fromUser->id,
            Columns::amount => 150,
        ]);

        $this->assertEquals(
            Messages::PAID,
            MasterPaymentStatus::find(
                DB::table('bill_splits')->where('bill_id', $response->json('data.id'))->value('payment_status_id')
            )->title
        );
    }
}
