<?php

namespace App\Models\Org;

use App\Concerns\HasSlug;
use App\Enums\CommonStatus;
use App\Models\Game\Project\Project;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class Organization extends Model implements HasMedia
{
    use HasSlug, InteractsWithMedia, SoftDeletes;

    protected $table = 'organizations';

    protected $fillable = [
        'created_by',

        'name',
        'slug',
        'summary',
        'description',
        'website_urls',
        'contact_email',

        'is_visible',
        'status',
        'verified_at',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'json:unicode',
            'is_visible' => 'boolean',
            'status' => CommonStatus::class,
            'verified_at' => 'datetime',
            'website_urls' => 'json:unicode',
        ];
    }

    public function orgMembers(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, 'organization_id');
    }

    public function projects(): MorphMany
    {
        return $this->morphMany(Project::class, 'ownerable');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
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
