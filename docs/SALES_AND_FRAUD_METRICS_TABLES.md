# Tabel dan Fields untuk Card Penjualan & Kontrol Fraud

Dokumen ini mencantumkan tabel dan field yang tersedia untuk menghitung metrik yang diminta.

## 📊 Card Penjualan (Sales Card)

### 1. Total Penerimaan (Payments - Refunds)

**Tabel:** `payments` dan `refunds`

**Migration:** 
- `database/migrations/2024_10_04_001900_create_payments_table.php`
- `database/migrations/2024_10_04_002000_create_refunds_table.php`
- `database/migrations/2025_11_07_000011_add_received_and_paid_fields_to_payments_table.php`

**Fields:**
```sql
-- payments table
id (uuid)
store_id (uuid, FK)
order_id (uuid, FK)
payment_method (enum: cash, credit_card, debit_card, qris, bank_transfer, e_wallet)
amount (decimal 12,2)
received_amount (decimal 12,2) -- Added in migration 2025_11_07
status (enum: pending, completed, failed, cancelled)
processed_at (timestamp)
paid_at (timestamp) -- Added in migration 2025_11_07

-- refunds table
id (uuid)
store_id (uuid, FK)
order_id (uuid, FK)
payment_id (uuid, FK)
amount (decimal 12,2)
status (enum: pending, approved, processed, rejected)
processed_at (timestamp)
```

**Cara Hitung:**
- Total Payments: `SUM(payments.amount)` WHERE `status = 'completed'`
- Total Refunds: `SUM(refunds.amount)` WHERE `status = 'processed'`
- Total Penerimaan = Total Payments - Total Refunds

---

### 2. Penjualan Kotor (Gross Sales)

**Tabel:** `orders`

**Migration:** `database/migrations/2024_10_04_001500_create_orders_table.php`

**Fields:**
```sql
id (uuid)
tenant_id (string 36, FK)
store_id (uuid, FK)
subtotal (decimal 12,2) -- Total sebelum tax, service charge, discount
tax_amount (decimal 12,2)
service_charge (decimal 12,2)
discount_amount (decimal 12,2)
total_amount (decimal 12,2) -- Total akhir setelah semua kalkulasi
status (enum: draft, open, completed, cancelled)
completed_at (timestamp)
created_at (timestamp)
```

**Cara Hitung:**
- Penjualan Kotor = `SUM(subtotal)` WHERE `status = 'completed'`
- Atau bisa juga = `SUM(total_amount + discount_amount - tax_amount - service_charge)`

---

### 3. Total Diskon (Pecah: Diskon Nota & Diskon Menu)

**Tabel:** `order_discounts` dan `order_item_discounts`

**Migrations:**
- `database/migrations/2024_11_14_000035_create_order_discounts_table.php`
- `database/migrations/2024_11_14_000036_create_order_item_discounts_table.php`

**Fields:**
```sql
-- order_discounts (Diskon Nota/Order Level)
id (string 36)
order_id (uuid, FK)
promotion_id (string 36, FK, nullable)
voucher_id (string 36, FK, nullable)
discount_type (enum: PROMOTION, VOUCHER, MANUAL)
discount_amount (decimal 18,2)

-- order_item_discounts (Diskon Menu/Item Level)
id (string 36)
order_item_id (uuid, FK)
promotion_id (string 36, FK, nullable)
discount_type (enum: PROMOTION, MANUAL)
discount_amount (decimal 18,2)
```

**Cara Hitung:**
- **Diskon Nota:** `SUM(order_discounts.discount_amount)`
- **Diskon Menu:** `SUM(order_item_discounts.discount_amount)`
- **Total Diskon:** Diskon Nota + Diskon Menu

---

### 4. PB1 / Tax (Opsional)

**Tabel:** `orders`

**Field:**
```sql
tax_amount (decimal 12,2)
```

**Cara Hitung:**
- `SUM(tax_amount)` WHERE `status = 'completed'`

---

### 5. Service Charge (Opsional)

**Tabel:** `orders`

**Field:**
```sql
service_charge (decimal 12,2)
```

**Cara Hitung:**
- `SUM(service_charge)` WHERE `status = 'completed'`

---

### 6. Penjualan Bersih (Net Sales)

**Tabel:** `orders`

**Fields:**
```sql
total_amount (decimal 12,2) -- Sudah dikurangi discount, sudah ditambah tax & service charge
```

**Cara Hitung:**
- `SUM(total_amount)` WHERE `status = 'completed'`
- Atau: Penjualan Kotor - Total Diskon + Tax + Service Charge

