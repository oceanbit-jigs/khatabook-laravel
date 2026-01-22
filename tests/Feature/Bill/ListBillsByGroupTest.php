<?php

namespace Tests\Feature;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Model\Bill;
use App\Model\BillSplit;
use App\Model\Group;
use App\Model\MasterPaymentStatus;
use App\Model\MasterBillType;
use App\User;
use Carbon\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;


class ListBillsByGroupTest extends TestCase
{
    protected string $url = '/api/bill/list/byGroup';
    protected User $user;
    protected function authenticate()
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_returns_validation_error_for_missing_group_id()
    {
        $this->authenticate();

        $response = $this->getJson($this->url);

        $response->assertStatus(406); // Or 406 depending on your API
        $response->assertJson([
            'status' => false,
            'message' => 'Request Failed',
        ]);

        $response->assertJsonFragment([
            'key' => Columns::group_id,
            'error' => 'The group id field is required.'
        ]);
    }

    public function test_returns_validation_error_for_non_existent_group_id()
    {
        $this->authenticate();

        $response = $this->getJson($this->url . '?' . Columns::group_id . '=9999');

        $response->assertStatus(406) // ← Your custom status code for validation failure
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
            ])
            ->assertJsonFragment([
                'key' => Columns::group_id,
                'error' => 'The selected group id is invalid.'
            ]);
    }

    public function test_returns_empty_result_if_no_bills_exist()
    {
        $this->authenticate();
        $group = Group::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->getJson($this->url . '?' . Columns::group_id . '=' . $group->id);

        $response->assertStatus(400)
            ->assertJson([
                Keys::STATUS => false,
                Keys::MESSAGE => 'Request Failed',
                Keys::ERROR => Messages::NO_DATA_FOUND
            ]);
    }

    public function test_returns_bills_for_given_group_id()
    {
        $this->authenticate();

        $group = Group::whereHas('bills')->first();  // get an existing group

        if (!$group) {
            $this->markTestSkipped('No group found with bills for this test.');
        }

        $response = $this->getJson($this->url . '?' . Columns::group_id . '=' . $group->id);

        $response->assertStatus(200)
            ->assertJson([
                Keys::STATUS => true,
                Keys::MESSAGE => Messages::RECORD_FOUND_SUCCESSFULLY,
            ])
            ->assertJsonStructure([
                Keys::DATA => [
                    '*' => [
                        Columns::id,
                        Columns::group_id,
                        Columns::title,
                        Columns::amount,
                        Columns::bill_splits,
                    ]
                ]
            ]);
    }

    public function test_pagination_works_correctly()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::whereHas('bills')->first();  // get an existing group


        if (!$group) {
            $this->markTestSkipped('No group found for pagination test.');
        }

        $response = $this->actingAs($user)->getJson($this->url . '?' . http_build_query([
            Columns::group_id => $group->id,
            Columns::limit => 10,
            Columns::page => 1,
        ]));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'group_balance',
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'group_id',
                        'bill_type_id',
                        'created_by',
                        // other expected keys
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
}
