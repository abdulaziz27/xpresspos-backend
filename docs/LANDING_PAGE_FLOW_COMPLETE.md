# Landing Page Flow - Lengkap dengan Semua Tabel

**Dokumen ini menjelaskan flow lengkap dari Landing Page hingga Owner bisa login, termasuk semua tabel yang terlibat.**

---

## 🎯 Kebutuhan yang Dicakup

**Model Bisnis:**
- **Subscription per Tenant** (bukan per Store)
- Satu tenant bisa punya banyak store, semua dilindungi oleh satu subscription yang sama
- Subscriptions TIDAK di-link ke store, tapi ke tenant

1. **Landing Page (Public)**
   - Menampilkan pricing plans
   - Visitor bisa pilih plan & checkout
   - Visitor belum punya akun
   - **Catatan:** Saat ini landing/checkout hanya mendukung *new signups*. Upgrade/downgrade existing akan di-handle dari dashboard, bukan dari landing.

2. **Checkout & Payment**
   - Multi-step checkout (plan selection → business info → payment)
   - Integrasi dengan Xendit untuk payment gateway
   - Tracking payment status
   - State machine untuk handle happy path dan unhappy path (payment failed, expired, dll)

3. **Provisioning (Auto setelah Payment)**
   - Auto-create tenant, store, user (owner)
   - Auto-create subscription (linked ke tenant, bukan store)
   - Auto-setup access control
   - Email notification (welcome email, login credentials, setup guide)

4. **Owner Dashboard**
   - Owner bisa login dengan email & temporary password
   - Owner bisa setup bisnis (products, staff, dll)

---

## 📊 Flow Lengkap: Landing → Owner Dashboard

### **Tahap 1: Landing Page (Public - No Auth)**

**Tujuan:** Visitor melihat pricing plans dan memilih plan.

**Tabel yang Dibaca:**
- `plans` - Query: `Plan::active()->ordered()->get()`

**Aksi:**
- Visitor melihat daftar plans dengan harga bulanan/tahunan
- Visitor klik "Beli" pada plan yang dipilih

**Tabel yang Ditulis:**
- ❌ Tidak ada (hanya read-only)

---

### **Tahap 2: Checkout Step 1 - Plan Selection**

**Tujuan:** Visitor memilih plan dan billing cycle (monthly/yearly).

**Tabel yang Dibaca:**
- `plans` - Get plan detail berdasarkan slug

**Aksi:**
- Visitor pilih plan (basic/pro/enterprise)
- Visitor pilih billing cycle (monthly/yearly)
- Data disimpan di session

**Tabel yang Ditulis:**
- ❌ Tidak ada (data di session)

---

### **Tahap 3: Checkout Step 2 - Business Information**

**Tujuan:** Visitor mengisi data bisnis mereka.

**Tabel yang Dibaca:**
- `landing_subscriptions` - Cek apakah email sudah pernah daftar (optional, untuk prevent duplicate)

**Tabel yang Ditulis:**
- ✅ `landing_subscriptions` - Insert record baru:
  - `email` - Email visitor
  - `name` - Nama visitor
  - `company` - Nama bisnis
  - `phone` - Nomor telepon
  - `country` - Negara
  - `plan` atau `plan_id` - Plan yang dipilih (dari step 1)
  - `status` = `'pending_payment'`
  - `stage` = `'payment_pending'`
  - `meta` (JSON) - `business_type`, `billing_cycle`, dll
  - `payment_amount` - Jumlah yang harus dibayar (dari plan price)

**Catatan:**
- Record ini adalah "lead" yang belum bayar
- Status akan berubah setelah payment berhasil

---

### **Tahap 4: Checkout Step 3 - Payment Method Selection**

**Tujuan:** Visitor pilih payment method dan create invoice di Xendit.

**Tabel yang Dibaca:**
- `landing_subscriptions` - Get record dari step 2
- `plans` - Get plan detail untuk calculate amount

**Tabel yang Ditulis:**
- ✅ `subscription_payments` - Insert record baru:
  - `landing_subscription_id` - FK ke `landing_subscriptions.id`
  - `xendit_invoice_id` - ID invoice dari Xendit API (UNIQUE)
  - `external_id` - External ID untuk Xendit (UNIQUE)
  - `payment_method` - bank_transfer/e_wallet/qris/credit_card
  - `payment_channel` - BCA, OVO, DANA, dll (nullable)
  - `amount` - Jumlah pembayaran
  - `gateway_fee` - Fee dari Xendit (default 0)
  - `status` = `'pending'`
  - `expires_at` - Timestamp kadaluarsa dari Xendit
  - `gateway_response` (JSON) - Response lengkap dari Xendit

**Aksi:**
- Sistem call Xendit API untuk create invoice
- Visitor di-redirect ke payment page Xendit atau QR code

---

### **Tahap 5: Payment Webhook (Xendit → Backend)**

