# Promotion Data Structure - Mobile POS Consumption Guide

## 📋 Analisa Struktur Saat Ini

### ✅ **KELEBIHAN** Struktur JSON Fleksibel

1. **Fleksibilitas Tinggi**
   - Mudah menambah tipe condition/reward baru tanpa ubah database schema
   - Tidak perlu kolom nullable untuk setiap parameter (misal: `min_amount`, `product_ids`, `start_time`, dll)
   - Scalable untuk kebutuhan promosi yang beragam

2. **Pattern Standard**
   - Type + Value adalah pattern umum di sistem e-commerce/promo
   - Mirip dengan Spatie Permission (permission_name + context), Stripe Coupons, dll

3. **Compact Storage**
   - Hanya menyimpan data yang relevan per tipe
   - Tidak ada waste storage untuk field yang tidak digunakan

### ⚠️ **TANTANGAN** untuk Mobile POS

1. **Parsing Required**
   - Mobile POS perlu parse JSON berdasarkan `condition_type` / `reward_type`
   - Perlu switch/case logic di mobile app

2. **Validasi Ganda**
   - Validasi di backend (sudah ada di Filament)
   - Perlu validasi di mobile POS saat consume data

3. **Dokumentasi**
   - Struktur JSON perlu didokumentasi dengan jelas
   - Setiap tipe punya struktur berbeda

## 🎯 **REKOMENDASI: Best Practice**

### ✅ **PENDEKATAN SAAT INI SUDAH BAIK**, dengan catatan:

1. **✅ Tetap Gunakan JSON** - Sudah sesuai dengan migration dan fleksibel
2. **✅ Form Dinamis di Admin** - Hanya UX improvement, tidak mengubah struktur data
3. **✅ Tambahkan Helper Methods di Model** - Memudahkan konsumsi di mobile POS

## 🛠️ **Solusi: Tambah Helper Methods di Model**

### Struktur JSON yang Akan Dikonsumsi Mobile POS:

#### Condition Types & Value Structure:
```json
// MIN_SPEND
{"amount": 50000}

// ITEM_INCLUDE
{"product_ids": ["uuid1", "uuid2"]}

// CUSTOMER_TIER_IN
{"tier_ids": ["uuid1", "uuid2"]}

// DOW (Day of Week)
{"days": [1, 2, 3, 4, 5]} // 1 = Monday, 7 = Sunday

// TIME_RANGE
{"start_time": "08:00", "end_time": "22:00"}

// BRANCH_IN
{"store_ids": ["uuid1", "uuid2"]}

// NEW_CUSTOMER
{}
```

#### Reward Types & Value Structure:
```json
// PCT_OFF
{"percentage": 10}

// AMOUNT_OFF
{"amount": 5000}

// BUY_X_GET_Y
{"buy_quantity": 2, "get_quantity": 1, "product_id": "uuid"}

// POINTS_MULTIPLIER
{"multiplier": 2.0}
```

### Helper Methods yang Diperlukan:

1. **`PromotionCondition::getAmount()`** - Get amount untuk MIN_SPEND
2. **`PromotionCondition::getProductIds()`** - Get product_ids untuk ITEM_INCLUDE
3. **`PromotionCondition::isValidForOrder($order)`** - Validate condition terhadap order
4. **`PromotionReward::getDiscountAmount($subtotal)`** - Calculate discount
5. **`Promotion::canApply($order)`** - Check semua conditions

## 📱 **Contoh Penggunaan di Mobile POS API**

```php
// API: GET /api/v1/promotions/active
// Response structure yang mudah dikonsumsi:

{
  "id": "uuid",
  "name": "Diskon 10% Min Beli 50rb",
  "type": "AUTOMATIC",
  "code": null,
  "status": "active",
  "starts_at": "2024-01-01T00:00:00Z",
  "ends_at": "2024-12-31T23:59:59Z",
  "conditions": [
    {
      "condition_type": "MIN_SPEND",
      "condition_value": {"amount": 50000}
    }
  ],
  "rewards": [
    {
      "reward_type": "PCT_OFF",
      "reward_value": {"percentage": 10}
    }
  ]
}

// Mobile POS logic:
foreach ($promotion->conditions as $condition) {
  if ($condition->condition_type === 'MIN_SPEND') {
    $minAmount = $condition->condition_value['amount'];
    if ($order->subtotal < $minAmount) {
      return false; // Condition tidak terpenuhi
    }
  }
}

foreach ($promotion->rewards as $reward) {
  if ($reward->reward_type === 'PCT_OFF') {
    $percentage = $reward->reward_value['percentage'];
    $discount = $order->subtotal * ($percentage / 100);
  }
}
```

## 🔄 **Alternatif (TIDAK DIREKOMENDASIKAN)**

### ❌ Flat Structure (Banyak Kolom Nullable)
```
promotion_conditions:
  - condition_type
  - min_amount (nullable)
  - product_ids (nullable, JSON)
  - tier_ids (nullable, JSON)
  - days (nullable, JSON)
  - start_time (nullable)
  - end_time (nullable)
  - store_ids (nullable, JSON)
```

**Masalah:**
- Banyak kolom nullable (7+ kolom)
- Tidak scalable (setiap tipe baru = kolom baru)
- Waste storage
- Complex migration setiap tambah fitur

### ❌ Separate Tables per Condition Type
```
promotion_min_spend_conditions
promotion_product_conditions
promotion_time_conditions
...
```

**Masalah:**
- Terlalu banyak tabel
- Complex queries untuk get semua conditions
- Maintenance nightmare

## ✅ **KESIMPULAN**

1. **Struktur JSON saat ini SUDAH BEST PRACTICE** ✅
2. **Form dinamis di admin panel TIDAK MEMPENGARUHI struktur data** ✅
3. **Mobile POS hanya perlu parse JSON berdasarkan type** (standard pattern) ✅
4. **Rekomendasi: Tambahkan helper methods di Model untuk memudahkan konsumsi** 🎯

## 📝 **Next Steps**

1. Tambahkan helper methods di `PromotionCondition` & `PromotionReward` models
2. Buat `PromotionService` untuk handle validation & calculation
3. Buat API endpoint untuk mobile POS: `GET /api/v1/promotions/active`
4. Dokumentasi API dengan contoh response untuk setiap condition/reward type

