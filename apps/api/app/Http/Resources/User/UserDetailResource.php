<?php

namespace App\Http\Resources\User;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'status_color' => $this->status?->getColor(),
            'display_name' => $this->whenLoaded('userProfile', fn (): ?string => $this->userProfile?->display_name),
            'avatar_url' => $this->whenLoaded(
                'userProfile',
                fn (): ?string => $this->userProfile?->getFirstMediaUrl('avatar') ?: null,
            ),
            'banner_url' => $this->whenLoaded(
                'userProfile',
                fn (): ?string => $this->userProfile?->getFirstMediaUrl('banner') ?: null,
            ),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'profile' => $this->whenLoaded('userProfile', fn (): ?array => $this->userProfile === null ? null : [
                'id' => $this->userProfile->id,
                'display_name' => $this->userProfile->display_name,
                'avatar_url' => $this->userProfile->getFirstMediaUrl('avatar') ?: null,
                'banner_url' => $this->userProfile->getFirstMediaUrl('banner') ?: null,
                'bio' => $this->userProfile->bio,
                'website_urls' => $this->userProfile->website_urls,
                'birthday' => $this->userProfile->birthday?->toDateString(),
                'is_public' => $this->userProfile->is_public,
                'show_online_status' => $this->userProfile->show_online_status,
                'show_last_seen_at' => $this->userProfile->show_last_seen_at,
                'created_at' => $this->userProfile->created_at?->toISOString(),
            ]),
            'organizations' => $this->whenLoaded('memberOf', fn (): array => $this->memberOf
                ->map(fn ($membership): ?array => $membership->inOrganization === null ? null : [
                    'id' => $membership->inOrganization->id,
                    'name' => $membership->inOrganization->name,
                    'status' => $membership->inOrganization->status?->value,
                    'status_label' => $membership->inOrganization->status?->getLabel(),
                    'status_color' => $membership->inOrganization->status?->getColor(),
                    'is_visible' => $membership->inOrganization->is_visible,
                    'verified_at' => $membership->inOrganization->verified_at?->toISOString(),
                ])
                ->filter()
                ->values()
                ->all()),
        ];
    }
}
