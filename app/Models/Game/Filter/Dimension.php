<?php

namespace App\Models\Game\Filter;

use App\Concerns\HasSlug;
use App\Enums\Filter\DimSelectionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
{
    use HasSlug;

    protected $table = 'dimensions';

    protected $fillable = [
        'name',
        'slug',
        'selection_mode',
        'is_filterable',
        'is_active',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(DimensionValue::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_filterable' => 'boolean',
            'selection_mode' => DimSelectionMode::class,
        ];
    }

    public function gameContentTypeDimensions(): HasMany
    {
        return $this->hasMany(GameContentTypeDimension::class);
    }
}