**Tujuan:** Xendit mengirim webhook saat payment berhasil, backend update status.

**Tabel yang Dibaca:**
- `subscription_payments` - Cari berdasarkan `xendit_invoice_id` dari webhook
- `landing_subscriptions` - Get dari `subscription_payments.landing_subscription_id`

**Tabel yang Ditulis:**
- ✅ `subscription_payments` - Update:
  - `status` = `'paid'`
  - `paid_at` = timestamp sekarang
  - `gateway_response` = update dengan response terbaru dari webhook
- ✅ `landing_subscriptions` - Update:
  - `status` = `'provisioned'` atau `'active'`
  - `stage` = `'active'`
  - `paid_at` = timestamp sekarang
  - `payment_status` = `'paid'` (jika ada kolom ini)

**Aksi:**
- Setelah payment berhasil, sistem trigger provisioning (Tahap 6)

---

### **Tahap 6: Provisioning (Auto setelah Payment Success)**

**Tujuan:** Sistem otomatis create tenant, store, user (owner), dan subscription.

**⚠️ CATATAN PENTING:** Berdasarkan review sebelumnya, ada gap di service yang ada. Flow di bawah ini adalah **flow yang seharusnya** berdasarkan schema.

#### 6.1. Create Tenant
**Tabel yang Ditulis:**
- ✅ `tenants` - Insert record baru:
  - `id` = UUID baru (36 chars)
  - `name` = dari `landing_subscriptions.company` atau `landing_subscriptions.name`
  - `email` = dari `landing_subscriptions.email`
  - `phone` = dari `landing_subscriptions.phone` (nullable)
  - `status` = `'active'` (default)
  - `settings` (JSON) - konfigurasi tenant

**Kegunaan:**
- Tenant adalah root entity untuk multi-tenant architecture
- Semua store akan belong ke tenant ini

---

#### 6.2. Create User (Owner)
**Tabel yang Ditulis:**
- ✅ `users` - Insert record baru:
  - `name` = dari `landing_subscriptions.name`
  - `email` = dari `landing_subscriptions.email` (harus unique)
  - `password` = hash dari temporary password (random 12 chars)
  - `email_verified_at` = `now()` (auto-verify karena sudah bayar)
  - `store_id` = `NULL` (legacy field, tidak digunakan karena pakai `store_user_assignments`)

**Kegunaan:**
- User adalah akun owner yang akan login ke dashboard
- Temporary password akan dikirim via email

**Aksi Tambahan:**
- Assign role `owner` via Spatie permissions

---

#### 6.3. Link User ke Tenant
**Tabel yang Ditulis:**
- ✅ `user_tenant_access` - Insert record:
  - `id` = UUID baru
  - `user_id` = ID user yang baru dibuat
  - `tenant_id` = ID tenant yang baru dibuat
  - `role` = `'owner'` (default)

**Kegunaan:**
- Memberikan akses tenant-level ke user
- User bisa melihat semua store di tenant (metadata)
- Tapi untuk akses operasional store, tetap butuh `store_user_assignments`

---

#### 6.4. Create Store
**Tabel yang Ditulis:**
- ✅ `stores` - Insert record baru:
  - `id` = UUID baru
  - `tenant_id` = ID tenant yang baru dibuat (**REQUIRED, NOT NULL**)
  - `name` = dari `landing_subscriptions.company` atau `"{name}'s Store"`
  - `code` = generate unique code (50 chars, unique di seluruh sistem)
  - `email` = dari `landing_subscriptions.email` (harus unique)
  - `phone` = dari `landing_subscriptions.phone` (nullable)
  - `timezone` = `'Asia/Jakarta'` (default)
  - `currency` = `'IDR'` (default)
  - `status` = `'active'` (default)
  - `settings` (JSON) - konfigurasi store

**Kegunaan:**
- Store adalah outlet/cabang yang akan digunakan owner untuk operasional POS
- Store ini akan digunakan untuk semua transaksi, products, orders, dll

---

#### 6.5. Link User ke Store
**Tabel yang Ditulis:**
- ✅ `store_user_assignments` - Insert record:
  - `id` = UUID baru
  - `store_id` = ID store yang baru dibuat
  - `user_id` = ID user yang baru dibuat
  - `assignment_role` = `'owner'` (default `'staff'`, tapi untuk owner harus `'owner'`)
  - `is_primary` = `true` (karena ini store pertama)

**Kegunaan:**
- Memberikan akses store-level ke user
- User bisa akses data operasional store (orders, inventory, products, dll)
- `is_primary` menentukan store default saat user login

---

