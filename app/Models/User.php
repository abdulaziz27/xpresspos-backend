<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Services\StoreContext;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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
        'midtrans_customer_id',
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
}
