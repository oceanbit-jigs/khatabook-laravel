<?php

namespace Tests\Feature\Group;

use App\Constants\Messages;
use App\Model\GroupType;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateGroupTest extends TestCase
{
    protected string $url = '/api/group/create';
    protected User $user;
    protected function authenticate()
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }


    public function test_group_is_created_successfully_with_valid_members()
    {
        $this->authenticate();
        $user = $this->user;

        // Create a valid group type using factory
        $groupType = GroupType::factory()->create();

        // Create 2 members
        $members = User::factory()->count(2)->create();

        // Prepare payload
        $payload = [
            'name' => 'Test Group',
            'group_type_id' => $groupType->id, // use created group type
            'members' => $members->map(fn($m) => ['user_id' => $m->id])->toArray(),
        ];

        // Make request
        $response = $this->actingAs($user)->postJson($this->url, $payload);

        // For debugging (optional, remove in final version)
        // dump($response->json());

        // Assertions
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => Messages::GROUP_CREATED_SUCCESSFULLY,
            ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'created_by' => $user->id,
        ]);

        foreach ($members as $member) {
            $this->assertDatabaseHas('group_users', [
                'user_id' => $member->id,
            ]);
        }
    }

    public function test_group_creation_fails_if_members_are_missing()
    {
        $this->authenticate();
        $user = $this->user;

        $response = $this->actingAs($user)->postJson($this->url, [
            'name' => 'Group Without Members',
            'group_type_id' => 1,
            'members' => [],
        ]);

        $response->assertStatus(406)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
                'error' => [
                    [
                        'key' => 'members',
                        'error' => 'The members field is required.'
                    ]
                ]
            ]);
    }

    public function test_group_creation_fails_with_invalid_user_id()
    {
        $this->authenticate();
        $user = $this->user;

        $response = $this->actingAs($user)->postJson($this->url, [
            'name' => 'Group With Invalid User',
            'group_type_id' => 1,
            'members' => [['user_id' => 999999]], // Assuming this user ID does not exist
        ]);

        $response->assertStatus(406)
            ->assertJson([
                'status' => false,
                'message' => 'Request Failed',
                'error' => [
                    [
                        'key' => 'members.0.user_id',
                        'error' => 'The selected members.0.user_id is invalid.'
                    ]
                ]
            ]);
    }

    public function test_creator_is_added_as_admin()
    {
        $this->authenticate();
        $user = $this->user;
        $member = User::factory()->create();

        $response = $this->actingAs($user)->postJson($this->url, [ // <- fix here
            'name' => 'Group Test',
            'group_type_id' => 1,
            'members' => [['user_id' => $member->id]],
        ]);

        $response->assertStatus(200); // or 201 depending on your implementation

        $groupId = $response['data']['id'];

        $this->assertDatabaseHas('group_users', [
            'group_id' => $groupId,
            'user_id' => $user->id,
            'is_admin' => true,
        ]);
    }

    public function test_group_is_created_with_image_upload()
    {
        Storage::fake('public');

        $this->authenticate();
        $user = $this->user;
        $members = User::factory()->count(1)->create();
        $file = UploadedFile::fake()->image('group.jpg');

        $response = $this->actingAs($user)->postJson($this->url, [
            'name' => 'With Image',
            'group_type_id' => 1,
            'members' => [['user_id' => $members[0]->id]],
            'image' => $file,
        ]);

        $response->assertStatus(200);
    }

    public function test_group_uses_default_image_when_none_provided()
    {
        $this->authenticate();
        $user = $this->user;
        $members = User::factory()->count(1)->create();

        $response = $this->actingAs($user)->postJson($this->url, [
            'name' => 'No Image',
            'group_type_id' => 1,
            'members' => [['user_id' => $members[0]->id]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.image_url', url('images/users/def_user.png'));
    }

    public function test_validation_fails_if_required_fields_missing()
{
    $this->authenticate();
    $user = $this->user;

    $response = $this->actingAs($user)->postJson($this->url, []);

    $response->assertStatus(406)
        ->assertJson([
            'status' => false,
            'message' => 'Request Failed',
        ]);

    $errorKeys = collect($response['error'])->pluck('key')->toArray();

    $this->assertContains('name', $errorKeys);
    $this->assertContains('group_type_id', $errorKeys);
    $this->assertContains('members', $errorKeys);
}

}
