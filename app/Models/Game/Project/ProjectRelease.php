<?php

namespace App\Models\Game\Project;

use App\Concerns\HasSlug;
use App\Enums\Project\ProjectReleaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectRelease extends Model
{
    use HasSlug;

    protected $table = 'project_releases';

    protected $fillable = [
        'project_id',
        'title',
        'slug',
        'changelog',
        'released_at',
        'status',
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
            'status' => ProjectReleaseStatus::class,
        ];
    }

    public function dimensionValues(): HasMany
    {
        return $this->hasMany(ProjectReleaseDimensionValue::class);
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
