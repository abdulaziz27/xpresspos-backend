# 📊 Scan Lengkap Tabel Checkout / Subscription / Billing / Invoice

**Tanggal Scan:** 2025-11-19  
**Tujuan:** Memastikan tidak ada duplikasi fungsi dan memahami struktur lengkap flow checkout → payment → subscription

---

## ✅ **KESIMPULAN: Tidak Ada Duplikasi**

**`landing_subscriptions` adalah satu-satunya tabel untuk log checkout.**

Tidak ada tabel lain yang fungsinya sama (misalnya `subscription_checkouts`, `tenant_orders`, `billing_carts`, dll).

---

## 📋 **TABEL RINGKAS - Flow Checkout → Subscription**

### **1. `landing_subscriptions` - Log Checkout / Intent Beli**

**Fungsi:** Tracking setiap kali user + tenant mau beli / upgrade plan

**Kolom Kunci:**
- ❌ `user_id` - **BELUM ADA** (perlu ditambah untuk flow authenticated)
- ❌ `tenant_id` - **BELUM ADA** (perlu ditambah untuk flow authenticated)
- ✅ `plan_id` - Ada (via migration `2025_10_26_080025`)
- ✅ `billing_cycle` - Ada (via migration `2025_10_26_080025`)
- ✅ `status` - Ada (pending/active/cancelled/expired/failed)
- ✅ `stage` - Ada (new/payment_pending/payment_completed/active)
- ✅ `payment_status` - Ada (denormalized, via migration `2025_10_25_154131`)
- ✅ `payment_amount` - Ada (denormalized)
- ✅ `subscription_id` - Ada (FK → subscriptions.id, nullable)
- ✅ `xendit_invoice_id` - Ada (nullable)

**Relasi:**
- `hasMany` → `subscription_payments` (via `landing_subscription_id`)
- `belongsTo` → `subscriptions` (via `subscription_id`)
- `belongsTo` → `users` (via `provisioned_user_id`) - **Hanya setelah provisioning**
- `belongsTo` → `stores` (via `provisioned_store_id`) - **Hanya setelah provisioning**

**Catatan:**
- Base migration (`2024_10_04_003500`) **tidak punya** `user_id` dan `tenant_id`
- Field `email`, `name`, `company`, `phone` masih ada (legacy dari flow anonymous)
- Field `plan` (string) masih ada, tapi sudah ada `plan_id` (via migration)

---

### **2. `subscription_payments` - Source of Truth Status Pembayaran**

**Fungsi:** Tracking payment via Xendit (status pembayaran yang sah)

**Kolom Kunci:**
- ✅ `landing_subscription_id` - FK → landing_subscriptions.id (nullable)
- ✅ `subscription_id` - FK → subscriptions.id (nullable, diisi setelah provisioning)
- ✅ `invoice_id` - FK → invoices.id (nullable)
- ✅ `status` - **SOURCE OF TRUTH** (pending/paid/expired/failed)
- ✅ `paid_at` - Timestamp pembayaran
- ✅ `amount` - Jumlah pembayaran
- ✅ `xendit_invoice_id` - ID invoice dari Xendit (UNIQUE)
- ✅ `external_id` - External ID untuk Xendit (UNIQUE)
- ✅ `gateway_response` - Response lengkap dari Xendit (JSON)

**Relasi:**
- `belongsTo` → `landing_subscriptions` (via `landing_subscription_id`)
- `belongsTo` → `subscriptions` (via `subscription_id`)
- `belongsTo` → `invoices` (via `invoice_id`)

**Catatan:**
- **Ini adalah source of truth untuk status pembayaran**
- `landing_subscriptions.payment_status` adalah denormalized copy (untuk query cepat)

---

### **3. `subscriptions` - Kontrak Berlangganan Aktif per Tenant**

**Fungsi:** Subscription resmi yang aktif untuk tenant

**Kolom Kunci:**
- ✅ `tenant_id` - FK → tenants.id (NOT NULL, cascade delete)
- ✅ `plan_id` - FK → plans.id (NOT NULL, restrict delete)
- ✅ `status` - active/inactive/cancelled/expired
- ✅ `billing_cycle` - monthly/annual
- ✅ `starts_at` - Tanggal mulai
- ✅ `ends_at` - Tanggal berakhir
- ✅ `amount` - Jumlah subscription
- ❌ `user_id` - **TIDAK ADA** (benar, karena subscription per tenant)
- ❌ `store_id` - **TIDAK ADA** (benar, karena subscription per tenant)

**Relasi:**
- `belongsTo` → `tenants` (via `tenant_id`)
- `belongsTo` → `plans` (via `plan_id`)
- `hasMany` → `subscription_usage` (tracking usage)
- `hasMany` → `invoices` (invoice untuk subscription)
- `hasMany` → `subscription_payments` (payment untuk subscription)

**Catatan:**
- **Subscription per tenant, bukan per store**
- Dibuat/diupdate **hanya** setelah payment `paid`

---

### **4. `invoices` - Invoice untuk Subscription**

**Fungsi:** Invoice resmi untuk subscription (billing document)

**Kolom Kunci:**
- ✅ `subscription_id` - FK → subscriptions.id (NOT NULL, cascade delete)
- ✅ `invoice_number` - Nomor invoice (UNIQUE)
- ✅ `status` - pending/paid/failed/refunded/cancelled
- ✅ `amount` - Jumlah invoice
- ✅ `total_amount` - Total termasuk tax
- ✅ `due_date` - Tanggal jatuh tempo
- ✅ `paid_at` - Timestamp pembayaran
- ❌ `tenant_id` - **TIDAK ADA** (bisa diakses via subscription.tenant_id)
- ❌ `user_id` - **TIDAK ADA** (bisa diakses via subscription.tenant.users)

