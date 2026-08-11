<?php

namespace App\Models\Game\Project;

use App\Concerns\HasSlug;
use App\Enums\Project\ProjectReleaseStatus;
use App\Enums\Project\ProjectReleaseType;
use App\Models\Game\Filter\DimensionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProjectRelease extends Model implements HasMedia
{
    use HasSlug, InteractsWithMedia;

    protected $table = 'project_releases';

    protected $fillable = [
        'project_id',
        'title',
        'slug',
        'type',
        'changelog',
        'released_at',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProjectReleaseStatus::ON_MODERATION->value,
        'type' => ProjectReleaseType::RELEASE->value,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changelog' => 'json:unicode',
            'released_at' => 'date',
            'type' => ProjectReleaseType::class,
            'status' => ProjectReleaseStatus::class,
        ];
    }

    public function dimensionValueLinks(): HasMany
    {
        return $this->hasMany(ProjectReleaseDimensionValue::class);
    }

    public function dimensionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            DimensionValue::class,
            'project_release_dimension_values'
        )->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('release')
            ->singleFile();
    }

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    /**
     * @return array<string, mixed>
     */
    protected function uniqueSlugConstraints(): array
    {
        return [
            'project_id' => $this->project_id,
        ];
    }
}
