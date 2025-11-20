# Tabel Terkait Limitasi Plan

**Dokumen ini menjelaskan tabel-tabel yang digunakan untuk mengelola limitasi dan fitur plan subscription.**

---

## 📊 Overview: Arsitektur Limitasi Plan

Sistem limitasi plan menggunakan **2 pendekatan**:

1. **JSON-based (Legacy)** - `plans.features` dan `plans.limits` (JSON)
2. **Normalized (Recommended)** - `plan_features` table (queryable, lebih fleksibel)

**Rekomendasi:** Gunakan `plan_features` untuk limitasi yang perlu di-query atau di-validate secara real-time.

---

## 1️⃣ `plans` - Master Data Plan

**Tujuan:** Menyimpan data plan subscription (pricing, features, limits)

**Migration:** `2024_10_04_000400_create_plans_table.php`

### **Struktur Tabel:**

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `id` | bigint (PK) | ID unik plan |
| `name` | string | Nama plan (Basic, Pro, Enterprise) |
| `slug` | string (UNIQUE) | Slug untuk URL (basic, pro, enterprise) |
| `description` | text (nullable) | Deskripsi plan |
| `price` | decimal(10,2) | Harga bulanan |
| `annual_price` | decimal(10,2) (nullable) | Harga tahunan (jika ada diskon) |
| `features` | JSON | Array fitur yang tersedia (legacy) |
| `limits` | JSON | Object limitasi (legacy) |
| `is_active` | boolean | Status aktif plan |
| `sort_order` | integer | Urutan tampil di landing page |
| `created_at`, `updated_at` | timestamps | Audit timestamps |

### **Index:**
- `is_active` - Untuk filter plan aktif
- `sort_order` - Untuk sorting
- `slug` (UNIQUE) - Untuk lookup plan

### **Contoh Data `features` (JSON):**
```json
[
  "basic_reports",
  "inventory_management",
  "customer_management",
  "advanced_analytics",
  "multi_store",
  "api_access"
]
```

### **Contoh Data `limits` (JSON):**
```json
{
  "stores": 3,
  "products": 2000,
  "staff": 50,
  "orders_per_month": -1,
  "transactions_per_year": 10000
}
```

**Catatan:** 
- Nilai `-1` atau `null` = unlimited
- Nilai positif = hard limit

### **Relasi:**
- `hasMany` → `plan_features` (normalized features)
- `hasMany` → `subscriptions` (subscriptions yang pakai plan ini)

---

## 2️⃣ `plan_features` - Normalisasi Plan Features & Limits

**Tujuan:** Normalisasi features dan limits plan (queryable, lebih fleksibel dari JSON)

**Migration:** `2024_10_04_000450_create_plan_features_table.php`

### **Struktur Tabel:**

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `id` | bigint (PK) | ID unik feature |
| `plan_id` | bigint (FK) | ID plan (references `plans.id`) |
| `feature_code` | string | Kode feature (MAX_BRANCH, MAX_PRODUCTS, ALLOW_LOYALTY, dll) |
| `limit_value` | string (nullable) | Nilai limit (numeric/string; NULL = unlimited) |
| `is_enabled` | boolean | Apakah feature enabled |
| `created_at`, `updated_at` | timestamps | Audit timestamps |

### **Index:**
- `(plan_id, feature_code)` (UNIQUE) - Satu feature per plan

### **Feature Codes (Contoh):**

