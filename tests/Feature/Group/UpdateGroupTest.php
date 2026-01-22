<?php

namespace Tests\Feature\Group;

use App\Constants\Columns;
use App\Constants\Messages;
use App\Model\Group;
use App\Model\GroupType;
use App\Model\GroupUser;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UpdateGroupTest extends TestCase
{
    protected string $url = '/api/group/update';
    protected User $user;

    protected function authenticate(): void
    {
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_group_name_is_updated_successfully()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create([
            Columns::created_by => $user->id,
        ]);
        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => true,
        ]);

        $payload = [
            Columns::group_id => $group->id,
            Columns::name => 'Updated Group Name',
        ];

        $response = $this->actingAs($user)->postJson($this->url, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => Messages::GROUP_UPDATED_SUCCESSFULLY,
            ]);

        $this->assertDatabaseHas('groups', [
            Columns::id => $group->id,
            Columns::name => 'Updated Group Name',
        ]);
    }

    public function test_group_image_is_updated_and_old_image_deleted()
    {
        Storage::fake('public');

        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create([
            Columns::created_by => $user->id,
            Columns::image_url => url('storage/old_image.jpg'),
        ]);
        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => true,
        ]);

        $file = UploadedFile::fake()->image('new_image.jpg');

        $response = $this->actingAs($user)->postJson($this->url, [
            Columns::group_id => $group->id,
            Columns::image => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => Messages::GROUP_UPDATED_SUCCESSFULLY,
        ]);
    }

    public function test_group_type_is_updated_successfully()
    {
        $this->authenticate();
        $user = $this->user;

        $groupType = GroupType::factory()->create();
        $group = Group::factory()->create([
            Columns::created_by => $user->id,
        ]);
        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => true,
        ]);

        $response = $this->actingAs($user)->postJson($this->url, [
            Columns::group_id => $group->id,
            Columns::group_type_id => $groupType->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('groups', [
            Columns::id => $group->id,
            Columns::group_type_id => $groupType->id,
        ]);
    }

    public function test_validation_fails_if_group_id_missing()
    {
        $this->authenticate();

        $response = $this->actingAs($this->user)->postJson($this->url, [
            Columns::name => 'No Group ID',
        ]);

        $response->assertStatus(406);
        $this->assertStringContainsString('group_id', $response->getContent());
    }

    public function test_non_admin_cannot_update_group()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create();
        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => false,
        ]);

        $response = $this->actingAs($user)->postJson($this->url, [
            Columns::group_id => $group->id,
            Columns::name => 'Attempted Name Change',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
            'message' => 'Request Failed',
            'error' => Messages::ALLOW_ONLY_GROUP_ADMIN,
        ]);

        $this->assertDatabaseMissing('groups', [
            Columns::id => $group->id,
            Columns::name => 'Attempted Name Change',
        ]);
    }

    public function test_update_fails_if_group_not_found()
    {
        $this->authenticate();

        $response = $this->actingAs($this->user)->postJson($this->url, [
            Columns::group_id => 999999,
            Columns::name => 'Non-existent Group',
        ]);

        $response->assertStatus(406)
            ->assertJsonFragment([
                'key' => 'group_id',
                'error' => 'The selected group id is invalid.',
            ]);
    }

    public function test_update_fails_with_invalid_image_format()
    {
        $this->authenticate();
        $user = $this->user;

        $group = Group::factory()->create([
            Columns::created_by => $user->id,
        ]);
        GroupUser::create([
            Columns::group_id => $group->id,
            Columns::user_id => $user->id,
            Columns::is_admin => true,
        ]);

        $file = UploadedFile::fake()->create('not-image.txt', 10); // Invalid file

        $response = $this->actingAs($user)->postJson($this->url, [
            Columns::group_id => $group->id,
            Columns::image => $file,
        ]);

        $response->assertStatus(406);
        $this->assertStringContainsString('The image must be an image', $response->getContent());
    }
}
