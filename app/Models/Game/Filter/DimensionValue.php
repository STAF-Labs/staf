<?php

namespace App\Models\Game\Filter;

use App\Models\Game\Project\ProjectReleaseDimensionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimensionValue extends Model
{
    protected $table = 'dimension_values';

    protected $fillable = [
        'dimension_id',
        'parent_id',
        'name',
        'sort_order',
        'is_active',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function projectReleaseDimensionValues(): HasMany
    {
        return $this->hasMany(ProjectReleaseDimensionValue::class);
    }
}