**Relasi:**
- `belongsTo` → `subscriptions` (via `subscription_id`)
- `hasMany` → `subscription_payments` (via `invoice_id`)

**Catatan:**
- Invoice dibuat untuk subscription (bukan untuk landing_subscription)
- Bisa punya multiple `subscription_payments` (jika partial payment)

---

### **5. `plans` - Master Data Plan**

**Fungsi:** Data plan yang tersedia (pricing, features, limits)

**Kolom Kunci:**
- ✅ `id` - PK
- ✅ `name` - Nama plan
- ✅ `slug` - Slug plan (UNIQUE)
- ✅ `price` - Harga bulanan
- ✅ `annual_price` - Harga tahunan (nullable)
- ✅ `features` - JSON array features
- ✅ `limits` - JSON object limits
- ✅ `is_active` - Flag aktif/tidak

**Relasi:**
- `hasMany` → `subscriptions` (via `plan_id`)
- `hasMany` → `plan_features` (via `plan_id`)

**Catatan:**
- JSON `features` dan `limits` untuk marketing/display
- `plan_features` untuk queryable limit & feature flags

---

### **6. `plan_features` - Feature & Limit per Plan (Normalized)**

**Fungsi:** Feature flags dan limits yang bisa di-query

**Kolom Kunci:**
- ✅ `plan_id` - FK → plans.id (NOT NULL, cascade delete)
- ✅ `feature_code` - Kode feature (MAX_STORES, ALLOW_LOYALTY, dll)
- ✅ `limit_value` - Nilai limit (string, bisa angka atau "-1" untuk unlimited)
- ✅ `is_enabled` - Flag enabled/disabled

**Relasi:**
- `belongsTo` → `plans` (via `plan_id`)

**Catatan:**
- UNIQUE constraint: `(plan_id, feature_code)`
- Digunakan oleh `PlanLimitService` untuk check limit & feature

---

### **7. `subscription_usage` - Tracking Usage per Feature**

**Fungsi:** Tracking penggunaan quota per subscription

**Kolom Kunci:**
- ✅ `subscription_id` - FK → subscriptions.id (NOT NULL, cascade delete)
- ✅ `feature_type` - transactions/products/users, dll
- ✅ `current_usage` - Jumlah penggunaan saat ini
- ✅ `annual_quota` - Quota tahunan (nullable = unlimited)
- ✅ `soft_cap_triggered` - Flag soft cap sudah dipicu
- ❌ `tenant_id` - **TIDAK ADA** (bisa diakses via subscription.tenant_id)
- ❌ `plan_id` - **TIDAK ADA** (bisa diakses via subscription.plan_id)

**Relasi:**
- `belongsTo` → `subscriptions` (via `subscription_id`)

**Catatan:**
- Dibuat otomatis dari `plan_features` saat provisioning
- Digunakan untuk tracking soft cap (80% quota)

---

## 🔄 **FLOW CHECKOUT → SUBSCRIPTION**

```
1. User Login + Pilih Plan
   ↓
2. landing_subscriptions (intent / checkout)
   - user_id (perlu ditambah)
   - tenant_id (perlu ditambah)
   - plan_id
   - billing_cycle
   - status = 'pending'
   - stage = 'payment_pending'
   ↓
3. subscription_payments (status pembayaran)
   - landing_subscription_id
   - status = 'pending' → 'paid'
   - xendit_invoice_id
   ↓ (payment paid via webhook)
4. subscriptions (kontrak aktif)
   - tenant_id
   - plan_id
   - status = 'active'
   ↓
5. invoices (optional, untuk billing document)
   - subscription_id
   - status = 'paid'
   ↓
6. subscription_usage (tracking usage)
   - subscription_id
   - feature_type
   - current_usage = 0
```

---

## ⚠️ **YANG PERLU DITAMBAH DI `landing_subscriptions`**

Untuk flow authenticated (wajib login dulu), perlu tambah:

1. **`user_id`** (FK → users.id, nullable untuk backward compatibility)
2. **`tenant_id`** (FK → tenants.id, nullable untuk backward compatibility)

**Alasan:**
- Dengan flow authenticated, user & tenant sudah ada sebelum checkout
- `landing_subscriptions` jadi "log checkout" yang jelas: siapa (`user_id`) untuk bisnis mana (`tenant_id`) mau plan apa (`plan_id`)

---

## 📊 **TABEL YANG TIDAK TERKAIT (Untuk Konteks)**

### `orders` - POS Orders (Store Transactions)
- **Bukan** untuk subscription checkout
- Untuk transaksi penjualan di store (orders, order_items, payments)

### `payments` - Store Order Payments
- **Bukan** untuk subscription payment
- Untuk pembayaran order di store (bank transfer, cash, dll)
- **Terpisah** dari `subscription_payments`

---

## ✅ **KESIMPULAN FINAL**

1. **`landing_subscriptions` adalah satu-satunya tabel untuk log checkout** ✅
2. **Tidak ada duplikasi fungsi** ✅
3. **Yang perlu ditambah:** `user_id` dan `tenant_id` di `landing_subscriptions` untuk flow authenticated ✅
4. **Source of truth status payment:** `subscription_payments.status` ✅
5. **Kontrak resmi:** `subscriptions` (per tenant) ✅

---

**Next Step:** Buat migration untuk tambah `user_id` dan `tenant_id` ke `landing_subscriptions`.