| Feature Code | Deskripsi | Limit Value |
|--------------|-----------|-------------|
| `MAX_STORES` | Maksimum jumlah store | `"3"` atau `"-1"` (unlimited) |
| `MAX_PRODUCTS` | Maksimum jumlah produk | `"2000"` atau `"-1"` (unlimited) |
| `MAX_STAFF` | Maksimum jumlah staff | `"50"` atau `"-1"` (unlimited) |
| `MAX_ORDERS_PER_MONTH` | Maksimum order per bulan | `"1000"` atau `"-1"` (unlimited) |
| `MAX_TRANSACTIONS_PER_YEAR` | Maksimum transaksi per tahun | `"10000"` atau `"-1"` (unlimited) |
| `ALLOW_LOYALTY` | Fitur loyalty program | `"1"` (enabled) atau `"0"` (disabled) |
| `ALLOW_ADVANCED_ANALYTICS` | Fitur advanced analytics | `"1"` atau `"0"` |
| `ALLOW_MULTI_STORE` | Fitur multi-store | `"1"` atau `"0"` |
| `ALLOW_API_ACCESS` | Fitur API access | `"1"` atau `"0"` |
| `ALLOW_CUSTOM_REPORTS` | Fitur custom reports | `"1"` atau `"0"` |
| `ALLOW_WHITE_LABEL` | Fitur white label | `"1"` atau `"0"` |
| `ALLOW_PRIORITY_SUPPORT` | Fitur priority support | `"1"` atau `"0"` |

### **Contoh Data:**

| plan_id | feature_code | limit_value | is_enabled |
|---------|--------------|-------------|------------|
| 1 | `MAX_STORES` | `"1"` | true |
| 1 | `MAX_PRODUCTS` | `"500"` | true |
| 1 | `ALLOW_LOYALTY` | `"1"` | true |
| 2 | `MAX_STORES` | `"3"` | true |
| 2 | `MAX_PRODUCTS` | `"2000"` | true |
| 2 | `ALLOW_MULTI_STORE` | `"1"` | true |
| 3 | `MAX_STORES` | `"-1"` | true |
| 3 | `MAX_PRODUCTS` | `"-1"` | true |
| 3 | `ALLOW_WHITE_LABEL` | `"1"` | true |

### **Relasi:**
- `belongsTo` → `plans`

### **Kegunaan:**
- Query limitasi plan secara real-time
- Validate feature access sebelum action
- Compare limitasi antar plan
- Dynamic feature gating

---

## 3️⃣ `subscriptions` - Subscription Aktif per Tenant

**Tujuan:** Menyimpan subscription aktif untuk tenant

**Migration:** `2024_10_04_000500_create_subscriptions_table.php`

**✅ Status:** Schema sudah final - Subscription per Tenant

**Model Bisnis:**
- **Subscription per Tenant** (bukan per Store)
- Satu tenant bisa punya banyak store, dan semua store dilindungi oleh satu subscription yang sama
- Migration `2024_10_04_000500_create_subscriptions_table.php` sudah diupdate langsung ke `tenant_id`

### **Struktur Tabel (Target Schema):**

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `id` | uuid (PK) | ID unik subscription |
| `tenant_id` | string(36) (FK, NOT NULL) | ID tenant (references `tenants.id`) |
| `plan_id` | bigint (FK) | ID plan (references `plans.id`) |
| `status` | enum | active/inactive/cancelled/expired |
| `billing_cycle` | enum | monthly/annual |
| `starts_at` | date | Tanggal mulai subscription |
| `ends_at` | date | Tanggal berakhir subscription |
| `trial_ends_at` | date (nullable) | Tanggal berakhir trial |
| `amount` | decimal(10,2) | Jumlah tagihan |
| `metadata` | JSON (nullable) | Metadata tambahan |
| `created_at`, `updated_at` | timestamps | Audit timestamps |

### **Index:**
- `(tenant_id, status)` - Untuk filter subscription aktif per tenant
- `status` - Untuk filter berdasarkan status
- `ends_at` - Untuk query subscription yang akan expired

### **Relasi:**
- `belongsTo` → `tenants` (subscription per tenant)
- `belongsTo` → `plans`
- `hasMany` → `subscription_usage` (tracking usage)
- `hasMany` → `invoices` (billing records)

**Relasi Hierarki:**
```
tenants (1) ──< (N) subscriptions
  │
  └──< (N) stores (semua store di tenant pakai subscription yang sama)
```

