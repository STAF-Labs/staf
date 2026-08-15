<?php

namespace Tests\Feature\Admin\User;

use App\Enums\CommonStatus;
use App\Enums\MembershipStatus;
use App\Enums\Org\OrgMemberRole;
use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use App\Models\Org\Organization;
use App\Models\Org\OrganizationMember;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_show_includes_personal_and_owned_organization_projects(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $otherUser = $this->createUser('other-user');
        $ownedOrganization = $this->createOrganization('Owned Organization');
        $memberOrganization = $this->createOrganization('Member Organization');

        $this->createMembership($user, $ownedOrganization, OrgMemberRole::OWNER);
        $this->createMembership($user, $memberOrganization, OrgMemberRole::MEMBER);

        Project::query()->create($this->projectPayload($user, $gameContentType, 'Personal Project'));
        Project::query()->create($this->projectPayload($ownedOrganization, $gameContentType, 'Owned Organization Project'));
        Project::query()->create($this->projectPayload($memberOrganization, $gameContentType, 'Member Organization Project'));
        Project::query()->create($this->projectPayload($otherUser, $gameContentType, 'Other User Project'));

        $response = $this
            ->actingAs($user)
            ->getJson("/api/users/{$user->id}")
            ->assertOk();

        $projectTitles = collect($response->json('projects'))->pluck('title');

        $this->assertTrue($projectTitles->contains('Personal Project'));
        $this->assertTrue($projectTitles->contains('Owned Organization Project'));
        $this->assertFalse($projectTitles->contains('Member Organization Project'));
        $this->assertFalse($projectTitles->contains('Other User Project'));
    }

    /**
     * @return array{User, GameContentType}
     */
    private function projectContext(): array
    {
        $user = $this->createUser('shown-user');
        $game = Game::query()->create([
            'name' => 'User Show Game',
            'status' => CommonStatus::ACTIVE,
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);
        $gameContentType = GameContentType::query()->create([
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);

        return [$user, $gameContentType];
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'password',
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    private function createOrganization(string $name): Organization
    {
        return Organization::query()->create([
            'name' => $name,
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    private function createMembership(
        User $user,
        Organization $organization,
        OrgMemberRole $role,
    ): OrganizationMember {
        return OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => MembershipStatus::ACTIVE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(
        User|Organization $author,
        GameContentType $gameContentType,
        string $title,
    ): array {
        return [
            'ownerable_type' => $author::class,
            'ownerable_id' => $author->id,
            'game_content_type_id' => $gameContentType->id,
            'title' => $title,
        ];
    }
}
