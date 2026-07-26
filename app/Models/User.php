<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\ResetPasswordMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\PermissionService;
use Modules\Tenancy\Models\Tenant;

#[Fillable(['name', 'email', 'password', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Send the password reset link using Storeboot's branded mailable.
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        Mail::to($this->email)->send(new ResetPasswordMail($this, $resetUrl));
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Does this user hold the given permission within a tenant? (branch scope ignored)
     */
    public function hasPermission(Tenant $tenant, string $permission): bool
    {
        return app(PermissionService::class)->has($this, $tenant, $permission);
    }

    /**
     * Full check: permission held AND allowed in the given branch (null = any branch).
     */
    public function canInBranch(Tenant $tenant, string $permission, ?int $branchId = null): bool
    {
        return app(PermissionService::class)->can($this, $tenant, $permission, $branchId);
    }

    /**
     * The numeric limit configured on this user's role for a limit key (null = unlimited).
     */
    public function permissionLimit(Tenant $tenant, string $key): int|float|null
    {
        return app(PermissionService::class)->limit($this, $tenant, $key);
    }

    /**
     * The user's active role within a tenant, if any.
     */
    public function roleFor(Tenant $tenant): ?Role
    {
        return app(PermissionService::class)->role($this, $tenant);
    }
}
