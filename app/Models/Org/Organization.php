<?php

namespace App\Models\Org;

use App\Enums\CommonStatus;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class Organization extends Model
{
    use InteractsWithMedia, softDeletes;

    protected $table = 'organizations';

    protected $fillable = [
        'created_by',

        'name',
        'summary',
        'description',
        'website_urls',
        'contact_email',

        'visibility',
        'status',
    ];

    protected $casts = [
        'status' => CommonStatus::class,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orgMembers(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, 'organization_id');
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
            ->singleFile();    }
}
