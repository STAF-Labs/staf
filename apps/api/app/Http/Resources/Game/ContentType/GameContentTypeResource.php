<?php

namespace App\Http\Resources\Game\ContentType;

use App\Models\Game\ContentType\GameContentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameContentType
 */
class GameContentTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'content_type_id' => $this->content_type_id,
            'content_type_name' => $this->contentType?->name,
            'content_type_slug' => $this->contentType?->slug,
            'is_public' => $this->contentType?->is_public,
            'projects_count' => $this->projects_count ?? 0,
            'filters_count' => $this->dimensions_count ?? 0,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