#### 6.6. Create Subscription
**Tabel yang Ditulis:**
- ✅ `subscriptions` - Insert record:
  - `id` = UUID baru
  - `tenant_id` = ID tenant yang baru dibuat (**REQUIRED, NOT NULL** - dari migration line 13)
  - `plan_id` = ID plan yang dipilih
  - `status` = `'active'`
  - `billing_cycle` = `'monthly'` atau `'annual'` (dari `landing_subscriptions.meta`)
  - `starts_at` = `now()`
  - `ends_at` = `now()->addMonth()` atau `now()->addYear()` (tergantung billing cycle)
  - `amount` = dari `subscription_payments.amount`
  - `metadata` (JSON) - `landing_subscription_id`, `subscription_payment_id`, dll

**⚠️ CATATAN:** Di migration `subscriptions` ada `tenant_id` (NOT NULL), tapi di service yang ada mereka pakai `store_id` (yang tidak ada di migration). Ini perlu diperbaiki.

**Kegunaan:**
- Subscription menentukan plan yang aktif untuk tenant
- Subscription menentukan limit features (products, users, transactions, dll)
- Subscription menentukan billing cycle dan expiry date

---

#### 6.7. Create Subscription Usage
**Tabel yang Ditulis:**
- ✅ `subscription_usage` - Insert record:
  - `id` = UUID baru
  - `subscription_id` = ID subscription yang baru dibuat
  - `feature_type` = `'transactions'`, `'products'`, `'users'`, dll (tergantung plan features)
  - `current_usage` = `0`
  - `annual_quota` = dari `plan_features.limit_value` atau `plans.limits` (JSON)
  - `subscription_year_start` = `now()`
  - `subscription_year_end` = `now()->addYear()`
  - `soft_cap_triggered` = `false`

**Kegunaan:**
- Tracking usage per feature (misal: berapa transaction sudah dipakai dari quota tahunan)
- Soft cap untuk notifikasi saat mendekati limit
- Reset setiap tahun subscription

---

#### 6.8. Create Invoice (Optional - untuk record keeping)
**Tabel yang Ditulis:**
- ✅ `invoices` - Insert record:
  - `id` = UUID baru
  - `subscription_id` = ID subscription yang baru dibuat
  - `invoice_number` = generate unique invoice number
  - `amount` = dari `subscription_payments.amount`
  - `tax_amount` = `0` (default, bisa diisi jika ada tax)
  - `total_amount` = `amount + tax_amount`
  - `status` = `'paid'` (karena sudah bayar)
  - `due_date` = `now()`
  - `paid_at` = timestamp sekarang
  - `line_items` (JSON) - detail items invoice

**Kegunaan:**
- Invoice untuk record keeping dan accounting
- Bisa digunakan untuk laporan keuangan

---

#### 6.9. Update Landing Subscription
**Tabel yang Ditulis:**
- ✅ `landing_subscriptions` - Update:
  - `status` = `'provisioned'` atau `'active'`
  - `stage` = `'active'`
  - `provisioned_store_id` = ID store yang baru dibuat
  - `provisioned_user_id` = ID user yang baru dibuat
  - `provisioned_at` = timestamp sekarang
  - `onboarding_url` = generate URL untuk onboarding
  - `subscription_id` = ID subscription yang baru dibuat (jika ada kolom ini)

**Kegunaan:**
- Tracking bahwa lead sudah di-provision
- Link ke store dan user yang dibuat
- Onboarding URL untuk redirect owner setelah login pertama

---

#### 6.10. Update Subscription Payment
**Tabel yang Ditulis:**
- ✅ `subscription_payments` - Update:
  - `subscription_id` = ID subscription yang baru dibuat
  - `invoice_id` = ID invoice yang baru dibuat (jika ada)

**Kegunaan:**
- Link payment ke subscription dan invoice
- Tracking payment untuk subscription tertentu

---

### **Tahap 7: Owner Login ke Dashboard**

**Tujuan:** Owner login dengan email & temporary password, kemudian bisa akses dashboard.

**Tabel yang Dibaca:**
- ✅ `users` - Authenticate user (email + password)
- ✅ `user_tenant_access` - Cek apakah user punya akses ke tenant (role `owner`)
- ✅ `store_user_assignments` - Get list store yang bisa diakses user
- ✅ `stores` - Get detail store (nama, status, dll)
- ✅ `tenants` - Get detail tenant (jika perlu)
- ✅ `subscriptions` - Cek apakah tenant punya active subscription
- ✅ `plans` - Get plan detail untuk display di dashboard
- ✅ `subscription_usage` - Get usage stats (berapa transaction sudah dipakai, dll)

**Tabel yang Ditulis:**
- ✅ Session - Set store context (via `StoreContext` service)
- ✅ `users` - Update `last_login_at` (jika ada kolom ini)

**Aksi:**
- Owner login dengan email & temporary password
- Sistem set store context ke primary store
- Owner di-redirect ke dashboard
- Dashboard menampilkan:
  - Store info (nama, status, dll)
  - Subscription info (plan, billing cycle, expiry date)
  - Usage stats (berapa transaction sudah dipakai dari quota)
  - Quick actions (setup products, add staff, dll)

