<?php

namespace App\Models\Game\Filter;

use App\Concerns\HasSlug;
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

    protected $casts = [
        'is_active' => 'boolean',
        'is_filterable' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(DimensionValue::class);
    }

    public function gameContentTypeDimensions(): HasMany
    {
        return $this->hasMany(GameContentTypeDimension::class);
    }
}