### **Kegunaan:**
- Menentukan plan yang aktif untuk tenant
- Validasi subscription status sebelum akses fitur
- Tracking billing cycle dan expiry
- **Semua store di tenant yang sama pakai subscription yang sama**

---

## 4️⃣ `subscription_usage` - Tracking Usage per Feature

**Tujuan:** Tracking penggunaan fitur per subscription (untuk soft cap / quota)

**Migration:** `2024_10_04_000600_create_subscription_usage_table.php`

### **Struktur Tabel:**

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `id` | uuid (PK) | ID unik usage record |
| `subscription_id` | uuid (FK) | ID subscription (references `subscriptions.id`) |
| `feature_type` | string | Tipe feature (transactions, orders, dll) |
| `current_usage` | integer | Usage saat ini |
| `annual_quota` | integer (nullable) | Quota tahunan (NULL = unlimited) |
| `subscription_year_start` | date | Awal tahun subscription (untuk reset quota) |
| `subscription_year_end` | date | Akhir tahun subscription |
| `soft_cap_triggered` | boolean | Apakah soft cap sudah triggered |
| `soft_cap_triggered_at` | timestamp (nullable) | Waktu soft cap triggered |
| `created_at`, `updated_at` | timestamps | Audit timestamps |

### **Index:**
- `(subscription_id, feature_type)` (UNIQUE) - Satu usage record per feature per subscription
- `soft_cap_triggered` - Untuk query subscription yang sudah trigger soft cap
- `subscription_year_end` - Untuk query subscription yang akan reset quota

### **Contoh Data:**

| subscription_id | feature_type | current_usage | annual_quota | soft_cap_triggered |
|-----------------|--------------|---------------|--------------|-------------------|
| uuid-1 | `transactions` | 8500 | 10000 | false |
| uuid-1 | `orders` | 450 | 500 | true |
| uuid-2 | `transactions` | 50000 | -1 (unlimited) | false |

### **Relasi:**
- `belongsTo` → `subscriptions`

### **Kegunaan:**
- Tracking usage per feature (misal: berapa transaction sudah dipakai dari quota tahunan)
- Soft cap untuk notifikasi saat mendekati limit (80%, 90%, 100%)
- Reset quota setiap tahun subscription
- Monitoring usage untuk upgrade recommendation

---

## 🔄 Flow Validasi Limitasi

### **1. Check Feature Access**

```
User Request → Check subscription → Get plan → Check plan_features.is_enabled
```

**Query:**
```sql
SELECT is_enabled 
FROM plan_features 
WHERE plan_id = ? AND feature_code = 'ALLOW_LOYALTY'
```

### **2. Check Hard Limit**

```
User Create Resource → Check subscription → Get plan → Get limit_value from plan_features
→ Compare dengan current count → Allow/Deny
```

**Query:**
```sql
-- Get limit
SELECT limit_value 
FROM plan_features 
WHERE plan_id = ? AND feature_code = 'MAX_PRODUCTS'

-- Get current count
SELECT COUNT(*) FROM products WHERE store_id = ?

-- Compare
IF current_count < limit_value THEN allow ELSE deny
```

### **3. Check Soft Cap (Quota)**

```
User Perform Action → Check subscription_usage → Get current_usage & annual_quota
→ Increment usage → Check soft cap (80%, 90%, 100%) → Trigger notification if needed
```

**Query:**
```sql
-- Get usage
SELECT current_usage, annual_quota 
FROM subscription_usage 
WHERE subscription_id = ? AND feature_type = 'transactions'

-- Update usage
UPDATE subscription_usage 
SET current_usage = current_usage + 1 
WHERE subscription_id = ? AND feature_type = 'transactions'

-- Check soft cap
IF current_usage >= (annual_quota * 0.8) THEN trigger_notification
```

---

## 📊 Relasi Antar Tabel

