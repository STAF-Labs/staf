<?php

namespace App\Http\Resources\Game\Filter;

use App\Models\Game\Filter\Dimension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dimension */
class DimensionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_content_type_id' => $this->game_content_type_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'selection_mode' => $this->selection_mode->value,
            'applies_to' => $this->applies_to->value,
            'is_filterable' => $this->is_filterable,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'values' => DimensionValueResource::collection($this->whenLoaded('values')),
        ];
    }
}
