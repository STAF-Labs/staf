<?php

namespace App\Models\Game\Project;

use App\Concerns\HasSlug;
use App\Enums\Project\ProjectStatus;
use App\Models\Game\ContentType\GameContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Project extends Model
{
    use HasSlug;

    protected $table = 'projects';

    protected $fillable = [
        'ownerable_type',
        'ownerable_id',
        'game_content_type_id',
        'title',
        'slug',
        'summary',
        'description',
        'tags',
        'website_urls',

        'status',
    ];

    public function ownerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'json:unicode',
            'status' => ProjectStatus::class,
            'summary' => 'json:unicode',
            'tags' => 'json:unicode',
            'website_urls' => 'json:unicode',
        ];
    }

    public function gameContentType(): BelongsTo
    {
        return $this->belongsTo(GameContentType::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ProjectRelease::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }
}
