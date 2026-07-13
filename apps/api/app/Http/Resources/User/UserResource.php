<?php

namespace App\Http\Resources\User;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'birthday' => $this->whenLoaded('userProfile', fn (): ?string => $this->userProfile?->birthday?->toDateString()),
            'is_public' => $this->whenLoaded('userProfile', fn (): ?bool => $this->userProfile?->is_public),
            'show_online_status' => $this->whenLoaded('userProfile', fn (): ?bool => $this->userProfile?->show_online_status),
            'show_last_seen_at' => $this->whenLoaded('userProfile', fn (): ?bool => $this->userProfile?->show_last_seen_at),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
