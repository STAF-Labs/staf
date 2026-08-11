<?php

namespace App\Http\Resources\Game\Project;

use App\Models\Game\Project\ProjectRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectRelease
 */
class ProjectReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type?->value,
            'type_label' => $this->type?->getLabel(),
            'type_color' => $this->type?->getColor(),
            'changelog' => $this->changelog,
            'released_at' => $this->released_at?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'status_color' => $this->status?->getColor(),
            'file_url' => $this->getFirstMediaUrl('release') ?: null,
            'file_name' => $this->getFirstMedia('release')?->file_name,
            'dimension_value_ids' => $this->whenLoaded(
                'dimensionValues',
                fn () => $this->dimensionValues->pluck('id')->values()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
