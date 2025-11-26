# Plan Limits Implementation Guide

**Dokumen ini menjelaskan langkah-langkah implementasi plan limits dan feature gating.**

---

## 🎯 Prerequisites

### **1. Migration: Subscriptions per Tenant (Sudah Diupdate)**

**File:** `database/migrations/2024_10_04_000500_create_subscriptions_table.php`

**Status:** ✅ **Sudah diupdate langsung di base migration**

**Schema Final:**
- `tenant_id` (string(36), NOT NULL, FK ke `tenants.id`)
- **Tidak ada `store_id`** (subscription per tenant, bukan per store)

**Catatan:** 
- Untuk early stage project, kita langsung reshape history di base migration
- Tidak perlu migration tambahan `update_subscriptions_to_tenant`
- Semua code langsung pakai `tenant_id`

### **2. Update Model: Subscription**

**File:** `app/Models/Subscription.php`

**Status:** ✅ **Sudah diupdate**

**Relasi:**
```php
// Relasi ke Tenant (bukan Store)
public function tenant(): BelongsTo
{
    return $this->belongsTo(Tenant::class);
}
```

### **3. Update Model: Tenant**

**File:** `app/Models/Tenant.php`

**Status:** ✅ **Sudah diupdate**

**Relasi & Helper:**
```php
// Relasi ke subscriptions
public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}

// Helper untuk get active subscription
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

## 📋 Daftar Feature Codes Resmi (v1)

**Locked untuk implementasi awal:**

### **Hard Limits (MAX_*):**
1. `MAX_STORES` - Maksimum jumlah store per tenant
2. `MAX_PRODUCTS` - Maksimum jumlah produk per tenant
3. `MAX_STAFF` - Maksimum jumlah staff per tenant
4. `MAX_TRANSACTIONS_PER_YEAR` - Maksimum transaksi per tahun (soft cap)

### **Feature Flags (ALLOW_*):**
5. `ALLOW_LOYALTY` - Fitur loyalty program
6. `ALLOW_MULTI_STORE` - Fitur multi-store
7. `ALLOW_API_ACCESS` - Fitur API access

---

## 🔧 Service: PlanLimitService

**File:** `app/Services/PlanLimitService.php` (sudah dibuat)

**Methods:**
- `hasFeature(Tenant|Store $entity, string $featureCode): bool`
- `limit(Tenant|Store $entity, string $featureCode): ?int`
- `isWithinLimit(Tenant|Store $entity, string $featureCode, int $currentCount): bool`
- `trackUsage(Tenant|Store $entity, string $featureType, int $amount = 1): ?SubscriptionUsage`
- `getUsage(Tenant|Store $entity, string $featureType): array`
- `canPerformAction(Tenant|Store $entity, string $action, int $currentCount = 0): array`

---

## 🎯 Implementation Hooks

### **1. Di Landing / Onboarding**

#### **Tampilkan Fitur per Plan:**
```php
// Controller: LandingController
public function plans()
{
    $plans = Plan::active()->ordered()->get();
    
    foreach ($plans as $plan) {
        $plan->features_normalized = $plan->planFeatures()
            ->where('is_enabled', true)
            ->get()
            ->map(function ($feature) {
                return [
                    'code' => $feature->feature_code,
                    'limit' => $feature->getNumericLimit(),
                    'enabled' => $feature->is_enabled,
                ];
            });
    }
    
    return view('landing.plans', compact('plans'));
}
```

#### **Auto-generate Subscription Usage:**
```php
// Service: SubscriptionProvisioningService
public function createSubscriptionUsage(Subscription $subscription)
{
    $plan = $subscription->plan;
    $features = $plan->planFeatures()
        ->where('is_enabled', true)
        ->where('feature_code', 'LIKE', 'MAX_%')
        ->get();
    
    foreach ($features as $feature) {
        // Map feature_code ke feature_type
        $featureType = $this->mapFeatureCodeToType($feature->feature_code);
        
        if ($featureType) {
            SubscriptionUsage::create([
                'subscription_id' => $subscription->id,
                'feature_type' => $featureType,
                'current_usage' => 0,
                'annual_quota' => $feature->getNumericLimit(),
                'subscription_year_start' => $subscription->starts_at->startOfYear(),
                'subscription_year_end' => $subscription->starts_at->endOfYear(),
            ]);
        }
    }
}
```

---

### **2. Di Dashboard Owner (Filament)**

#### **Feature Gating (Sembunyikan Menu):**
```php
// Resource: app/Filament/Owner/Resources/LoyaltyResource.php
public static function canViewAny(): bool
{
    $user = auth()->user();
    $tenant = $user->tenant; // Via user_tenant_access
    
    $service = app(PlanLimitService::class);
    return $service->hasFeature($tenant, 'ALLOW_LOYALTY');
}
```

#### **Hard Limit (Before Create Store):**
```php
// Resource: app/Filament/Owner/Resources/StoreResource.php
public static function canCreate(): bool
{
    $user = auth()->user();
    $tenant = $user->tenant;
    $service = app(PlanLimitService::class);
    
    $currentStores = $tenant->stores()->count();
    return $service->isWithinLimit($tenant, 'MAX_STORES', $currentStores);
}

