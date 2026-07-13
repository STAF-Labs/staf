<?php

namespace App\Http\Resources\User;

use App\Models\User\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserProfile
 */
class UserProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn (): ?string => $this->user?->username),
            'email' => $this->whenLoaded('user', fn (): ?string => $this->user?->email),
            'display_name' => $this->display_name,
            'avatar_url' => $this->getFirstMediaUrl('avatar') ?: null,
            'birthday' => $this->birthday?->toDateString(),
            'is_public' => $this->is_public,
            'show_online_status' => $this->show_online_status,
            'show_last_seen_at' => $this->show_last_seen_at,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
