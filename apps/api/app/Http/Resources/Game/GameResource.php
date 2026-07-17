<?php

namespace App\Http\Resources\Game;

use App\Models\Game\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Game
 */
class GameResource extends JsonResource
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
            'description' => $this->description,
            'released_at' => $this->released_at?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'status_color' => $this->status?->getColor(),
            'logo_url' => $this->getFirstMediaUrl('logo') ?: null,
            'banner_url' => $this->getFirstMediaUrl('banner') ?: null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
