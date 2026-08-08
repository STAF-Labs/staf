<?php

namespace Tests\Feature\Admin\Project;

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_project(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();

        $this
            ->postJson('/api/projects', $this->projectPayload($user, $gameContentType))
            ->assertUnauthorized();

        $this->assertDatabaseEmpty('projects');
    }

    public function test_authenticated_user_can_create_project_for_themselves(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $this
            ->actingAs($user)
            ->postJson('/api/projects', $this->projectPayload($user, $gameContentType))
            ->assertCreated()
            ->assertJsonPath('ownerable_type', User::class)
            ->assertJsonPath('ownerable_id', $user->id);
    }

    public function test_owner_and_maintainer_can_create_project_for_active_organization(): void
    {
        Storage::fake('public');

        [, $gameContentType] = $this->projectContext();

        foreach ([OrgMemberRole::OWNER, OrgMemberRole::MAINTAINER] as $index => $role) {
            $user = $this->createUser("eligible-author-{$index}");
            $organization = $this->createOrganization("Eligible Organization {$index}");
            $this->createMembership($user, $organization, $role);

            $this
                ->actingAs($user)
                ->postJson('/api/projects', [
                    ...$this->projectPayload($organization, $gameContentType),
                    'title' => "Organization Project {$index}",
                    'logo' => UploadedFile::fake()->image("logo-{$index}.png", 512, 512),
                ])
                ->assertCreated()
                ->assertJsonPath('ownerable_type', Organization::class)
                ->assertJsonPath('ownerable_id', $organization->id)
                ->assertJsonPath('owner_name', $organization->name);
        }
    }

    public function test_member_and_reader_cannot_create_project_for_organization(): void
    {
        Storage::fake('public');

        [, $gameContentType] = $this->projectContext();

        foreach ([OrgMemberRole::MEMBER, OrgMemberRole::READER] as $index => $role) {
            $user = $this->createUser("ineligible-author-{$index}");
            $organization = $this->createOrganization("Ineligible Organization {$index}");
            $this->createMembership($user, $organization, $role);

            $this
                ->actingAs($user)
                ->postJson('/api/projects', [
                    ...$this->projectPayload($organization, $gameContentType),
                    'logo' => UploadedFile::fake()->image("logo-{$index}.png", 512, 512),
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseEmpty('projects');
    }

    public function test_inactive_membership_or_organization_cannot_author_projects(): void
    {
        Storage::fake('public');

        [, $gameContentType] = $this->projectContext();
        foreach ([MembershipStatus::INVITED, MembershipStatus::SUSPENDED] as $index => $status) {
            $user = $this->createUser("inactive-member-{$index}");
            $organization = $this->createOrganization("Membership Organization {$index}");
            $this->createMembership($user, $organization, OrgMemberRole::OWNER, $status);

            $this
                ->actingAs($user)
                ->postJson('/api/projects', $this->projectPayload($organization, $gameContentType))
                ->assertForbidden();
        }

        foreach ([CommonStatus::BLOCKED, CommonStatus::SUSPENDED] as $index => $status) {
            $user = $this->createUser("inactive-organization-owner-{$index}");
            $organization = $this->createOrganization("Inactive Organization {$index}", $status);
            $this->createMembership($user, $organization, OrgMemberRole::OWNER);

            $this
                ->actingAs($user)
                ->postJson('/api/projects', $this->projectPayload($organization, $gameContentType))
                ->assertForbidden();
        }

        $this->assertDatabaseEmpty('projects');
    }

    public function test_user_cannot_create_project_for_another_user(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $otherUser = $this->createUser('other-author');

        $this
            ->actingAs($user)
            ->postJson('/api/projects', $this->projectPayload($otherUser, $gameContentType))
            ->assertForbidden();

        $this->assertDatabaseEmpty('projects');
    }

    public function test_author_options_contain_only_personal_profile_and_eligible_organizations(): void
    {
        [$user] = $this->projectContext();
        $ownerOrganization = $this->createOrganization('Owner Organization');
        $maintainerOrganization = $this->createOrganization('Maintainer Organization');
        $memberOrganization = $this->createOrganization('Member Organization');
        $blockedOrganization = $this->createOrganization('Blocked Organization', CommonStatus::BLOCKED);
        $this->createMembership($user, $ownerOrganization, OrgMemberRole::OWNER);
        $this->createMembership($user, $maintainerOrganization, OrgMemberRole::MAINTAINER);
        $this->createMembership($user, $memberOrganization, OrgMemberRole::MEMBER);
        $this->createMembership($user, $blockedOrganization, OrgMemberRole::OWNER);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/project-owner-options')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.kind', 'user')
            ->assertJsonPath('data.0.id', $user->id);

        $optionIds = collect($response->json('data'))->pluck('id');

        $this->assertTrue($optionIds->contains($ownerOrganization->id));
        $this->assertTrue($optionIds->contains($maintainerOrganization->id));
        $this->assertFalse($optionIds->contains($memberOrganization->id));
        $this->assertFalse($optionIds->contains($blockedOrganization->id));
    }

    public function test_project_index_uses_organization_name_as_author(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $organization = $this->createOrganization('Index Author Organization');
        $project = Project::query()->create([
            ...$this->projectAttributes($organization, $gameContentType),
            'title' => 'Organization Index Project',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.owner_name', 'Index Author Organization');
    }

    public function test_standard_update_cannot_transfer_project_to_another_author(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $organization = $this->createOrganization('Transfer Target');
        $this->createMembership($user, $organization, OrgMemberRole::OWNER);
        $project = Project::query()->create([
            ...$this->projectAttributes($user, $gameContentType),
            'title' => 'Original Project',
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'ownerable_type' => Organization::class,
                'ownerable_id' => $organization->id,
                'title' => 'Updated Project',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ownerable_type', 'ownerable_id']);

        $project->refresh();

        $this->assertSame('Original Project', $project->title);
        $this->assertSame(User::class, $project->ownerable_type);
        $this->assertSame($user->id, $project->ownerable_id);
    }

    public function test_personal_project_owner_can_delete_project(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectAttributes($user, $gameContentType));

        $this
            ->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Проект удален.');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_organization_owner_and_maintainer_can_delete_project(): void
    {
        [, $gameContentType] = $this->projectContext();

        foreach ([OrgMemberRole::OWNER, OrgMemberRole::MAINTAINER] as $index => $role) {
            $user = $this->createUser("delete-author-{$index}");
            $organization = $this->createOrganization("Delete Organization {$index}");
            $this->createMembership($user, $organization, $role);
            $project = Project::query()->create([
                ...$this->projectAttributes($organization, $gameContentType),
                'title' => "Delete Project {$index}",
            ]);

            $this
                ->actingAs($user)
                ->deleteJson("/api/projects/{$project->id}")
                ->assertOk();

            $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        }
    }

    public function test_non_owner_cannot_delete_project(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $otherUser = $this->createUser('delete-other-user');
        $project = Project::query()->create($this->projectAttributes($user, $gameContentType));

        $this
            ->actingAs($otherUser)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_organization_member_cannot_delete_project(): void
    {
        [$owner, $gameContentType] = $this->projectContext();
        $organization = $this->createOrganization('Protected Organization');
        $this->createMembership($owner, $organization, OrgMemberRole::OWNER);
        $member = $this->createUser('delete-member');
        $this->createMembership($member, $organization, OrgMemberRole::MEMBER);
        $project = Project::query()->create($this->projectAttributes($organization, $gameContentType));

        $this
            ->actingAs($member)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    /**
     * @return array{User, GameContentType}
     */
    private function projectContext(): array
    {
        $user = $this->createUser('project-author');
        $game = Game::query()->create([
            'name' => 'Authorization Game',
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

    private function createOrganization(
        string $name,
        CommonStatus $status = CommonStatus::ACTIVE,
    ): Organization {
        return Organization::query()->create([
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function createMembership(
        User $user,
        Organization $organization,
        OrgMemberRole $role,
        MembershipStatus $status = MembershipStatus::ACTIVE,
    ): OrganizationMember {
        return OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(User|Organization $author, GameContentType $gameContentType): array
    {
        return [
            ...$this->projectAttributes($author, $gameContentType),
            'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAttributes(
        User|Organization $author,
        GameContentType $gameContentType,
    ): array {
        return [
            'ownerable_type' => $author::class,
            'ownerable_id' => $author->id,
            'game_content_type_id' => $gameContentType->id,
            'title' => 'Authorized Project',
        ];
    }
}
