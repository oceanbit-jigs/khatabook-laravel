<?php

namespace Tests\Feature\PaymentRequest;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Model\BillSplit;
use App\Model\MasterPaymentStatus;
use App\Model\PaymentRequest;
use App\User;
use Tests\TestCase;

class CreatePaymentRequestTest extends TestCase
{
    protected string $url = '/api/payment/request';

    protected function actingAsUser()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        return $user;
    }

    protected function assertCustomValidationErrors($response, array $expectedKeys)
    {
        $response->assertStatus(406);
        $json = $response->json();
        $this->assertFalse($json['status']);
        $this->assertEquals('Request Failed', $json['message']);
        $this->assertIsArray($json['error']);

        foreach ($expectedKeys as $key) {
            $this->assertNotNull(collect($json['error'])->firstWhere('key', $key), "Missing validation error for: $key");
        }
    }

    /** @test */
    public function it_fails_validation_if_required_fields_are_missing()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->url, []);

        $this->assertCustomValidationErrors($response, [
            Columns::payment_status_id,
            Columns::bill_split_id,
            Columns::from_user_id,
            Columns::to_user_id,
            Columns::amount,
        ]);
    }

    /** @test */
    public function it_fails_if_foreign_keys_do_not_exist()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->url, [
            Columns::payment_status_id => 999,
            Columns::bill_split_id => 999,
            Columns::from_user_id => 999,
            Columns::to_user_id => 999,
            Columns::amount => 50,
        ]);

        $this->assertCustomValidationErrors($response, [
            Columns::payment_status_id,
            Columns::bill_split_id,
            Columns::from_user_id,
            Columns::to_user_id,
        ]);
    }

    /** @test */
    public function it_fails_if_payment_request_already_exists_and_status_not_declined()
    {
        $user = $this->actingAsUser(); // from_user_id
    
        $toUser = User::factory()->create(); // Make sure this user exists in DB
    
        $status = MasterPaymentStatus::firstOrCreate(['id' => 1]); // 1 = pending
    
        $billSplit = BillSplit::factory()->create([
            Columns::payment_status_id => 1,
            Columns::borrow_by => $user->id,
            Columns::paid_by => $toUser->id,
        ]);
    
        PaymentRequest::factory()->create([
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $user->id,
            Columns::to_user_id => $toUser->id,
        ]);
    
        $response = $this->postJson($this->url, [
            Columns::payment_status_id => $status->id,
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $user->id,
            Columns::to_user_id => $toUser->id,
            Columns::amount => 100,
        ]);
    
        $response->assertStatus(400);
        $json = $response->json();
    
        $this->assertFalse($json['status']);
        $this->assertEquals(Messages::PAYMENT_REQUEST_ALERADY_CREATED, $json['error']);
    }
    

    /** @test */
    public function it_creates_payment_request_successfully()
    {
        $from = $this->actingAsUser();
        $to = User::factory()->create([
            Columns::fcm_token => 'dummy_token',
        ]);

        $status = MasterPaymentStatus::firstOrCreate(['id' => 1]);
        $billSplit = BillSplit::factory()->create([
            Columns::payment_status_id => 3, // declined, allows retry
            Columns::borrow_by => $from->id,
            Columns::paid_by => $to->id,
        ]);

        $response = $this->postJson($this->url, [
            Columns::payment_status_id => $status->id,
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $from->id,
            Columns::to_user_id => $to->id,
            Columns::amount => 50,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => Messages::ADDED_SUCCESSFULLY,
        ]);

        $this->assertDatabaseHas('payment_requests', [
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $from->id,
            Columns::to_user_id => $to->id,
            Columns::amount => 50,
        ]);

        $this->assertDatabaseHas('bill_splits', [
            'id' => $billSplit->id,
            Columns::payment_status_id => 1, // set to pending
        ]);
    }

    /** @test */
    public function it_skips_notification_if_receiver_has_no_token()
    {
        $from = $this->actingAsUser();
        $to = User::factory()->create([
            Columns::fcm_token => null,
        ]);

        $status = MasterPaymentStatus::factory()->create();
        $billSplit = BillSplit::factory()->create([
            Columns::payment_status_id => 3,
            Columns::borrow_by => $from->id,
            Columns::paid_by => $to->id,
        ]);

        $response = $this->postJson($this->url, [
            Columns::payment_status_id => $status->id,
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $from->id,
            Columns::to_user_id => $to->id,
            Columns::amount => 99.99,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payment_requests', [
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $from->id,
            Columns::to_user_id => $to->id,
            Columns::amount => 99.99,
        ]);
    }
}