---

## 📋 Daftar Lengkap Tabel yang Terlibat

### **1. `plans` - Pricing Plans**

**Isi Tabel:**
- `id` (PK, bigint)
- `name` (string) - Nama plan (Basic, Pro, Enterprise)
- `slug` (string, UNIQUE) - URL-friendly identifier
- `description` (text, nullable) - Deskripsi plan
- `price` (decimal 10,2) - Harga bulanan
- `annual_price` (decimal 10,2, nullable) - Harga tahunan
- `features` (JSON) - Array fitur yang included
- `limits` (JSON) - Limit per fitur (products, users, transactions, dll)
- `is_active` (boolean, default true) - Status aktif
- `sort_order` (integer, default 0) - Urutan tampil
- `created_at`, `updated_at` (timestamps)

**Index:**
- `is_active`
- `sort_order`

**Relasi:**
- `hasMany` → `plan_features`
- `hasMany` → `subscriptions`

**Kegunaan:**
- Menyimpan data pricing plans yang ditampilkan di landing page
- Digunakan untuk calculate harga saat checkout
- Digunakan untuk determine features & limits saat provisioning

---

### **2. `plan_features` - Detail Features per Plan**

**Isi Tabel:**
- `id` (PK, bigint)
- `plan_id` (FK → `plans.id`, cascade delete)
- `feature_code` (string) - Kode fitur (MAX_BRANCH, MAX_PRODUCTS, ALLOW_LOYALTY, dll)
- `limit_value` (string, nullable) - Nilai limit (nullable = unlimited)
- `is_enabled` (boolean, default true) - Status enabled
- `created_at`, `updated_at` (timestamps)

**Constraint:**
- UNIQUE (`plan_id`, `feature_code`)

**Relasi:**
- `belongsTo` → `plans`

**Kegunaan:**
- Normalized features per plan (bukan JSON)
- Memudahkan query untuk cek feature tertentu
- Memudahkan update limit per feature

---

### **3. `landing_subscriptions` - Lead dari Landing Page**

**Isi Tabel:**
- `id` (PK, bigint)
- `email` (string) - Email lead
- `name` (string, nullable) - Nama lead
- `company` (string, nullable) - Nama bisnis
- `phone` (string, nullable) - Nomor telepon
- `country` (string, nullable) - Negara
- `preferred_contact_method` (string, nullable) - email/phone/whatsapp
- `notes` (text, nullable) - Catatan tambahan
- `follow_up_logs` (JSON, nullable) - Log follow-up
- `plan` atau `plan_id` (string, nullable) - Plan yang dipilih
- `status` (string, default 'pending') - pending/provisioned/active
- `stage` (string, default 'new') - new/payment_pending/active
- `meta` (JSON, nullable) - Metadata tambahan (business_type, billing_cycle, dll)
- `processed_at` (timestamp, nullable) - Timestamp diproses
- `processed_by` (FK → `users.id`, nullable, null on delete)
- `provisioned_store_id` (FK → `stores.id`, nullable, null on delete)
- `provisioned_user_id` (FK → `users.id`, nullable, null on delete)
- `provisioned_at` (timestamp, nullable) - Timestamp provisioning
- `onboarding_url` (string, nullable) - URL onboarding
- `xendit_invoice_id` (string, nullable) - ID invoice Xendit
- `payment_status` (string, nullable) - Status payment
- `payment_amount` (decimal, nullable) - Jumlah pembayaran
- `paid_at` (timestamp, nullable) - Timestamp pembayaran
- `subscription_id` (FK → `subscriptions.id`, nullable) - Subscription yang dibuat
- `created_at`, `updated_at` (timestamps)

**Index:**
- `email`
- `status`
- `stage`

**Relasi:**
- `belongsTo` → `users` (processed_by)
- `belongsTo` → `stores` (provisioned_store_id)
- `belongsTo` → `users` (provisioned_user_id)
- `belongsTo` → `subscriptions` (subscription_id)
- `hasMany` → `subscription_payments`

**Kegunaan:**
- Tracking lead dari landing page
- Menyimpan data visitor sebelum mereka punya akun
- Link ke store dan user yang dibuat setelah provisioning
- Tracking status dari pending → payment → provisioned → active

---

### **4. `subscription_payments` - Payment via Xendit**

