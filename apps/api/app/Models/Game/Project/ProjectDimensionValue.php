<?php

namespace App\Models\Game\Project;

use App\Models\Game\Filter\DimensionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDimensionValue extends Model
{
    protected $table = 'project_dimension_values';

    protected $fillable = [
        'project_id',
        'dimension_value_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class);
    }
}