```
plans (1) ──< (N) plan_features
  │
  │ (1)
  │
tenants (1) ──< (N) subscriptions
  │              │
  │              │ (1)
  │              │
  │              └──< (N) subscription_usage
  │
  └──< (N) stores (semua store di tenant pakai subscription yang sama)
```

**Flow Query:**
1. `tenants` → `subscriptions` → `plans` → `plan_features` (untuk get limits)
2. `tenants` → `subscriptions` → `subscription_usage` (untuk get usage)

**Pattern Query:**
```php
// Get subscription dari tenant
$tenant = Tenant::find($tenantId);
$subscription = $tenant->activeSubscription; // relasi tenant -> subscriptions

// Get subscription dari store (via tenant)
$store = Store::find($storeId);
$tenant = $store->tenant;
$subscription = $tenant->activeSubscription;
```

---

## 🎯 Best Practices

### **1. Gunakan `plan_features` untuk Limitasi yang Perlu Di-Query**

**✅ Recommended:**
- Limitasi yang perlu di-validate real-time (MAX_STORES, MAX_PRODUCTS)
- Feature flags yang perlu di-check (ALLOW_LOYALTY, ALLOW_MULTI_STORE)

**❌ Tidak Perlu:**
- Metadata plan (description, pricing) → tetap di `plans` table

### **2. Hard Limit vs Soft Cap**

**Hard Limit:**
- Enforced di `plan_features.limit_value`
- Block action jika melebihi limit
- Contoh: MAX_STORES, MAX_PRODUCTS, MAX_STAFF

**Soft Cap:**
- Tracked di `subscription_usage`
- Tidak block action, hanya notifikasi
- **Trigger sekali** saat pertama kali lewat ambang (default 80%)
- Contoh: MAX_TRANSACTIONS_PER_YEAR

### **3. Feature Code Naming Convention**

**Format:** `{TYPE}_{RESOURCE}` atau `ALLOW_{FEATURE}`

**Konvensi:**
- `MAX_*` → Hard limit (numeric, -1 = unlimited)
- `ALLOW_*` → Feature flag (boolean, 1/0 atau is_enabled)

**Contoh:**
- `MAX_STORES` - Maximum stores
- `MAX_PRODUCTS` - Maximum products
- `MAX_STAFF` - Maximum staff
- `ALLOW_LOYALTY` - Allow loyalty program
- `ALLOW_MULTI_STORE` - Allow multi-store

### **4. Mapping `feature_code` vs `feature_type`**

**Konvensi:**
- `feature_code` (di `plan_features`) = Definisi limit/feature di plan
- `feature_type` (di `subscription_usage`) = Nama aktivitas yang di-track

**Mapping:**
- `MAX_TRANSACTIONS_PER_YEAR` (feature_code) ↔ `transactions` (feature_type)
- `MAX_ORDERS_PER_MONTH` (feature_code) ↔ `orders` (feature_type)

**Best Practice:**
- Untuk numeric limits yang di-track → gunakan nama aktivitas di `feature_type`
- Contoh: `transactions`, `orders`, `api_calls`, dll

### **5. Handling `limit_value` (String vs Integer)**

**Untuk Numeric Limits (MAX_*):**
- Store sebagai `string` di DB (fleksibel)
- **Cast ke integer di PHP layer:**
  ```php
  $limit = (int) $feature->limit_value; // -1 = unlimited, null = unlimited
  if ($limit <= 0) {
      // unlimited
  }
  ```

**Untuk Feature Flags (ALLOW_*):**
- Gunakan `is_enabled` boolean (primary)
- `limit_value` bisa diabaikan atau digunakan sebagai fallback
- **Best Practice:** Gunakan `is_enabled` saja untuk feature flags

---

## 📋 Daftar Feature Codes Resmi (v1 Minimal)

**Locked Feature Codes untuk implementasi awal:**