// Atau di form action
protected function mutateFormDataBeforeCreate(array $data): array
{
    $user = auth()->user();
    $tenant = $user->tenant;
    $service = app(PlanLimitService::class);
    
    $currentStores = $tenant->stores()->count();
    $result = $service->canPerformAction($tenant, 'create_store', $currentStores);
    
    if (!$result['allowed']) {
        throw new \Exception($result['message']);
    }
    
    return $data;
}
```

#### **Usage Bar / Warning:**
```php
// Widget: app/Filament/Owner/Widgets/UsageWidget.php
public function getUsageData(): array
{
    $user = auth()->user();
    $tenant = $user->tenant;
    $service = app(PlanLimitService::class);
    
    $usage = $service->getUsage($tenant, 'transactions');
    
    return [
        'current' => $usage['current'],
        'quota' => $usage['quota'],
        'percentage' => $usage['percentage'],
        'warning' => $usage['percentage'] >= 80,
    ];
}
```

---

### **3. Di API POS**

#### **Before Create Order (Track Usage):**
```php
// Controller: app/Http/Controllers/Api/V1/OrderController.php
public function store(Request $request)
{
    $store = StoreContext::current();
    $tenant = $store->tenant;
    $service = app(PlanLimitService::class);
    
    // Track usage (soft cap - tidak block)
    $service->trackUsage($tenant, 'transactions', 1);
    
    // Check feature access (hard limit - block jika tidak ada)
    if (!$service->hasFeature($tenant, 'ALLOW_API_ACCESS')) {
        return response()->json([
            'error' => 'API access not available in current plan',
        ], 403);
    }
    
    // Proceed with order creation
    // ...
}
```

#### **Before Create Product (Hard Limit):**
```php
// Controller: app/Http/Controllers/Api/V1/ProductController.php
public function store(Request $request)
{
    $store = StoreContext::current();
    $tenant = $store->tenant;
    $service = app(PlanLimitService::class);
    
    // Get current product count (across all stores in tenant)
    $currentProducts = $tenant->stores()
        ->withCount('products')
        ->get()
        ->sum('products_count');
    
    $result = $service->canPerformAction($tenant, 'create_product', $currentProducts);
    
    if (!$result['allowed']) {
        return response()->json([
            'error' => $result['message'],
            'reason' => $result['reason'],
            'limit' => $result['limit'],
        ], 403);
    }
    
    // Proceed with product creation
    // ...
}
```

---

## 📊 Mapping: Feature Code ↔ Feature Type

**Konvensi:**
- `feature_code` (di `plan_features`) = Definisi limit di plan
- `feature_type` (di `subscription_usage`) = Nama aktivitas yang di-track

**Mapping Resmi (v1):**
| feature_code | feature_type | Deskripsi |
|--------------|--------------|-----------|
| `MAX_TRANSACTIONS_PER_YEAR` | `transactions` | Track transaksi per tahun |

**Catatan:** 
- `MAX_ORDERS_PER_MONTH` adalah contoh untuk future (belum di daftar feature codes resmi v1)
- Jika ingin track orders, pastikan `MAX_ORDERS_PER_MONTH` ada di seed `plan_features` untuk semua plan

**Helper Method:**
```php
protected function mapFeatureCodeToType(string $featureCode): ?string
{
    $mapping = [
        'MAX_TRANSACTIONS_PER_YEAR' => 'transactions',
        'MAX_ORDERS_PER_MONTH' => 'orders',
    ];
    
    return $mapping[$featureCode] ?? null;
}
```

---

## 🎯 Next Steps

1. **Buat Migration:** Update subscriptions ke tenant_id
2. **Update Models:** Tambah relasi tenant di Subscription model
3. **Seed Plan Features:** Isi `plan_features` dengan feature codes resmi
4. **Implement Hooks:** Tambahkan validasi di controller/resource
5. **Test:** Test semua skenario (within limit, exceed limit, feature access)

---

**Last Updated:** Berdasarkan service PlanLimitService dan dokumentasi PLAN_LIMITS_TABLES.md

---

## 🧩 Add-on Invoice Monitoring & Alerting (v2)

### Reminder & Escalation Job
- **Job:** `app/Jobs/AddOnPaymentReminderJob.php`
- **Schedule:** `app/Console/Kernel.php` menjalankan `dailyAt('09:00')`.
- **Konfigurasi:** `config/xendit.php` → `addon.reminder_hours` (default 48 jam) dan `addon.reminder_cooldown_hours` (default 12 jam).
- **Log:** Menggunakan channel `payment` (`storage/logs/payment.log`) untuk setiap reminder yang dikirim / kegagalan.
- **Email Template:** `resources/views/emails/addon-payment-reminder.blade.php` (menampilkan invoice ID, nominal, sisa waktu, CTA “Bayar Sekarang”).

### Owner Portal (Filament Owner)
- **Resource:** `app/Filament/Owner/Resources/TenantAddOns/TenantAddOnResource.php`.
- **Penambahan:**
  - Kolom status pembayaran, due date, invoice link di tabel utama.
  - Relation manager `PaymentsRelationManager` menampilkan histori pembayaran + tombol “Buka Invoice” dan “Salin Link”.
  - View page (`ViewTenantAddOn`) menyediakan aksi “Buka Invoice” dan “Kirim Pengingat” manual (menggunakan `AddOnPaymentReminderNotification`).
- **Limit Enforcement:** `CreateTenantAddOn` dan `ChecksPlanLimits` tetap sebagai guard utama, tapi owner sekarang melihat feedback visual bila payment pending/expired.

### Admin / Support Portal (Filament Admin)
- **Resource:** `app/Filament/Admin/Resources/AddOnPayments/AddOnPaymentResource.php`.
- **Fitur:**
  - Filter status, filter add-on, pencarian tenant.
  - Aksi cepat: “Resend Reminder” (email + notifikasi) dan “Mark as Failed”.
  - Export CSV/XLSX via `Tables\Actions\ExportAction` + `App\Filament\Admin\Exports\AddOnPaymentExporter`.
  - View detail memuat tenant, add-on, invoice metadata, timeline reminder.

### Logging & Monitoring
- **Channel Baru:** `payment` di `config/logging.php` (daily log, 30 hari retensi).
- **Create Flow:** `CreateTenantAddOn` mencatat keberhasilan / kegagalan saat membuat invoice Xendit.
- **Webhook Flow:** `XenditWebhookController` mencatat semua event add-on (paid, expired, failure) ke channel `payment`.
- **Job Reminder:** setiap pengiriman reminder / anomali tenant tanpa owner ikut tercatat.

### SOP Manual (Ops / Support)
1. **Owner meminta link ulang?**
   - Buka Filament Owner → `Add-ons` → pilih record → klik “Buka Invoice” atau “Kirim Pengingat”.
2. **Support ingin memantau seluruh tenant?**
   - Buka Filament Admin → `Add-on Payments`.
   - Gunakan filter “Overdue” untuk mendeteksi invoice akan kedaluwarsa.
   - Gunakan aksi “Resend Reminder” atau “Mark as Failed” sesuai prosedur.
3. **Audit / Rekonsiliasi Finance:**
   - Export data dari header action `Export CSV`.
   - File mencakup tenant, add-on, status, nominal, channel, timestamp reminder terakhir.


