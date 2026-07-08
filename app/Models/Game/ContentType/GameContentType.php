<?php

namespace App\Models\Game\ContentType;

use App\Models\Game\Filter\GameContentTypeDimension;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameContentType extends Model
{
    protected $table = 'game_content_types';

    protected $fillable = [
        'game_id',
        'content_type_id',
        'notes',
    ];

    protected $casts = [
        'notes' => 'json:unicode',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    public function dimensions(): HasMany
    {
        return $this->hasMany(GameContentTypeDimension::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
