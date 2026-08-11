<?php

namespace App\Http\Resources\Game\Project;

use App\Models\Game\Project\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectMember
 */
class ProjectMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'username' => $this->user?->username,
            'display_name' => $this->user?->userProfile?->display_name,
            'avatar_url' => $this->user?->userProfile?->getFirstMediaUrl('avatar') ?: null,
            'role' => $this->role?->value,
            'role_label' => $this->role?->getLabel(),
            'role_color' => $this->role?->getColor(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'status_color' => $this->status?->getColor(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