---

### 7. Total Bill

**Tabel:** `orders`

**Field:**
```sql
total_amount (decimal 12,2)
```

**Cara Hitung:**
- `COUNT(*)` WHERE `status = 'completed'` (jumlah bill/order)
- Atau `SUM(total_amount)` untuk total nilai bill

---

### 8. Ukuran Bill (Bill Size / Average Bill)

**Tabel:** `orders`

**Fields:**
```sql
total_amount (decimal 12,2)
```

**Cara Hitung:**
- `AVG(total_amount)` WHERE `status = 'completed'`
- Atau: Total Penjualan Bersih / Total Bill

---

### 9. Total Pax & Pembelanjaan per Pax (Opsional)

**Tabel:** `table_occupancy_histories`

**Migration:** `database/migrations/2024_10_04_003100_create_table_occupancy_histories_table.php`

**Fields:**
```sql
id (uuid)
tenant_id (string 36, FK)
store_id (uuid, FK)
table_id (uuid, FK)
order_id (uuid, FK, nullable)
party_size (integer, nullable) -- Jumlah orang/pax
order_total (decimal 12,2, nullable) -- Total order
occupied_at (timestamp)
cleared_at (timestamp, nullable)
status (enum: occupied, cleared, abandoned)
```

**Cara Hitung:**
- **Total Pax:** `SUM(party_size)` WHERE `order_id IS NOT NULL` dan `status = 'cleared'`
- **Pembelanjaan per Pax:** `SUM(order_total) / SUM(party_size)`

**Catatan:** Data ini hanya tersedia untuk dine_in orders yang menggunakan meja. Untuk takeaway/delivery, `party_size` mungkin tidak tersedia.

---

## 🛡️ Card Kontrol Fraud (Fraud Control Card)

### 1. Komplimen / FOC (Free of Charge)

**Tabel:** `orders`, `order_discounts`, `order_item_discounts`

**Fields:**
```sql
-- orders
total_amount (decimal 12,2)
discount_amount (decimal 12,2)

-- order_discounts
discount_type (enum: PROMOTION, VOUCHER, MANUAL)
discount_amount (decimal 18,2)

-- order_item_discounts
discount_type (enum: PROMOTION, MANUAL)
discount_amount (decimal 18,2)
```

**Cara Identifikasi:**
- **FOC Order:** `total_amount = 0` AND `status = 'completed'`
- **FOC via Discount:** 
  - `discount_amount >= subtotal` (diskon 100% atau lebih)
  - Atau `discount_type = 'MANUAL'` AND `discount_amount` sangat besar (mendekati atau sama dengan subtotal)
- **Total FOC:** Hitung jumlah order yang memenuhi kriteria di atas

**Catatan:** Saat ini tidak ada flag khusus untuk FOC. Perlu logika bisnis untuk mengidentifikasi:
- Order dengan total_amount = 0
- Order dengan discount_amount >= subtotal
- Order dengan MANUAL discount yang sangat besar

---

### 2. Pembatalan Order (Total & Count)

**Tabel:** `orders`

**Fields:**
```sql
status (enum: draft, open, completed, cancelled)
total_amount (decimal 12,2)
created_at (timestamp)
```

**Cara Hitung:**
- **Count Pembatalan:** `COUNT(*)` WHERE `status = 'cancelled'`
- **Total Nilai Pembatalan:** `SUM(total_amount)` WHERE `status = 'cancelled'`

---

### 3. Refund Tunai (Cash Refund)

**Tabel:** `refunds` dan `payments`

**Fields:**
```sql
-- refunds
id (uuid)
order_id (uuid, FK)
payment_id (uuid, FK)
amount (decimal 12,2)
status (enum: pending, approved, processed, rejected)

-- payments (untuk mengetahui payment_method)
payment_method (enum: cash, credit_card, debit_card, qris, bank_transfer, e_wallet)
```

**Cara Hitung:**
- `SUM(refunds.amount)` 
  WHERE `refunds.status = 'processed'` 
  AND `payments.payment_method = 'cash'`
  JOIN `payments` ON `refunds.payment_id = payments.id`

---

### 4. Refund Non Tunai (Non-Cash Refund)

**Tabel:** `refunds` dan `payments`

**Fields:**
```sql
-- refunds
amount (decimal 12,2)
status (enum: pending, approved, processed, rejected)

-- payments
payment_method (enum: cash, credit_card, debit_card, qris, bank_transfer, e_wallet)
```

