<?php

namespace App\Models\Game\Filter;

use App\Models\Game\ContentType\GameContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameContentTypeDimension extends Model
{
    protected $table = 'game_content_type_dimensions';

    protected $fillable = [
        'game_content_type_id',
        'dimension_id',
        'is_required',
        'sort_order',
        'notes',
    ];

    public function gameContentType(): BelongsTo
    {
        return $this->belongsTo(GameContentType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'notes' => 'json:unicode',
            'sort_order' => 'integer',
        ];
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }
}