**Isi Tabel:**
- `id` (PK, UUID)
- `landing_subscription_id` (FK → `landing_subscriptions.id`, nullable, set null on delete)
- `subscription_id` (FK → `subscriptions.id`, nullable, set null on delete)
- `invoice_id` (FK → `invoices.id`, nullable, set null on delete)
- `xendit_invoice_id` (string, UNIQUE) - ID invoice dari Xendit
- `external_id` (string, UNIQUE) - External ID untuk Xendit
- `payment_method` (enum) - bank_transfer/e_wallet/qris/credit_card
- `payment_channel` (string, nullable) - BCA, OVO, DANA, dll
- `amount` (decimal 15,2) - Jumlah pembayaran
- `gateway_fee` (decimal 8,2, default 0) - Fee dari Xendit
- `status` (enum, default 'pending') - pending/paid/expired/failed
- `gateway_response` (JSON, nullable) - Response lengkap dari Xendit
- `paid_at` (timestamp, nullable) - Timestamp pembayaran
- `expires_at` (timestamp, nullable) - Timestamp kadaluarsa
- `created_at`, `updated_at` (timestamps)

**Index:**
- `landing_subscription_id`
- `subscription_id`
- `invoice_id`
- `xendit_invoice_id`
- `external_id`
- `status`
- `payment_method`
- `paid_at`
- `expires_at`

**Relasi:**
- `belongsTo` → `landing_subscriptions`
- `belongsTo` → `subscriptions`
- `belongsTo` → `invoices`

**Kegunaan:**
- Tracking payment via Xendit
- Menyimpan response dari Xendit untuk audit
- Link ke landing subscription, subscription, dan invoice
- Tracking status payment dari pending → paid

---

### **5. `tenants` - Root Entity Multi-Tenancy**

**Isi Tabel:**
- `id` (PK, string 36 chars, UUID)
- `name` (string) - Nama tenant/brand
- `email` (string, nullable) - Email tenant
- `phone` (string, nullable) - Phone tenant
- `settings` (JSON, nullable) - Konfigurasi tenant
- `status` (string, default 'active') - active/inactive/suspended
- `created_at`, `updated_at` (timestamps)

**Index:**
- `(status, created_at)`

**Relasi:**
- `hasMany` → `stores` (via `stores.tenant_id`)
- `belongsToMany` → `users` (via pivot `user_tenant_access`)
- `hasMany` → `subscriptions` (via `subscriptions.tenant_id`)

**Kegunaan:**
- Root entity untuk multi-tenant architecture
- Semua store belong ke tenant
- Subscription belong ke tenant (bukan store)

---

### **6. `users` - User Sistem**

**Isi Tabel:**
- `id` (PK, bigint)
- `store_id` (FK → `stores.id`, nullable, cascade delete) - **LEGACY FIELD**
- `name` (string) - Nama user
- `email` (string, UNIQUE) - Email user
- `email_verified_at` (timestamp, nullable) - Timestamp verifikasi email
- `password` (string) - Password hashed
- `remember_token` (string, nullable) - Token untuk remember me
- `created_at`, `updated_at` (timestamps)

**Index:**
- `store_id`

**Relasi:**
- `belongsTo` → `stores` (via `store_id` - legacy)
- `hasMany` → `store_user_assignments`
- `belongsToMany` → `stores` (via pivot `store_user_assignments`)
- `belongsToMany` → `tenants` (via pivot `user_tenant_access`)

**Kegunaan:**
- Akun user yang bisa login ke sistem
- Owner dibuat saat provisioning
- Staff bisa di-invite kemudian

---

### **7. `user_tenant_access` - Pivot User-Tenant dengan Role**

**Isi Tabel:**
- `id` (PK, string 36 chars, UUID)
- `user_id` (FK → `users.id`, cascade delete)
- `tenant_id` (FK → `tenants.id`, cascade delete)
- `role` (string, default 'owner') - owner/admin/accountant/viewer
- `created_at`, `updated_at` (timestamps)

**Constraint:**
- UNIQUE (`user_id`, `tenant_id`)

**Index:**
- `(tenant_id, role)`

**Relasi:**
- `belongsTo` → `users`
- `belongsTo` → `tenants`

**Kegunaan:**
- Memberikan akses tenant-level ke user
- User bisa melihat semua store di tenant (metadata)
- Tapi untuk akses operasional store, tetap butuh `store_user_assignments`

---

### **8. `stores` - Store/Branch per Tenant**

**Isi Tabel:**
- `id` (PK, UUID)
- `tenant_id` (string 36 chars, NOT NULL, FK → `tenants.id`, cascade delete)
- `name` (string) - Nama store
- `code` (string 50, UNIQUE) - Kode store (unique di seluruh sistem)
- `email` (string, UNIQUE) - Email store
- `phone` (string, nullable) - Phone store
- `address` (text, nullable) - Alamat store
- `logo` (string, nullable) - Path logo store
- `timezone` (string 50, default 'Asia/Jakarta') - Timezone store
- `currency` (string 3, default 'IDR') - Mata uang store
- `settings` (JSON, nullable) - Konfigurasi store
- `status` (enum, default 'active') - active/inactive/suspended
- `created_at`, `updated_at` (timestamps)

**Index:**
- `(tenant_id, status)`
- `status`
- `created_at`

