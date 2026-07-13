<?php

namespace App\Http\Resources\Org;

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
                    'username' => $membership->member?->username,
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
        ];
    }
}