**Cara Hitung:**
- `SUM(refunds.amount)` 
  WHERE `refunds.status = 'processed'` 
  AND `payments.payment_method != 'cash'`
  JOIN `payments` ON `refunds.payment_id = payments.id`

---

### 5. Biaya Lainnya / Platform Fee dari Expenses (Opsional)

**Tabel:** `expenses`

**Migration:** `database/migrations/2024_10_04_002200_create_expenses_table.php`

**Fields:**
```sql
id (uuid)
store_id (uuid, FK)
cash_session_id (uuid, FK, nullable)
user_id (bigint, FK)
category (string) -- Kategori expense
description (string)
amount (decimal 12,2)
receipt_number (string, nullable)
vendor (string, nullable)
expense_date (date)
notes (text, nullable)
created_at (timestamp)
updated_at (timestamp)
```

**Cara Hitung:**
- **Platform Fee:** `SUM(amount)` WHERE `category = 'Platform Fee'` atau kategori tertentu
- **Biaya Lainnya:** `SUM(amount)` WHERE `category IN ('Platform Fee', 'Other Fees', ...)`

**Catatan:** Perlu konvensi penamaan kategori yang konsisten untuk mengidentifikasi Platform Fee.

---

## 💰 Tabel COGS (Cost of Goods Sold)

COGS digunakan untuk menghitung profitabilitas dan margin. Data COGS tersimpan dalam 2 tabel yang saling terkait.

### 1. COGS History (Riwayat COGS per Produk per Order)

**Tabel:** `cogs_history`

**Migration:** `database/migrations/2024_10_04_002800_create_cogs_history_table.php`

**Fields:**
```sql
id (uuid)
tenant_id (string 36, FK)
store_id (uuid, FK)
product_id (bigint, FK) -- Link ke products
order_id (uuid, FK, nullable) -- Link ke orders
quantity_sold (integer) -- Jumlah produk yang terjual
unit_cost (decimal 10,2) -- Biaya per unit
total_cogs (decimal 10,2) -- Total COGS (unit_cost * quantity_sold)
calculation_method (enum: fifo, lifo, weighted_average) -- Metode perhitungan
cost_breakdown (json, nullable) -- Breakdown biaya per inventory item
created_at (timestamp)
updated_at (timestamp)
```

**Relasi:**
- `belongsTo` products, orders
- `hasMany` cogs_details

**Cara Hitung:**
- **Total COGS:** `SUM(total_cogs)` WHERE `order_id IS NOT NULL`
- **COGS per Produk:** `SUM(total_cogs)` GROUP BY `product_id`
- **COGS per Order:** `SUM(total_cogs)` GROUP BY `order_id`
- **Average Unit Cost:** `AVG(unit_cost)` atau `SUM(total_cogs) / SUM(quantity_sold)`

---

### 2. COGS Details (Detail Granular per Inventory Item)

**Tabel:** `cogs_details`

**Migration:** `database/migrations/2024_11_14_000029_create_cogs_details_table.php`

**Fields:**
```sql
id (string 36)
cogs_history_id (uuid, FK) -- Link ke cogs_history
order_item_id (uuid, FK, nullable) -- Link ke order_items
inventory_item_id (string 36, FK) -- Link ke inventory_items (bahan baku)
lot_id (string 36, FK, nullable) -- Link ke inventory_lots (untuk tracking lot)
quantity (decimal 18,3) -- Jumlah inventory item yang dikonsumsi
unit_cost (decimal 18,4) -- Biaya per unit inventory item
total_cost (decimal 18,2) -- Total biaya (quantity * unit_cost)
created_at (timestamp)
updated_at (timestamp)
```

**Relasi:**
- `belongsTo` cogs_history, order_items, inventory_items, inventory_lots

**Cara Hitung:**
- **COGS per Inventory Item:** `SUM(total_cost)` GROUP BY `inventory_item_id`
- **COGS per Order Item:** `SUM(total_cost)` GROUP BY `order_item_id`
- **Detail Breakdown:** Query dengan JOIN ke `inventory_items` untuk nama bahan baku

---

### 3. Informasi yang Bisa Diambil untuk Laporan

#### A. Gross Profit (Laba Kotor)

**Formula:** Penjualan Bersih - Total COGS

**Data yang Dibutuhkan:**
- Penjualan Bersih: `SUM(orders.total_amount)` WHERE `status = 'completed'`
- Total COGS: `SUM(cogs_history.total_cogs)` WHERE `order_id IS NOT NULL`