### **Hard Limits (MAX_*):**
1. `MAX_STORES` - Maksimum jumlah store per tenant
2. `MAX_PRODUCTS` - Maksimum jumlah produk per tenant
3. `MAX_STAFF` - Maksimum jumlah staff per tenant
4. `MAX_TRANSACTIONS_PER_YEAR` - Maksimum transaksi per tahun (soft cap)

### **Feature Flags (ALLOW_*):**
5. `ALLOW_LOYALTY` - Fitur loyalty program
6. `ALLOW_MULTI_STORE` - Fitur multi-store
7. `ALLOW_API_ACCESS` - Fitur API access

**Catatan:**
- Feature codes ini adalah **minimum viable** untuk v1
- Bisa ditambah lagi di future (ALLOW_ADVANCED_ANALYTICS, ALLOW_WHITE_LABEL, dll)
- Semua feature codes harus konsisten di semua plan (jika tidak ada, berarti tidak enabled)

---

## 📝 Contoh Implementasi

### **Check Limit Sebelum Create Store:**

```php
// Get tenant (dari store atau langsung)
$tenant = $store->tenant; // atau Tenant::find($tenantId)

// Get subscription
$subscription = $tenant->activeSubscription;

// Get plan
$plan = $subscription->plan;

// Get limit from plan_features
$maxStores = $plan->getFeatureLimit('MAX_STORES'); // Query plan_features

// Get current count
$currentStores = $tenant->stores()->count();

// Validate
if ($maxStores !== -1 && $currentStores >= $maxStores) {
    throw new LimitExceededException('Maximum stores limit reached');
}
```

### **Check Feature Access:**

```php
// Get tenant
$tenant = $store->tenant; // atau Tenant::find($tenantId)

// Get subscription
$subscription = $tenant->activeSubscription;

// Get plan
$plan = $subscription->plan;

// Check feature
if (!$plan->hasFeature('ALLOW_LOYALTY')) {
    throw new FeatureNotAvailableException('Loyalty program not available in current plan');
}
```

### **Track Usage (Soft Cap):**

```php
// Get tenant
$tenant = $store->tenant; // atau Tenant::find($tenantId)

// Get subscription
$subscription = $tenant->activeSubscription;

// Get plan
$plan = $subscription->plan;

// Get or create usage record
$usage = SubscriptionUsage::firstOrCreate([
    'subscription_id' => $subscription->id,
    'feature_type' => 'transactions', // Mapping dari MAX_TRANSACTIONS_PER_YEAR
], [
    'current_usage' => 0,
    'annual_quota' => $plan->getFeatureLimit('MAX_TRANSACTIONS_PER_YEAR'),
    'subscription_year_start' => $subscription->starts_at->startOfYear(),
    'subscription_year_end' => $subscription->starts_at->endOfYear(),
]);

// Increment usage
$usage->increment('current_usage');

// Check soft cap
if ($usage->annual_quota !== -1) {
    $percentage = ($usage->current_usage / $usage->annual_quota) * 100;
    
    if ($percentage >= 80 && !$usage->soft_cap_triggered) {
        // Trigger notification
        $usage->update([
            'soft_cap_triggered' => true,
            'soft_cap_triggered_at' => now(),
        ]);
        
        // Send notification
        SendQuotaWarningNotification::dispatch($subscription, $usage);
    }
}
```

---

## 🔍 Query Examples

### **Get All Limits untuk Plan:**

```sql
SELECT feature_code, limit_value, is_enabled
FROM plan_features
WHERE plan_id = 1
ORDER BY feature_code;
```

### **Get Usage untuk Subscription:**

```sql
SELECT feature_type, current_usage, annual_quota, 
       (current_usage / annual_quota * 100) as usage_percentage
FROM subscription_usage
WHERE subscription_id = 'uuid-here'
  AND annual_quota IS NOT NULL
  AND annual_quota > 0;
```

