<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class UserProfile extends Model
{
    use InteractsWithMedia, softDeletes;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',

        'display_name',
        'bio',
        'website_urls',
        'birthday',

        'is_public',
        'show_online_status',
        'show_last_seen_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            )
            ->singleFile();

        $this->addMediaCollection('banner')
            ->acceptsFile(
                fn (File $file): bool => str_starts_with($file->mimeType, 'image/')
            )
            ->singleFile();
    }
}
