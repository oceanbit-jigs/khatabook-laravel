<?php

namespace Tests\Feature\Bill;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Model\Bill;
use App\User;
use Tests\TestCase;

class CreateBillTest extends TestCase
{

    protected string $billAddUrl = '/api/bill/add';

    /**
     * Helper to create a logged-in user and set auth header.
     */
    protected function actingAsUser()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        return $user;
    }

    /**
     * Helper to assert validation errors in the custom response format.
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param array $expectedErrorKeys Array of keys expected in error array
     */
    protected function assertCustomValidationErrors($response, array $expectedErrorKeys)
    {
        $response->assertStatus(406);
        $json = $response->json();

        $this->assertFalse($json['status']);
        $this->assertEquals('Request Failed', $json['message']);
        $this->assertIsArray($json['error']);

        foreach ($expectedErrorKeys as $key) {
            $errorItem = collect($json['error'])->firstWhere('key', $key);
            $this->assertNotNull($errorItem, "Expected error key '$key' not found in response");
            $this->assertIsString($errorItem['error']);
        }
    }

    /** @test */
    public function it_fails_validation_if_required_fields_missing()
    {
        $this->actingAsUser();

        $response = $this->postJson($this->billAddUrl, []);

        $this->assertCustomValidationErrors($response, [
            'friends',
            Columns::amount,
            Columns::paid_by,
            Columns::title,
            Columns::is_split_equally,
        ]);
    }

    /** @test */
    public function it_fails_if_friends_array_is_empty()
    {
        $this->actingAsUser();

        $payload = Bill::factory()->make()->toArray();
        $payload['friends'] = [];

        $response = $this->postJson($this->billAddUrl, $payload);

        $this->assertCustomValidationErrors($response, ['friends']);
    }

    /** @test */
    public function it_fails_if_friend_user_id_does_not_exist()
    {
        $this->actingAsUser();

        $payload = Bill::factory()->make()->toArray();
        $payload['friends'] = [['user_id' => 9999999]]; // Non-existent user ID

        $response = $this->postJson($this->billAddUrl, $payload);

        $this->assertCustomValidationErrors($response, ['friends.0.user_id']);
    }

    /** @test */
    public function it_fails_if_amounts_missing_for_custom_split()
    {
        $user = $this->actingAsUser();

        $payload = Bill::factory()->make([
            Columns::is_split_equally => false,
        ])->toArray();

        // friends array missing amount for custom split
        $payload['friends'] = [
            ['user_id' => $user->id] // no amount
        ];

        $response = $this->postJson($this->billAddUrl, $payload);

        $this->assertCustomValidationErrors($response, ['friends.0.amount']);
    }

    /** @test */
    public function it_creates_bill_successfully_with_equal_split()
    {
        $user = $this->actingAsUser();

        $friends = User::factory()->count(3)->create();

        $payload = Bill::factory()->make([
            Columns::is_split_equally => true,
            Columns::paid_by => $user->id,
            Columns::amount => 120.00,
            Columns::bill_create_date => now()->format('d-m-Y'),
        ])->toArray();

        $payload['friends'] = $friends->map(fn($f) => ['user_id' => $f->id])->toArray();

        $response = $this->postJson($this->billAddUrl, $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'message' => Messages::BILL_ADDED_SUCCESSFULLY,
            'status' => true,
        ]);

        $this->assertDatabaseHas('bills', [
            Columns::title => $payload[Columns::title],
            Columns::amount => 120.00,
            Columns::is_split_equally => true,
        ]);

        // Each split should be 120 / 3 = 40
        $this->assertDatabaseHas('bill_splits', [
            Columns::amount => 40.00,
            Columns::paid_by => $user->id,
        ]);
    }

    /** @test */
    public function it_creates_bill_successfully_with_custom_split()
    {
        $user = $this->actingAsUser();

        $friends = User::factory()->count(3)->create();

        $payload = Bill::factory()->make([
            Columns::is_split_equally => false,
            Columns::paid_by => $user->id,
            Columns::amount => 150.00,
        ])->toArray();

        $payload['friends'] = [
            ['user_id' => $friends[0]->id, 'amount' => 50],
            ['user_id' => $friends[1]->id, 'amount' => 50],
            ['user_id' => $friends[2]->id, 'amount' => 50],
        ];

        $response = $this->postJson($this->billAddUrl, $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'message' => Messages::BILL_ADDED_SUCCESSFULLY,
            'status' => true,
        ]);

        $this->assertDatabaseHas('bills', [
            Columns::amount => 150.00,
            Columns::is_split_equally => false,
        ]);

        $this->assertDatabaseHas('bill_splits', [
            Columns::amount => 50.00,
            Columns::paid_by => $user->id,
        ]);
    }

    /** @test */
    public function it_fails_if_total_split_amount_does_not_match_bill_amount()
    {
        $user = $this->actingAsUser();

        $friends = User::factory()->count(2)->create();

        $payload = Bill::factory()->make([
            Columns::is_split_equally => false,
            Columns::paid_by => $user->id,
            Columns::amount => 100,
        ])->toArray();

        // Sum of amounts is 80 instead of 100
        $payload['friends'] = [
            ['user_id' => $friends[0]->id, 'amount' => 50],
            ['user_id' => $friends[1]->id, 'amount' => 30],
        ];

        $response = $this->postJson($this->billAddUrl, $payload);
        // dump($response);
        $response->assertStatus(400);
        $json = $response->json();

        $this->assertFalse($json['status']);
        $this->assertEquals(Messages::AMOUNT_DOES_NOT_MATCH, $json['error']);

        // Bill should not exist
        $this->assertDatabaseMissing('bills', [
            Columns::amount => 100,
            Columns::paid_by => $user->id,
            Columns::created_by => $user->id,
            Columns::title => $payload['title'], // This is random and unique per factory
        ]);
    }

    /** @test */
    public function it_creates_bill_successfully_with_group_id()
    {
        $user = $this->actingAsUser();

        $friends = User::factory()->count(2)->create();

        $group = \App\Model\Group::factory()->create();

        $payload = Bill::factory()->make([
            Columns::group_id => $group->id,
            Columns::is_split_equally => true,
            Columns::paid_by => $user->id,
            Columns::amount => 100.00,
            Columns::bill_create_date => now()->format('d-m-Y'),
        ])->toArray();

        $payload['friends'] = $friends->map(fn($f) => ['user_id' => $f->id])->toArray();

        $response = $this->postJson($this->billAddUrl, $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => Messages::BILL_ADDED_SUCCESSFULLY,
            'status' => true,
        ]);

        // Assert bill exists in DB with group ID
        $this->assertDatabaseHas('bills', [
            Columns::group_id => $group->id,
            Columns::title => $payload[Columns::title],
            Columns::amount => 100.00,
            Columns::is_split_equally => true,
        ]);

        // Each friend + user should owe 100 / 2 = 50.00
        $this->assertDatabaseHas('bill_splits', [
            Columns::amount => 50.00,
            Columns::paid_by => $user->id,
        ]);
    }
}