**Relasi:**
- `belongsTo` → `tenants` (via `tenant_id`)
- `hasMany` → `users` (via `users.store_id` - legacy)
- `hasMany` → `store_user_assignments`
- `belongsToMany` → `users` (via pivot `store_user_assignments`)
- `hasMany` → `subscriptions` (via `subscriptions.store_id` - **TAPI INI TIDAK ADA DI MIGRATION**)

**Kegunaan:**
- Store adalah outlet/cabang untuk operasional POS
- Semua data operasional (orders, products, inventory) belong ke store
- Store belong ke tenant

---

### **9. `store_user_assignments` - Pivot User-Store dengan Assignment Role**

**Isi Tabel:**
- `id` (PK, UUID)
- `store_id` (FK → `stores.id`, cascade delete)
- `user_id` (FK → `users.id`, cascade delete)
- `assignment_role` (string, default 'staff') - staff/manager/admin/owner
- `is_primary` (boolean, default false) - Flag store utama
- `created_at`, `updated_at` (timestamps)

**Constraint:**
- UNIQUE (`store_id`, `user_id`)

**Index:**
- `(user_id, assignment_role)`
- `(store_id, assignment_role)`
- `(user_id, store_id)`

**Relasi:**
- `belongsTo` → `stores`
- `belongsTo` → `users`

**Kegunaan:**
- Memberikan akses store-level ke user
- User bisa akses data operasional store (orders, inventory, products, dll)
- `is_primary` menentukan store default saat user login

---

### **10. `subscriptions` - Subscription per Tenant**

**Isi Tabel:**
- `id` (PK, UUID)
- `tenant_id` (string 36 chars, NOT NULL, FK → `tenants.id`, cascade delete)
- `plan_id` (FK → `plans.id`, restrict delete)
- `status` (enum, default 'active') - active/inactive/cancelled/expired
- `billing_cycle` (enum, default 'monthly') - monthly/annual
- `starts_at` (date) - Tanggal mulai
- `ends_at` (date) - Tanggal berakhir
- `trial_ends_at` (date, nullable) - Tanggal trial berakhir
- `amount` (decimal 10,2) - Jumlah subscription
- `metadata` (JSON, nullable) - Metadata tambahan
- `created_at`, `updated_at` (timestamps)

**⚠️ CATATAN:** Di migration ada `tenant_id` (NOT NULL), tapi di service yang ada mereka pakai `store_id` (yang tidak ada di migration). Ini perlu diperbaiki.

**Index:**
- `(tenant_id, status)`
- `status`
- `ends_at`

**Relasi:**
- `belongsTo` → `tenants` (via `tenant_id`)
- `belongsTo` → `plans`
- `hasMany` → `subscription_usage`
- `hasMany` → `invoices`
- `hasMany` → `subscription_payments`

**Kegunaan:**
- Subscription menentukan plan yang aktif untuk tenant
- Subscription menentukan limit features (products, users, transactions, dll)
- Subscription menentukan billing cycle dan expiry date

---

### **11. `subscription_usage` - Tracking Usage per Feature**

**Isi Tabel:**
- `id` (PK, UUID)
- `subscription_id` (FK → `subscriptions.id`, cascade delete)
- `feature_type` (string) - transactions/products/users/categories, dll
- `current_usage` (integer, default 0) - Usage saat ini
- `annual_quota` (integer, nullable) - Quota tahunan (nullable = unlimited)
- `subscription_year_start` (date) - Tanggal mulai tahun subscription
- `subscription_year_end` (date) - Tanggal berakhir tahun subscription
- `soft_cap_triggered` (boolean, default false) - Flag soft cap triggered
- `soft_cap_triggered_at` (timestamp, nullable) - Timestamp soft cap triggered
- `created_at`, `updated_at` (timestamps)

**Constraint:**
- UNIQUE (`subscription_id`, `feature_type`)

**Index:**
- `soft_cap_triggered`
- `subscription_year_end`

**Relasi:**
- `belongsTo` → `subscriptions`

**Kegunaan:**
- Tracking usage per feature (misal: berapa transaction sudah dipakai dari quota tahunan)
- Soft cap untuk notifikasi saat mendekati limit
- Reset setiap tahun subscription

---

### **12. `invoices` - Invoice untuk Subscription**

**Isi Tabel:**
- `id` (PK, UUID)
- `subscription_id` (FK → `subscriptions.id`, cascade delete)
- `invoice_number` (string, UNIQUE) - Nomor invoice
- `amount` (decimal 10,2) - Jumlah invoice
- `tax_amount` (decimal 10,2, default 0) - Jumlah tax
- `total_amount` (decimal 10,2) - Total amount (amount + tax)
- `status` (enum, default 'pending') - pending/paid/failed/refunded/cancelled
- `due_date` (date) - Tanggal jatuh tempo
- `paid_at` (timestamp, nullable) - Timestamp pembayaran
- `line_items` (JSON) - Detail items invoice
- `metadata` (JSON, nullable) - Metadata tambahan
- `created_at`, `updated_at` (timestamps)