### **Get Subscription yang Mendekati Limit:**

```sql
SELECT s.id, s.tenant_id, p.name as plan_name,
       su.feature_type, su.current_usage, su.annual_quota,
       (su.current_usage / su.annual_quota * 100) as usage_percentage
FROM subscriptions s
JOIN plans p ON s.plan_id = p.id
JOIN subscription_usage su ON s.id = su.subscription_id
WHERE s.status = 'active'
  AND su.annual_quota IS NOT NULL
  AND su.annual_quota > 0
  AND (su.current_usage / su.annual_quota * 100) >= 80
ORDER BY usage_percentage DESC;
```

---

## 🔧 Service Layer: PlanLimitService

**File:** `app/Services/PlanLimitService.php`

Service ini menyediakan interface yang clean untuk handle plan limits dan feature gating.

### **Methods:**

#### **1. `hasFeature(Tenant|Store $entity, string $featureCode): bool`**
Check jika tenant punya feature enabled.

```php
$service = app(PlanLimitService::class);
$hasLoyalty = $service->hasFeature($tenant, 'ALLOW_LOYALTY');
```

#### **2. `limit(Tenant|Store $entity, string $featureCode): ?int`**
Get limit value untuk feature (returns -1 untuk unlimited, null jika tidak ditemukan).

```php
$maxStores = $service->limit($tenant, 'MAX_STORES');
// Returns: 3, -1 (unlimited), atau null (not found)
```

#### **3. `isWithinLimit(Tenant|Store $entity, string $featureCode, int $currentCount): bool`**
Check apakah current count masih dalam limit.

```php
$canCreateStore = $service->isWithinLimit($tenant, 'MAX_STORES', $currentStoreCount);
```

#### **4. `trackUsage(Tenant|Store $entity, string $featureType, int $amount = 1): ?SubscriptionUsage`**
Track usage untuk soft cap (auto-increment, auto-check thresholds).

```php
$usage = $service->trackUsage($tenant, 'transactions', 1);
// Auto-increment, auto-check soft cap (80%, 90%, 100%)
```

#### **5. `getUsage(Tenant|Store $entity, string $featureType): array`**
Get current usage data (current, quota, percentage).

```php
$usage = $service->getUsage($tenant, 'transactions');
// Returns: ['current' => 8500, 'quota' => 10000, 'percentage' => 85.0]
```

#### **6. `canPerformAction(Tenant|Store $entity, string $action, int $currentCount = 0): array`**
Comprehensive check (combines feature + limit check).

```php
$result = $service->canPerformAction($tenant, 'create_store', $currentStoreCount);
// Returns: ['allowed' => true/false, 'reason' => ..., 'message' => ..., 'limit' => ...]
```

### **Usage Examples:**

#### **Di Controller (Before Create Store):**
```php
public function store(Request $request)
{
    $tenant = auth()->user()->tenant;
    $service = app(PlanLimitService::class);
    
    $currentStores = $tenant->stores()->count();
    $result = $service->canPerformAction($tenant, 'create_store', $currentStores);
    
    if (!$result['allowed']) {
        return response()->json([
            'error' => $result['message'],
            'reason' => $result['reason'],
        ], 403);
    }
    
    // Proceed with store creation
}
```

#### **Di API (Before Create Order):**
```php
public function store(Request $request)
{
    $store = StoreContext::current();
    $tenant = $store->tenant;
    $service = app(PlanLimitService::class);
    
    // Track usage (soft cap)
    $service->trackUsage($tenant, 'transactions', 1);
    
    // Proceed with order creation
}
```

#### **Di Filament (Feature Gating):**
```php
public static function canView(): bool
{
    $tenant = auth()->user()->tenant;
    $service = app(PlanLimitService::class);
    
    return $service->hasFeature($tenant, 'ALLOW_LOYALTY');
}
```

---

**Last Updated:** Berdasarkan migration files dan service yang ada di codebase.

