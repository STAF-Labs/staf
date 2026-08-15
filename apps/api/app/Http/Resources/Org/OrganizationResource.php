<?php

namespace App\Http\Resources\Org;

use App\Http\Resources\Game\Project\ProjectResource;
use App\Models\Org\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'contact_email' => $this->contact_email,
            'avatar_url' => $this->getFirstMediaUrl('avatar') ?: null,
            'banner_url' => $this->getFirstMediaUrl('banner') ?: null,
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'status_color' => $this->status?->getColor(),
            'is_visible' => $this->is_visible,
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'description' => $this->whenLoaded('orgMembers', fn (): mixed => $this->description),
            'website_urls' => $this->whenLoaded('orgMembers', fn (): mixed => $this->website_urls),
            'members' => $this->whenLoaded('orgMembers', fn (): array => $this->orgMembers
                ->map(fn ($membership): array => [
                    'id' => $membership->id,
                    'user_id' => $membership->member?->id,
                    'username' => $membership->member?->username,
                    'display_name' => $membership->member?->userProfile?->display_name,
                    'avatar_url' => $membership->member?->userProfile?->getFirstMediaUrl('avatar') ?: null,
                    'email_verified_at' => $membership->member?->email_verified_at?->toISOString(),
                    'status' => $membership->status?->value,
                    'status_label' => $membership->status?->getLabel(),
                    'status_color' => $membership->status?->getColor(),
                    'public_title' => $membership->public_title,
                    'role' => $membership->role?->value,
                    'role_label' => $membership->role?->getLabel(),
                    'role_color' => $membership->role?->getColor(),
                ])
                ->values()
                ->all()),
            'projects' => $this->whenLoaded(
                'activityProjects',
                fn (): array => ProjectResource::collection($this->activityProjects)->resolve($request),
            ),
        ];
    }
}
