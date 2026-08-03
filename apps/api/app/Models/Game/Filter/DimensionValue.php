<?php

namespace App\Models\Game\Filter;

use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectDimensionValue;
use App\Models\Game\Project\ProjectRelease;
use App\Models\Game\Project\ProjectReleaseDimensionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function projectDimensionValues(): HasMany
    {
        return $this->hasMany(ProjectDimensionValue::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_dimension_values')->withTimestamps();
    }

    public function projectReleases(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectRelease::class,
            'project_release_dimension_values'
        )->withTimestamps();
    }
}
