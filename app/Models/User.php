<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

/**
 * @method bool hasRole(string|array $roles)
 * @method \Spatie\Permission\Collection\PermissionCollection getAllPermissions()
 * @method \Illuminate\Support\Collection getRoleNames()
 * @method $this assignRole(string|array|\Spatie\Permission\Models\Role ...$roles)
 * @method $this removeRole(string|\Spatie\Permission\Models\Role $role)
 * @method bool hasAnyRole(string|array $roles)
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // Profile fields
        'last_name',
        'first_name',
        'middle_name',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * Users managed by this user (when the user has Manager role).
     */
    public function managedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'manager_user_assignments',
            'manager_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * Managers this user is assigned to.
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'manager_user_assignments',
            'user_id',
            'manager_id'
        )->withTimestamps();
    }

    protected static function booted(): void
    {
        // Clear performers cache when a user is created, updated, or deleted
        // This ensures the performers list stays fresh (especially for is_blocked status)
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('performers_list');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('performers_list');
        });
    }
}
