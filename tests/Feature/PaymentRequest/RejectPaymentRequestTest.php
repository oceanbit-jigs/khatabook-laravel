<?php

namespace Tests\Feature\PaymentRequest;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\PaymentRequest;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RejectPaymentRequestTest extends TestCase
{
    protected string $url = '/api/payment-request/reject';

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
    public function it_fails_validation_if_id_is_missing()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->url, []);

        $this->assertCustomValidationErrors($response, [Columns::id]);
    }

    /** @test */
    public function it_fails_validation_if_id_does_not_exist()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->url, [
            Columns::id => 9999,
        ]);

        $this->assertCustomValidationErrors($response, [Columns::id]);
    }

    /** @test */
    public function it_returns_fail_if_payment_request_not_found_even_if_id_valid()
    {
        $this->actingAsUser();

        // Insert dummy row then delete to ensure id exists but no record is found
        $paymentRequest = PaymentRequest::factory()->create();
        $paymentRequest->delete();

        $response = $this->postJson($this->url, [
            Columns::id => $paymentRequest->id,
        ]);

        $response->assertStatus(406); // typical for validation errors
        $json = $response->json();
        $this->assertFalse($json['status']);
        $this->assertEquals('The selected id is invalid.', $json['error'][0]['error']);
    }

    /** @test */
    public function it_successfully_rejects_payment_request_and_updates_bill_split()
    {
        $toUser = $this->actingAsUser();
        $fromUser = User::factory()->create([
            Columns::fcm_token => 'dummy_token',
        ]);

        $bill = Bill::factory()->create([
            Columns::bill_create_date => null
        ]);

        $billSplit = BillSplit::factory()->create([
            Columns::bill_id => $bill->id,
            Columns::payment_status_id => 1,
            Columns::borrow_by => $fromUser->id,
            Columns::paid_by => $toUser->id,
        ]);

        $paymentRequest = PaymentRequest::factory()->create([
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $fromUser->id,
            Columns::to_user_id => $toUser->id,
        ]);

        $response = $this->postJson($this->url, [
            Columns::id => $paymentRequest->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => Messages::UPDATED_SUCCESSFULLY,
        ]);

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            Columns::payment_status_id => 3,
        ]);

        $this->assertDatabaseHas('bill_splits', [
            'id' => $billSplit->id,
            Columns::payment_status_id => 3,
        ]);
    }

    /** @test */
    public function it_skips_notification_if_from_user_has_no_token()
    {
        $toUser = $this->actingAsUser();
        $fromUser = User::factory()->create([
            Columns::fcm_token => null,
        ]);

        $billSplit = BillSplit::factory()->create([
            Columns::payment_status_id => 1,
            Columns::borrow_by => $fromUser->id,
            Columns::paid_by => $toUser->id,
        ]);

        $paymentRequest = PaymentRequest::factory()->create([
            Columns::bill_split_id => $billSplit->id,
            Columns::from_user_id => $fromUser->id,
            Columns::to_user_id => $toUser->id,
        ]);

        $response = $this->postJson($this->url, [
            Columns::id => $paymentRequest->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            Columns::payment_status_id => 3,
        ]);
    }
}
