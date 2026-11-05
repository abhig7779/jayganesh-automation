<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'branch_id', 'is_active', 'last_login_at', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roleLabel(): string
    {
        $role = $this->getRoleNames()->first();

        return Permissions::ROLE_LABELS[$role] ?? ucfirst((string) $role);
    }

    /** Owner and Finance get a short idle timeout; counter staff a long one (ARCHITECTURE.md §4). */
    public function idleTimeoutMinutes(): int
    {
        return $this->hasAnyRole(['owner', 'finance']) ? 30 : 480;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'branch_id', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('staff');
    }
}
