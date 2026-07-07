<?php

namespace App\Models\User;

use App\Enums\CommonStatus;
use App\Models\Org\Organization;
use App\Models\Org\OrganizationMember;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, softDeletes;

    protected $table = 'users';
    protected string $guard_name = 'web';

    protected $fillable = [
        'username',
        'email',
        'password',

        'status',

        'last_seen_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => CommonStatus::class,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function creatorOf(): HasMany
    {
        return $this->HasMany(Organization::class, 'created_by');
    }

    public function memberOf(): HasMany
    {
        return $this->HasMany(OrganizationMember::class, 'user_id');
    }

    public function inviterOf(): HasMany
    {
        return $this->HasMany(OrganizationMember::class, 'invited_by');
    }
}
