<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Services\StoreContext;
use App\Traits\HasSubscriptionFeatures;
use App\Models\Tenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasSubscriptionFeatures;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'store_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the store that owns the user.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function storeAssignments(): HasMany
    {
        return $this->hasMany(StoreUserAssignment::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user_assignments')
            ->withPivot(['assignment_role', 'is_primary'])
            ->withTimestamps();
    }

    public function primaryStore(): ?Store
    {
        $assignment = $this->storeAssignments()
            ->where('is_primary', true)
            ->orderByDesc('updated_at')
            ->first();

        return $assignment?->store;
    }

    public function currentStoreId(): ?string
    {
        return StoreContext::instance()->current($this);
    }

    /**
     * Get tenants that the user has access to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'user_tenant_access')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the primary tenant for the user (first tenant with owner role).
     */
    public function currentTenant(): ?Tenant
    {
        $tenantAccess = DB::table('user_tenant_access')
            ->where('user_id', $this->id)
            ->where('role', 'owner')
            ->first();

        if ($tenantAccess) {
            return Tenant::find($tenantAccess->tenant_id);
        }

        // Fallback: get first tenant if no owner role found
        $firstTenant = $this->tenants()->first();
        return $firstTenant;
    }
    
    /**
     * Get roles with proper team context.
     */
    public function getRolesWithContext()
    {
        if ($this->store_id) {
            setPermissionsTeamId($this->store_id);
        }
        return $this->roles;
    }
    
    /**
     * Get permissions with proper team context.
     */
    public function getPermissionsWithContext()
    {
        if ($this->store_id) {
            setPermissionsTeamId($this->store_id);
        }
        return $this->getAllPermissions();
    }

    /**
     * Get the orders created by the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the payment methods for the user.
     */
    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the default payment method for the user.
     */
    public function defaultPaymentMethod()
    {
        return $this->hasOne(PaymentMethod::class)->where('is_default', true);
    }

    /**
     * Determine if the user can access the given Filament panel.
     * Required by FilamentUser interface for production environment.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Set team context first to avoid SQL ambiguity
        $storeId = $this->store_id ?? $this->primaryStore()?->id;
        if ($storeId) {
            setPermissionsTeamId($storeId);
        }

        // Admin panel - only admin_sistem and super_admin
        if ($panel->getId() === 'admin') {
            return $this->hasRole(['admin_sistem', 'super_admin']);
        }

        // Owner panel - owner role or admin_sistem/super_admin for monitoring
        if ($panel->getId() === 'owner') {
            // Allow admin_sistem and super_admin to access owner panel
            if ($this->hasRole(['admin_sistem', 'super_admin'])) {
                return true;
            }

            // Check email verification
            if (!$this->email_verified_at) {
                return false;
            }

            // Check if user has store
            if (!$storeId) {
                return false;
            }

            // Check if store is active
            $store = Store::find($storeId);
            if ($store && $store->status !== 'active') {
                return false;
            }

            // Check owner role or assignment
            $hasOwnerRole = $this->hasRole('owner');
            $hasOwnerAssignment = $this->storeAssignments()
                ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
                ->exists();

            return $hasOwnerRole || $hasOwnerAssignment;
        }

        return false;
    }
}
