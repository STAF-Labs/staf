<?php

namespace App\Models\Game\ContentType;

use App\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentType extends Model
{
    use HasSlug;

    protected $table = 'content_types';

    protected $fillable = [
        'name',
        'slug',
        'is_public',
    ];

    public function gameContentTypes(): HasMany
    {
        return $this->hasMany(GameContentType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }
}
