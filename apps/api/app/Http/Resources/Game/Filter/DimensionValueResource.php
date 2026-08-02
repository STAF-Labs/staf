<?php

namespace App\Http\Resources\Game\Filter;

use App\Models\Game\Filter\DimensionValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DimensionValue */
class DimensionValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dimension_id' => $this->dimension_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
