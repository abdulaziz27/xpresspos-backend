# 🔐 Authenticated Checkout Flow

**Versi:** 1.0  
**Tanggal:** 2025-11-19  
**Status:** ✅ Implemented

---

## 📋 Ringkasan

Checkout sekarang **HANYA via authenticated user**. User harus login dulu sebelum bisa melakukan checkout subscription.

---

## 🔄 Flow Lengkap

### 1. **User Login**
- User harus login terlebih dahulu
- User harus punya `tenant` (via `user_tenant_access`)

### 2. **Checkout**
- User pilih plan dari pricing page
- System create `landing_subscriptions` dengan:
  - ✅ `user_id` (FK → users.id)
  - ✅ `tenant_id` (FK → tenants.id)
  - ✅ `plan_id` (FK → plans.id)
  - ✅ `billing_cycle` (monthly/annual)
  - ✅ `payment_amount`
  - ✅ `status` = 'pending'
  - ✅ `stage` = 'payment_pending'

### 3. **Payment**
- System create `subscription_payments` dengan:
  - ✅ `landing_subscription_id` (FK → landing_subscriptions.id)
  - ✅ `status` = 'pending'
  - ✅ `xendit_invoice_id`
  - ✅ `amount`

### 4. **Webhook Xendit (Payment Paid)**
- Xendit webhook → `XenditWebhookController::handleInvoiceCallback()`
- Update `subscription_payments.status` = 'paid'
- Trigger `SubscriptionProvisioningService::provisionFromPaidLandingSubscription()`

### 5. **Provisioning**
- **Authenticated Flow** (jika `user_id` & `tenant_id` sudah ada):
  - ✅ **TIDAK** membuat tenant/user baru
  - ✅ Menggunakan tenant & user yang sudah ada
  - ✅ Create/update `subscriptions` untuk tenant tersebut
  - ✅ Create `subscription_usage` dari `plan_features`
  - ✅ Update `landing_subscriptions.subscription_id`
  - ✅ Update `landing_subscriptions.status` = 'provisioned'

- **Anonymous Flow** (legacy, jika `user_id` atau `tenant_id` null):
  - ✅ Create tenant baru
  - ✅ Create user baru
  - ✅ Create store pertama
  - ✅ Create subscription

---

## 📊 Tabel yang Terlibat

| Tabel | Fungsi | Kolom Kunci |
|-------|--------|-------------|
| `landing_subscriptions` | Log checkout | `user_id`, `tenant_id`, `plan_id`, `billing_cycle`, `subscription_id` |
| `subscription_payments` | Status pembayaran (source of truth) | `landing_subscription_id`, `status`, `xendit_invoice_id` |
| `subscriptions` | Kontrak aktif per tenant | `tenant_id`, `plan_id`, `status` |
| `subscription_usage` | Tracking usage | `subscription_id`, `feature_type`, `current_usage` |

---

## 🔑 Key Points

1. **User harus login** sebelum checkout
2. **User harus punya tenant** (via `user_tenant_access`)
3. **Checkout create `landing_subscriptions`** (log checkout)
4. **Payment create `subscription_payments`** (status pembayaran)
5. **Webhook trigger provisioning** → create `subscriptions` per tenant
6. **Authenticated flow tidak membuat tenant/user baru** (menggunakan yang sudah ada)

---

## 🧪 Testing

Semua flow sudah di-cover oleh:
- ✅ `AuthenticatedCheckoutFlowTest` (4 tests, 16 assertions)
- ✅ `SubscriptionProvisioningTest` (11 tests, 58 assertions)

---

## 📝 Catatan

- Legacy fields (`email`, `name`, `company`, dll) masih ada di `landing_subscriptions` untuk backward compatibility
- Flow anonymous (tanpa login) masih didukung untuk backward compatibility, tapi tidak direkomendasikan
- `plan_id` sekarang menggunakan integer (bukan slug string) untuk konsistensi