**Index:**
- `(subscription_id, status)`
- `status`
- `due_date`
- `paid_at`

**Relasi:**
- `belongsTo` → `subscriptions`
- `hasMany` → `subscription_payments` (via `subscription_payments.invoice_id`)

**Kegunaan:**
- Invoice untuk record keeping dan accounting
- Bisa digunakan untuk laporan keuangan
- Link ke subscription payment

---

## 🔄 State Machine: `landing_subscriptions` & `subscription_payments` (Happy + Unhappy Path)

### **State Machine `landing_subscriptions.status`**

| Status | Deskripsi | Trigger |
|--------|-----------|---------|
| `pending` | Lead baru, belum submit business info | Initial state saat create record |
| `pending_payment` | Business info sudah diisi, menunggu payment | Setelah step 2 (business info) |
| `payment_pending` | Payment invoice sudah dibuat, menunggu pembayaran | Setelah create Xendit invoice |
| `payment_failed` | Payment gagal (insufficient funds, dll) | Webhook Xendit: payment failed |
| `payment_expired` | Invoice expired, user belum bayar | Webhook Xendit: invoice expired |
| `paid` | Payment berhasil | Webhook Xendit: payment paid |
| `provisioned` | Tenant, store, user sudah dibuat | Setelah provisioning service selesai |
| `active` | Semua setup selesai, owner bisa login | Final state |

### **State Machine `subscription_payments.status`**

| Status | Deskripsi | Trigger |
|--------|-----------|---------|
| `pending` | Invoice Xendit sudah dibuat, menunggu pembayaran | Setelah create Xendit invoice |
| `paid` | Payment berhasil | Webhook Xendit: payment paid |
| `expired` | Invoice expired | Webhook Xendit: invoice expired |
| `failed` | Payment gagal | Webhook Xendit: payment failed |

### **State Transitions (Happy Path)**

```
landing_subscriptions:
  pending → pending_payment → payment_pending → paid → provisioned → active

subscription_payments:
  pending → paid
```

### **State Transitions (Unhappy Path)**

**Skenario 1: Payment Failed**
```
subscription_payments: pending → failed
landing_subscriptions: payment_pending → payment_failed
```
**Action:**
- Lead tetap di `landing_subscriptions` dengan status `payment_failed`
- Sales team bisa follow-up manual
- User bisa retry payment (create new `subscription_payments` record)

**Skenario 2: Invoice Expired**
```
subscription_payments: pending → expired
landing_subscriptions: payment_pending → payment_expired
```
**Action:**
- Lead dianggap "cold" (butuh follow-up sales)
- Tidak auto-create payment baru
- Sales team bisa follow-up atau create payment baru manual

**Skenario 3: Provisioning Failed**
```
landing_subscriptions: paid → (provisioning error) → payment_failed (rollback)
```
**Action:**
- Rollback payment status
- Log error ke `activity_logs`
- Admin bisa retry provisioning manual

---

## 🔀 Variasi Flow: New Customer vs Existing Customer

### **Flow Saat Ini: New Customer Only**

**Saat ini landing/checkout hanya mendukung *new signups*.**

**Flow:**
1. Visitor baru → Submit business info → Payment → Provisioning → Tenant baru

**Validasi:**
- Cek apakah email sudah ada di `users` table
- Jika sudah ada → Tampilkan error: "Email sudah terdaftar. Silakan login atau gunakan email lain."

### **Flow Future: Existing Customer (Upgrade/Downgrade)**

**Catatan:** Fitur ini belum diimplementasikan. Akan di-handle dari dashboard, bukan dari landing.

**Skenario:**
- Existing tenant mau upgrade/downgrade plan dari landing page
- Email yang dipakai sudah ada di `users` dan sudah punya `tenant`

**Flow yang Disarankan:**
1. Cek email di `users` → Jika sudah ada, cek `user_tenant_access` untuk get `tenant_id`
2. Attach subscription baru ke tenant yang lama (bukan buat tenant baru)
3. Update `subscriptions` untuk tenant tersebut
4. Update `landing_subscriptions` dengan `provisioned_store_id` = store pertama dari tenant

**Tidak Disarankan:**
- ❌ Buat tenant baru untuk email yang sudah ada (akan bikin duplikasi)

---

## 📧 Email & Komunikasi (UX Important)

**Catatan:** Secara DB tidak perlu tabel baru, tapi secara flow sebaiknya ditulis untuk implementasi backend & marketing.

### **Email yang Dikirim:**

#### **1. Setelah Payment Paid (Webhook Xendit)**
**Event:** `subscription_payments.status` = `paid`

