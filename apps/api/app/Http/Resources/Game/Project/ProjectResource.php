<?php

namespace App\Http\Resources\Game\Project;

use App\Models\Game\Project\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $game = $this->gameContentType?->game;

        return [
            'id' => $this->id,
            'ownerable_type' => $this->ownerable_type,
            'ownerable_id' => $this->ownerable_id,
            'game_content_type_id' => $this->game_content_type_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'tags' => $this->tags,
            'website_urls' => $this->website_urls,
            'logo_url' => $this->getFirstMediaUrl('logo') ?: null,
            'screenshot_urls' => $this->getMedia('screenshots')
                ->map(fn ($media): string => $media->getUrl())
                ->values(),
            'licence_name' => $this->licence_name,
            'licence_url' => $this->getFirstMediaUrl('licence') ?: null,
            'dimension_value_ids' => $this->whenLoaded(
                'dimensionValues',
                fn () => $this->dimensionValues->pluck('id')->values()
            ),
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'status_color' => $this->status?->getColor(),
            'game_id' => $game?->id,
            'game_name' => $game?->name,
            'content_type_name' => $this->gameContentType?->contentType?->name,
            'released_at' => $this->releases_max_released_at,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
