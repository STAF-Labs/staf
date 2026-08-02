<?php

namespace App\Models\Game\Filter;

use App\Concerns\HasSlug;
use App\Enums\Filter\DimSelectionMode;
use App\Models\Game\ContentType\GameContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
{
    use HasSlug;

    protected $table = 'dimensions';

    protected $fillable = [
        'game_content_type_id',
        'name',
        'slug',
        'selection_mode',
        'is_filterable',
        'is_required',
        'is_active',
        'sort_order',
        'notes',
    ];

    public function gameContentType(): BelongsTo
    {
        return $this->belongsTo(GameContentType::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(DimensionValue::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function uniqueSlugConstraints(): array
    {
        return [
            'game_content_type_id' => $this->game_content_type_id,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'notes' => 'json:unicode',
            'selection_mode' => DimSelectionMode::class,
            'sort_order' => 'integer',
        ];
    }
}
