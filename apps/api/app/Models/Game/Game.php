<?php

namespace App\Models\Game;

use App\Concerns\HasSlug;
use App\Enums\CommonStatus;
use App\Models\Game\ContentType\GameContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class Game extends Model implements HasMedia
{
    use HasSlug, InteractsWithMedia, SoftDeletes;

    protected $table = 'games';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'released_at',

        'status',
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
            'description' => 'json:unicode',
            'released_at' => 'date',
            'status' => CommonStatus::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            )->singleFile();

        $this->addMediaCollection('banner')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            )->singleFile();

        $this->addMediaCollection('screenshots')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            );
    }
}
