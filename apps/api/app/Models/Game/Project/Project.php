<?php

namespace App\Models\Game\Project;

use App\Concerns\HasSlug;
use App\Enums\Project\ProjectStatus;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\DimensionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class Project extends Model implements HasMedia
{
    use HasSlug, InteractsWithMedia;

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

    public function dimensionValueLinks(): HasMany
    {
        return $this->hasMany(ProjectDimensionValue::class);
    }

    public function dimensionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            DimensionValue::class,
            'project_dimension_values'
        )->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            )
            ->singleFile();

        $this->addMediaCollection('screenshots')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            );

        $this->addMediaCollection('licence')
            ->acceptsFile(
                fn (File $file): bool => in_array($file->mimeType, [
                    'application/msword',
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/markdown',
                    'text/plain',
                ], true)
            )
            ->singleFile();
    }

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }
}
