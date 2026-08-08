<?php

namespace App\Policies;

use App\Enums\CommonStatus;
use App\Enums\MembershipStatus;
use App\Enums\Org\OrgMemberRole;
use App\Models\Game\Project\Project;
use App\Models\Org\Organization;
use App\Models\Org\OrganizationMember;
use App\Models\User\User;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability !== 'create' && $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function create(User $user, User|Organization $author): bool
    {
        if ($user->status !== CommonStatus::ACTIVE) {
            return false;
        }

        return match (true) {
            $author instanceof User => $author->is($user),
            $author instanceof Organization => $this->canActForOrganization($user, $author),
        };
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->status !== CommonStatus::ACTIVE) {
            return false;
        }

        return match ($project->ownerable_type) {
            User::class => $project->ownerable_id === $user->id,
            Organization::class => $project->ownerable instanceof Organization
                && $this->canActForOrganization($user, $project->ownerable),
            default => false,
        };
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    private function canActForOrganization(User $user, Organization $organization): bool
    {
        return $organization->status === CommonStatus::ACTIVE
            && OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::ACTIVE)
                ->whereIn('role', [OrgMemberRole::OWNER, OrgMemberRole::MAINTAINER])
                ->exists();
    }
}
