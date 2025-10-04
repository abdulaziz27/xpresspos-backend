<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Store;

class LandingSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'company',
        'phone',
        'country',
        'preferred_contact_method',
        'notes',
        'plan',
        'status',
        'stage',
        'meta',
        'processed_at',
        'processed_by',
        'follow_up_logs',
        'provisioned_store_id',
        'provisioned_user_id',
        'provisioned_at',
        'onboarding_url',
    ];

    protected $casts = [
        'meta' => 'array',
        'processed_at' => 'datetime',
        'follow_up_logs' => 'array',
        'provisioned_at' => 'datetime',
    ];

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function provisionedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'provisioned_store_id');
    }

    public function provisionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provisioned_user_id');
    }
}
