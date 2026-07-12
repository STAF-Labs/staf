<?php

namespace App\Models\Game\Project;

use App\Models\Game\Filter\DimensionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReleaseDimensionValue extends Model
{
    protected $table = 'project_release_dimension_values';

    protected $fillable = [
        'project_release_id',
        'dimension_value_id',
    ];

    public function projectRelease(): BelongsTo
    {
        return $this->belongsTo(ProjectRelease::class);
    }

    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class);
    }
}
