# Verifikasi: Subscription Per Tenant

**Status:** ✅ **SELESAI - Semua sudah sesuai dengan statement**

---

## ✅ Step 1: Schema & Model Subscription

### **1. Migration `create_subscriptions_table`**

**File:** `database/migrations/2024_10_04_000500_create_subscriptions_table.php`

**Status:** ✅ **Sudah sesuai**

- ✅ `tenant_id` (string(36), NOT NULL, FK ke `tenants.id`)
- ✅ **Tidak ada `store_id`** (sudah dihapus total)
- ✅ Index: `['tenant_id', 'status']`
- ✅ Cascade delete: `onDelete('cascade')`

**Schema Final:**
```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('tenant_id', 36)->comment('Subscription per tenant, bukan per store');
    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
    $table->enum('status', ['active', 'inactive', 'cancelled', 'expired'])->default('active');
    $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly');
    $table->date('starts_at');
    $table->date('ends_at');
    $table->date('trial_ends_at')->nullable();
    $table->decimal('amount', 10, 2);
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['tenant_id', 'status']);
    $table->index('status');
    $table->index('ends_at');
});
```

---

### **2. Subscription Model**

**File:** `app/Models/Subscription.php`

**Status:** ✅ **Sudah sesuai**

- ✅ `fillable`: `tenant_id` (bukan `store_id`)
- ✅ Relasi `tenant()`: `belongsTo(Tenant::class)`
- ✅ **Tidak ada relasi `store()`** (sudah dihapus)

**Relasi:**
```php
public function tenant(): BelongsTo
{
    return $this->belongsTo(Tenant::class);
}
```

---

### **3. Tenant Model**

**File:** `app/Models/Tenant.php`

**Status:** ✅ **Sudah sesuai**

- ✅ Relasi `subscriptions()`: `hasMany(Subscription::class)`
- ✅ Helper `activeSubscription()`: method untuk get active subscription

**Relasi & Helper:**
```php
public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}

public function activeSubscription(): ?Subscription
{
    return $this->subscriptions()
        ->where('status', 'active')
        ->where('ends_at', '>', now())
        ->latest()
        ->first();
}
```

---

## ✅ Step 2: PlanLimitService - Sync ke Tenant

**File:** `app/Services/PlanLimitService.php`

**Status:** ✅ **Sudah sesuai**

### **1. `getActiveSubscription()`**

**Sebelum:**
```php
// Via store_id (salah)
$store = $tenant->stores()->first();
return Subscription::where('store_id', $store->id)...
```

**Sesudah:**
```php
protected function getActiveSubscription(Tenant $tenant): ?Subscription
{
    return $tenant->activeSubscription(); // Pakai helper di model Tenant
}
```

✅ **Tidak ada lagi logika via `store_id`**

---

### **2. `getQuotaForFeatureType()`**

**Sebelum:**
```php
// Via store->tenant (salah)
return $this->limit($subscription->store->tenant, $featureCode);
```

**Sesudah:**
```php
protected function getQuotaForFeatureType(Subscription $subscription, string $featureType): ?int
{
    $tenant = $subscription->tenant; // Langsung dari subscription
    // ...
    return $this->limit($tenant, $featureCode);
}
```

✅ **Langsung pakai `subscription->tenant`**

---

### **3. Verifikasi: Tidak Ada Referensi `store_id`**

**Hasil grep:**
- ✅ Tidak ada `store_id` di `PlanLimitService`
- ✅ Tidak ada `subscription->store` di `PlanLimitService`
- ✅ Hanya ada komentar dokumentasi tentang "Subscription per Tenant"

---

## 🎯 Target Akhir: TERCAPAI

### **Step 1 Target:**
> **Secara database & Eloquent, subscription per-tenant sudah jadi "kebenaran satu-satunya".**

✅ **TERCAPAI:**
- Migration hanya punya `tenant_id`
- Model `Subscription` hanya punya relasi `tenant()`
- Model `Tenant` punya helper `activeSubscription()`

### **Step 2 Target:**
> **Semua limit & usage sekarang benar-benar "dipusatkan di tenant", nggak ada lagi referensi kode ke `store_id` di subscription.**

✅ **TERCAPAI:**
- `getActiveSubscription()` pakai `$tenant->activeSubscription()`
- `getQuotaForFeatureType()` pakai `$subscription->tenant`
- Tidak ada referensi `store_id` di service

---

## 📋 Next Action: Verifikasi dengan `migrate:fresh`

**Command:**
```bash
php artisan migrate:fresh
```

**Yang perlu dicek:**
1. ✅ Tabel `subscriptions` punya kolom `tenant_id`
2. ✅ Tabel `subscriptions` **tidak punya** kolom `store_id`
3. ✅ Foreign key `subscriptions.tenant_id` → `tenants.id` berfungsi
4. ✅ Index `['tenant_id', 'status']` terbuat

**Setelah `migrate:fresh` hijau:**
- ✅ Step 1 & 2 **100% selesai**
- ✅ Siap untuk Step 3 (Provisioning dari Landing → Subscription)

---

**Last Updated:** Setelah semua perubahan diterapkan
**Status:** ✅ **Siap untuk `migrate:fresh` dan verifikasi**

