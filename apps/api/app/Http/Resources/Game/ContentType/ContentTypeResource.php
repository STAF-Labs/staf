<?php

namespace App\Http\Resources\Game\ContentType;

use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentType
 */
class ContentTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $games = $this->gameContentTypes
            ->map(fn (GameContentType $gameContentType): ?array => $gameContentType->game === null ? null : [
                'id' => $gameContentType->game->id,
                'name' => $gameContentType->game->name,
            ])
            ->filter()
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'games' => $games,
            'game_ids' => $games->pluck('id')->all(),
            'game_names' => $games->pluck('name')->all(),
            'is_public' => $this->is_public,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
