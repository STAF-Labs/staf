<?php

namespace App\Models\Game;

use App\Enums\CommonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class Game extends Model
{
    use InteractsWithMedia, softDeletes;

    protected $table = 'games';

    protected $fillable = [
        'name',
        'description',
        'released_at',

        'status',
    ];

    protected $casts = [
        'status' => CommonStatus::class,
    ];

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