**Query Example:**
```sql
SELECT 
    SUM(o.total_amount) as penjualan_bersih,
    SUM(ch.total_cogs) as total_cogs,
    SUM(o.total_amount) - SUM(ch.total_cogs) as gross_profit
FROM orders o
LEFT JOIN cogs_history ch ON ch.order_id = o.id
WHERE o.status = 'completed'
  AND o.store_id = ?
  AND o.created_at BETWEEN ? AND ?
```

---

#### B. Gross Profit Margin (Margin Laba Kotor)

**Formula:** (Gross Profit / Penjualan Bersih) × 100%

**Data yang Dibutuhkan:**
- Gross Profit (dari A)
- Penjualan Bersih

---

#### C. COGS per Produk

**Query:**
```sql
SELECT 
    p.id,
    p.name,
    p.sku,
    SUM(ch.quantity_sold) as total_quantity_sold,
    SUM(ch.total_cogs) as total_cogs,
    AVG(ch.unit_cost) as avg_unit_cost,
    SUM(ch.total_cogs) / SUM(ch.quantity_sold) as effective_unit_cost
FROM cogs_history ch
JOIN products p ON p.id = ch.product_id
WHERE ch.store_id = ?
  AND ch.created_at BETWEEN ? AND ?
GROUP BY p.id, p.name, p.sku
```

**Kegunaan:**
- Identifikasi produk dengan COGS tinggi
- Analisis profitabilitas per produk
- Pricing strategy

---

#### D. COGS per Order

**Query:**
```sql
SELECT 
    o.id,
    o.order_number,
    o.total_amount as penjualan,
    SUM(ch.total_cogs) as total_cogs,
    o.total_amount - SUM(ch.total_cogs) as gross_profit,
    (o.total_amount - SUM(ch.total_cogs)) / o.total_amount * 100 as margin_pct
FROM orders o
LEFT JOIN cogs_history ch ON ch.order_id = o.id
WHERE o.status = 'completed'
  AND o.store_id = ?
  AND o.created_at BETWEEN ? AND ?
GROUP BY o.id, o.order_number, o.total_amount
```

**Kegunaan:**
- Analisis profitabilitas per transaksi
- Identifikasi order dengan margin rendah/negatif

---

#### E. COGS Breakdown per Bahan Baku (Inventory Item)

**Query:**
```sql
SELECT 
    ii.id,
    ii.name,
    ii.sku,
    SUM(cd.quantity) as total_consumption,
    AVG(cd.unit_cost) as avg_unit_cost,
    SUM(cd.total_cost) as total_cost
FROM cogs_details cd
JOIN inventory_items ii ON ii.id = cd.inventory_item_id
JOIN cogs_history ch ON ch.id = cd.cogs_history_id
WHERE ch.store_id = ?
  AND ch.created_at BETWEEN ? AND ?
GROUP BY ii.id, ii.name, ii.sku
ORDER BY total_cost DESC
```

**Kegunaan:**
- Identifikasi bahan baku dengan biaya tertinggi
- Analisis konsumsi bahan baku
- Inventory cost analysis

---

#### F. COGS per Kategori Produk

**Query:**
```sql
SELECT 
    c.id,
    c.name,
    SUM(ch.quantity_sold) as total_quantity,
    SUM(ch.total_cogs) as total_cogs,
    AVG(ch.unit_cost) as avg_unit_cost
FROM cogs_history ch
JOIN products p ON p.id = ch.product_id
JOIN categories c ON c.id = p.category_id
WHERE ch.store_id = ?
  AND ch.created_at BETWEEN ? AND ?
GROUP BY c.id, c.name
```

**Kegunaan:**
- Analisis profitabilitas per kategori
- Kategori mana yang paling profitable

---

#### G. COGS Trend (Time Series)

**Query:**
```sql
SELECT 
    DATE(ch.created_at) as date,
    COUNT(DISTINCT ch.order_id) as order_count,
    SUM(ch.quantity_sold) as total_quantity,
    SUM(ch.total_cogs) as total_cogs,
    AVG(ch.unit_cost) as avg_unit_cost
FROM cogs_history ch
WHERE ch.store_id = ?
  AND ch.created_at BETWEEN ? AND ?
GROUP BY DATE(ch.created_at)
ORDER BY date
```

**Kegunaan:**
- Tracking trend COGS dari waktu ke waktu
- Identifikasi anomaly (COGS naik/turun drastis)

---

#### H. COGS vs Sales Comparison