**Email: "Payment Successful + Welcome"**
- Subject: "Selamat! Pembayaran Anda Berhasil - Akses Dashboard XpressPOS"
- Content:
  - Konfirmasi payment berhasil
  - URL login dashboard: `https://dashboard.xpresspos.com/login`
  - Email owner: `{user.email}`
  - Temporary password: `{temporary_password}` (atau magic link)
  - Link setup guide

**Side-effect:**
- Update `landing_subscriptions.status` = `paid`
- Trigger provisioning service

#### **2. Setelah Provisioning Success**
**Event:** Provisioning service selesai, `landing_subscriptions.status` = `provisioned`

**Email: "Setup Guide / Onboarding Steps"**
- Subject: "Panduan Setup Awal XpressPOS - Mulai Sekarang!"
- Content:
  - Step-by-step setup guide
  - Link ke video tutorial
  - Link ke documentation
  - Support contact

**Side-effect:**
- Update `landing_subscriptions.status` = `active`
- Log ke `activity_logs`: "Provisioning completed for tenant {tenant_id}"

#### **3. Payment Pending Reminder (Optional)**
**Event:** `subscription_payments.status` = `pending` dan `expires_at` < 24 jam

**Email: "Payment Reminder"**
- Subject: "Ingatkan: Selesaikan Pembayaran Anda - XpressPOS"
- Content:
  - Link payment Xendit
  - Deadline payment
  - Contact support jika ada masalah

**Side-effect:**
- Update `landing_subscriptions.follow_up_logs` (JSON) dengan timestamp reminder

#### **4. Payment Failed / Expired (Optional)**
**Event:** `subscription_payments.status` = `failed` atau `expired`

**Email: "Payment Failed / Expired"**
- Subject: "Pembayaran Gagal - Bantuan XpressPOS"
- Content:
  - Alasan payment failed/expired
  - Link retry payment
  - Contact support

**Side-effect:**
- Update `landing_subscriptions.status` = `payment_failed` atau `payment_expired`
- Update `landing_subscriptions.follow_up_logs` (JSON)

---

## 📊 Observability & Audit

**Catatan:** Event penting akan dicatat ke `activity_logs` / `payment_audit_logs` pada tahap implementasi berikutnya.

### **Event yang Perlu Di-Log:**

#### **1. Payment Events**
- Payment created → `payment_audit_logs`
- Payment paid → `payment_audit_logs` + `activity_logs`
- Payment failed → `payment_audit_logs` + `activity_logs`
- Payment expired → `payment_audit_logs` + `activity_logs`

#### **2. Provisioning Events**
- Provisioning started → `activity_logs`
- Provisioning success → `activity_logs` (tenant_id, store_id, user_id)
- Provisioning failed → `activity_logs` (error message, stack trace)

#### **3. Owner Login Events**
- First login → `activity_logs` (tenant_id, store_id, user_id)
- Login success → `activity_logs` (optional, bisa di-handle oleh Laravel auth)
- Login failed → `activity_logs` (optional, bisa di-handle oleh Laravel auth)

### **Tabel yang Digunakan:**

- `activity_logs` - Untuk log aktivitas umum
- `payment_audit_logs` - Untuk log payment events
- `payment_security_logs` - Untuk log security events (jika diperlukan)

---

## 🔄 Ringkasan Flow

```
1. Landing Page
   └─> Baca: plans
   
2. Checkout Step 1 (Plan Selection)
   └─> Baca: plans
   └─> Tulis: session
   
3. Checkout Step 2 (Business Info)
   └─> Tulis: landing_subscriptions
   
4. Checkout Step 3 (Payment)
   └─> Baca: landing_subscriptions, plans
   └─> Tulis: subscription_payments
   
5. Payment Webhook
   └─> Baca: subscription_payments, landing_subscriptions
   └─> Tulis: subscription_payments (update status), landing_subscriptions (update status)
   
6. Provisioning (Auto)
   └─> Tulis: tenants, users, user_tenant_access, stores, store_user_assignments, 
              subscriptions, subscription_usage, invoices
   └─> Update: landing_subscriptions, subscription_payments
   
7. Owner Login
   └─> Baca: users, user_tenant_access, store_user_assignments, stores, tenants, 
             subscriptions, plans, subscription_usage
   └─> Tulis: session (store context)
```

---

## 📊 Total Tabel yang Terlibat

**Landing Page (Public):**
1. `plans`
2. `plan_features` (optional, untuk query lebih detail)

**Checkout & Payment:**
3. `landing_subscriptions`
4. `subscription_payments`

**Provisioning:**
5. `tenants`
6. `users`
7. `user_tenant_access`
8. `stores`
9. `store_user_assignments`
10. `subscriptions`
11. `subscription_usage`
12. `invoices`

**Total: 12 tabel utama yang terlibat dalam flow Landing Page → Owner Dashboard.**

---

**Last Updated:** Berdasarkan migration files dan flow yang ada di codebase.