**Query:**
```sql
SELECT 
    DATE(o.created_at) as date,
    SUM(o.total_amount) as sales,
    SUM(ch.total_cogs) as cogs,
    SUM(o.total_amount) - SUM(ch.total_cogs) as gross_profit,
    (SUM(o.total_amount) - SUM(ch.total_cogs)) / SUM(o.total_amount) * 100 as margin_pct
FROM orders o
LEFT JOIN cogs_history ch ON ch.order_id = o.id
WHERE o.status = 'completed'
  AND o.store_id = ?
  AND o.created_at BETWEEN ? AND ?
GROUP BY DATE(o.created_at)
ORDER BY date
```

**Kegunaan:**
- Perbandingan sales vs COGS harian
- Tracking margin dari waktu ke waktu

---

### 4. Catatan Penting tentang COGS

1. **Calculation Method:**
   - Saat ini menggunakan `weighted_average` untuk recipe-based products
   - COGS dihitung dari recipe ingredients (inventory items)
   - Method `fifo` dan `lifo` tersedia di enum tapi mungkin belum diimplementasi penuh

2. **Recipe-Based COGS:**
   - COGS dihitung dari recipe ingredients, bukan langsung dari product cost
   - Setiap produk yang punya recipe akan dihitung COGS-nya dari bahan baku yang dikonsumsi

3. **Order Link:**
   - `cogs_history.order_id` bisa NULL (untuk kasus tertentu)
   - Untuk laporan, filter `order_id IS NOT NULL` untuk memastikan hanya COGS dari order yang valid

4. **Granularity:**
   - `cogs_history`: Summary per produk per order
   - `cogs_details`: Detail per inventory item per order item
   - Gunakan `cogs_details` untuk breakdown yang lebih detail

5. **Data Availability:**
   - COGS hanya tersedia untuk produk yang memiliki recipe
   - Produk tanpa recipe tidak akan punya COGS history (akan throw exception saat calculate)

6. **Filtering:**
   - Semua query harus memfilter berdasarkan:
     - `store_id` (untuk multi-store)
     - `tenant_id` (untuk multi-tenant)
     - Date range (untuk periode laporan)
     - `order_id IS NOT NULL` (untuk memastikan COGS dari order yang valid)

---

## 📋 Ringkasan Migration Files

1. **Orders:**
   - `database/migrations/2024_10_04_001500_create_orders_table.php`

2. **Payments:**
   - `database/migrations/2024_10_04_001900_create_payments_table.php`
   - `database/migrations/2025_11_07_000011_add_received_and_paid_fields_to_payments_table.php`

3. **Refunds:**
   - `database/migrations/2024_10_04_002000_create_refunds_table.php`

4. **Discounts:**
   - `database/migrations/2024_11_14_000035_create_order_discounts_table.php`
   - `database/migrations/2024_11_14_000036_create_order_item_discounts_table.php`

5. **Order Items:**
   - `database/migrations/2024_10_04_001600_create_order_items_table.php`

6. **Table Occupancy (untuk Pax):**
   - `database/migrations/2024_10_04_003100_create_table_occupancy_histories_table.php`

7. **Expenses:**
   - `database/migrations/2024_10_04_002200_create_expenses_table.php`

8. **COGS:**
   - `database/migrations/2024_10_04_002800_create_cogs_history_table.php`
   - `database/migrations/2024_11_14_000029_create_cogs_details_table.php`

---

## ⚠️ Catatan Penting

1. **FOC/Komplimen:** Tidak ada flag khusus. Perlu logika untuk mengidentifikasi:
   - Order dengan `total_amount = 0`
   - Order dengan `discount_amount >= subtotal`
   - Order dengan MANUAL discount yang sangat besar

2. **Pax Data:** Hanya tersedia untuk dine_in orders yang menggunakan meja. Data di `table_occupancy_histories.party_size`.

3. **Refund Payment Method:** Perlu JOIN dengan `payments` table untuk mengetahui apakah refund tunai atau non-tunai.

4. **Platform Fee:** Perlu konvensi penamaan kategori di `expenses.category` untuk mengidentifikasi Platform Fee.

5. **Filtering:** Semua query harus memfilter berdasarkan:
   - `store_id` (untuk multi-store)
   - `tenant_id` (untuk multi-tenant)
   - Date range (untuk periode laporan)
   - `status = 'completed'` untuk orders yang sudah selesai

6. **COGS Data:**
   - COGS hanya tersedia untuk produk yang memiliki recipe
   - Gunakan `cogs_history` untuk summary, `cogs_details` untuk breakdown detail
   - COGS dihitung dari recipe ingredients (inventory items), bukan langsung dari product cost
   - Filter `order_id IS NOT NULL` untuk memastikan hanya COGS dari order yang valid

