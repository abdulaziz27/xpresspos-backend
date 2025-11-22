# Wave 3 - Order → Recipe → Inventory Movements → COGS Summary

## Status: ✅ COMPLETED

Wave 3 berhasil mengimplementasikan pipeline otomatis dari order completion ke COGS calculation menggunakan recipe-based approach.

---

## 1. File yang Dibuat/Diubah

### ✅ Files Created

1. **`app/Models/CogsDetail.php`**
   - Model baru untuk tabel `cogs_details`
   - Relasi ke `CogsHistory`, `OrderItem`, `InventoryItem`, `InventoryLot`
   - Casts untuk quantity (decimal:3), unit_cost (decimal:4), total_cost (decimal:2)

2. **`app/Jobs/ProcessOrderCogsJob.php`**
   - Job untuk memproses COGS saat order completed
   - Menerima `orderId` sebagai parameter
   - Memanggil `CogsService::processOrderById()`
   - Implements `ShouldQueue` untuk async processing

### ✅ Files Modified

3. **`app/Services/CogsService.php`**
   - **Method baru:** `processOrder(Order $order): void`
     - Validasi order eligibility
     - Loop order_items dengan active recipe
     - Hitung konsumsi inventory_item berdasarkan recipe
     - Buat inventory_movements (type 'sale')
     - Buat cogs_history (summary per product)
     - Buat cogs_details (granular per order_item & inventory_item)
   - **Method baru:** `processOrderById(string $orderId): void`
     - Helper untuk load order dan panggil `processOrder()`

4. **`app/Models/CogsHistory.php`**
   - **Relasi baru:** `cogsDetails(): HasMany`
   - Support untuk query COGS details dari history

5. **`app/Models/Order.php`**
   - **Hook baru:** Di `booted()` method, tambahkan `saved` event listener
   - Saat status berubah ke 'completed', dispatch `ProcessOrderCogsJob`
   - Idempotency check: skip jika sudah ada `cogs_history` untuk order tersebut

---

## 2. Pipeline Alur COGS

### Flow Diagram

```
Order Status → 'completed'
    ↓
Order Model Event (saved)
    ↓
ProcessOrderCogsJob::dispatch()
    ↓
CogsService::processOrder()
    ↓
┌─────────────────────────────────────┐
│ For each order_item with recipe:    │
│ 1. Calculate inventory consumption │
│ 2. Create inventory_movements        │
│ 3. Update stock_levels              │
│ 4. Create cogs_history               │
│ 5. Create cogs_details              │
└─────────────────────────────────────┘
```

### Detailed Algorithm

**Input:** Order dengan status 'completed'

**Step 1: Validation**
- ✅ Order status = 'completed'
- ✅ Order memiliki store_id
- ✅ Order belum pernah di-COGS-kan (idempotency check)

**Step 2: Load Data**
- Load order_items dengan eager loading:
  - `items.product`
  - `product.recipes` (hanya yang `is_active = true`)
  - `recipe.items.inventoryItem`

**Step 3: Group by Product**
- Group order_items per `product_id`
- Untuk setiap product group:
  - Skip jika `track_inventory = false`
  - Skip jika tidak ada active recipe
  - Skip jika recipe tidak punya items

**Step 4: Calculate Consumption**
- Untuk setiap order_item:
  - Hitung multiplier: `order_item.quantity / recipe.yield_quantity`
  - Untuk setiap recipe_item:
    - `consumed_qty = recipe_item.quantity * multiplier`
    - `unit_cost = recipe_item.unit_cost` (dari `inventory_items.default_cost`)
    - `line_total_cost = consumed_qty * unit_cost`
    - Accumulate ke consumption map per inventory_item

**Step 5: Create Inventory Movements**
- Untuk setiap inventory_item yang dikonsumsi:
  - Buat 1 `inventory_movements` record:
    - `type = 'sale'`
    - `quantity = total_consumed_qty` (positif)
    - `unit_cost = avg_unit_cost`
    - `total_cost = total_cost`
    - `reference_type = 'App\Models\Order'`
    - `reference_id = order.id`
  - Update `stock_levels` via `StockLevel::updateFromMovement()`

**Step 6: Create COGS History**
- Untuk setiap product:
  - Buat 1 `cogs_history` record:
    - `quantity_sold = sum(order_items.quantity)`
    - `total_cogs = sum(total_cost semua inventory_item)`
    - `unit_cost = total_cogs / quantity_sold`
    - `calculation_method = 'weighted_average'`
    - `cost_breakdown = JSON summary per inventory_item`

**Step 7: Create COGS Details**
- Untuk setiap kombinasi (order_item, inventory_item):
  - Buat 1 `cogs_details` record:
    - `cogs_history_id`
    - `order_item_id`
    - `inventory_item_id`
    - `lot_id = null` (Wave 3: not using lots)
    - `quantity = consumed_qty`
    - `unit_cost = unit_cost dari recipe_item`
    - `total_cost = line_total_cost`

---

## 3. Key Features

### ✅ Recipe-Based COGS
- Cost diambil dari `recipe_items.unit_cost` (yang sudah auto-calculated dari `inventory_items.default_cost`)
- Tidak menggunakan per-lot/FIFO (untuk Wave 4)
- Simple dan predictable

### ✅ Automatic Stock Deduction
- Saat order completed, stok inventory_item otomatis berkurang
- Via `inventory_movements` type 'sale'
- `stock_levels` di-update otomatis via `StockLevel::updateFromMovement()`

### ✅ Idempotency
- Check di awal: jika sudah ada `cogs_history` untuk order, skip processing
- Aman jika job di-trigger 2x (misal karena retry)

### ✅ Transaction Safety
- Semua operasi dalam `DB::transaction()`
- Jika ada error, semua perubahan di-rollback

### ✅ Edge Case Handling
- Product tanpa recipe aktif → skip (log warning)
- Recipe tanpa items → skip (log warning)
- consumed_qty atau cost = 0 → skip entry
- Product dengan `track_inventory = false` → skip

---

## 4. Database Changes

### No Schema Changes
- Wave 3 tidak mengubah schema database
- Hanya menggunakan tabel yang sudah ada:
  - `orders`, `order_items`
  - `recipes`, `recipe_items`
  - `inventory_items`, `inventory_movements`, `stock_levels`
  - `cogs_history`, `cogs_details`

### Model Changes
- ✅ `CogsDetail` model dibuat (tabel sudah ada dari migration)
- ✅ `CogsHistory` model ditambah relasi `cogsDetails()`

---

## 5. Testing Checklist

### Manual Testing Steps

1. **Setup Data:**
   - ✅ 1 tenant, 1 store
   - ✅ 2 inventory_items dengan `default_cost` > 0
   - ✅ 1 product dengan `track_inventory = true`
   - ✅ 1 active recipe untuk product tersebut
   - ✅ Recipe items menggunakan kedua inventory_items
   - ✅ Stock awal untuk inventory_items (via purchase/adjustment)

2. **Create Order:**
   - ✅ Buat order dengan status 'draft' atau 'open'
   - ✅ Tambah 2 order_items untuk product yang sama
   - ✅ Quantity: misal 3 dan 2 (total 5)

3. **Complete Order:**
   - ✅ Ubah status order ke 'completed'
   - ✅ Verify job di-dispatch (cek queue/logs)

4. **Verify Results:**

   **Inventory Movements:**
   - ✅ Ada entri type 'sale' per inventory_item
   - ✅ Quantity sesuai: `recipe_item.quantity * (order_item.quantity / recipe.yield_quantity)`
   - ✅ `store_id` benar
   - ✅ `reference_type` = 'App\Models\Order'
   - ✅ `reference_id` = order.id

   **Stock Levels:**
   - ✅ `current_stock` berkurang sesuai movement
   - ✅ `available_stock` berkurang sesuai movement
   - ✅ `average_cost` dan `total_value` ter-update

   **COGS History:**
   - ✅ Ada 1 row per product untuk order tersebut
   - ✅ `quantity_sold` = total qty product di order (5)
   - ✅ `total_cogs` = sum total_cost semua inventory_item
   - ✅ `unit_cost` = total_cogs / quantity_sold
   - ✅ `order_id` = order.id

   **COGS Details:**
   - ✅ Ada row per (order_item, inventory_item)
   - ✅ `quantity` & `total_cost` sesuai perhitungan
   - ✅ `lot_id` = null (Wave 3)

5. **Idempotency Test:**
   - ✅ Simpan order lagi (ubah status completed 2x)
   - ✅ Verify tidak ada duplikasi:
     - Jumlah row di `cogs_history` tidak bertambah
     - Jumlah movement type 'sale' untuk order tidak bertambah

---

## 6. COGS Calculation Details

### Cost Source
- **Recipe Items:** `recipe_items.unit_cost` (auto dari `inventory_items.default_cost`)
- **Not Used:** Per-lot cost, FIFO/LIFO (untuk Wave 4)

### Calculation Method
- **Method:** `weighted_average` (default, walaupun logikanya recipe-based)
- **Reason:** Consistency dengan enum yang sudah ada, tapi implementasinya recipe-based

### Cost Breakdown
- **cogs_history.cost_breakdown:** JSON summary per inventory_item
  ```json
  {
    "inventory_item_id": "...",
    "inventory_item_name": "...",
    "quantity": 1.5,
    "total_cost": 15000
  }
  ```

### Granular Tracking
- **cogs_details:** Detail per order_item dan inventory_item
  - Bisa track bahan mana yang dipakai per order_item
  - Support untuk analisis lebih detail nanti

---

## 7. Limitations & Future Enhancements

### Current Limitations (Wave 3)

1. **No Lot Tracking:**
   - `cogs_details.lot_id` selalu null
   - Tidak support FIFO/LIFO per lot
   - Cost selalu dari `recipe_items.unit_cost` (default cost)

2. **Recipe-Based Only:**
   - Product tanpa recipe aktif → tidak ada COGS
   - Tidak ada fallback untuk non-recipe products

3. **No Real-Time Cost:**
   - Cost dari `recipe_items.unit_cost` (snapshot saat resep dibuat)
   - Tidak menggunakan actual cost dari purchase orders/lots

### Future Enhancements (Wave 4+)

1. **Lot-Based COGS:**
   - Support FIFO/LIFO per lot
   - `cogs_details.lot_id` diisi dari actual lot yang dipakai
   - Cost dari `inventory_lots.unit_cost`

2. **Real-Time Cost:**
   - Update `recipe_items.unit_cost` dari actual purchase cost
   - Atau gunakan weighted average dari stock_levels

3. **Non-Recipe Products:**
   - Support COGS untuk products tanpa recipe
   - Mungkin direct inventory_item mapping

---

## 8. Integration Points

### Order Completion
- **Trigger:** Order model `saved` event
- **Condition:** Status changed to 'completed'
- **Action:** Dispatch `ProcessOrderCogsJob`

### Stock Management
- **Integration:** `InventoryMovement` dan `StockLevel`
- **Update:** Stock levels di-update otomatis via `StockLevel::updateFromMovement()`
- **Type:** Movement type 'sale' untuk mengurangi stok

### Recipe System
- **Integration:** `Product::getActiveRecipe()`
- **Source:** Recipe items untuk hitung konsumsi bahan
- **Cost:** `recipe_items.unit_cost` untuk cost calculation

---

## 9. Error Handling

### Validation Errors
- Order tidak completed → throw Exception dengan pesan jelas
- Order tidak punya store_id → throw Exception
- Order sudah di-COGS-kan → skip (idempotency)

### Processing Errors
- Product tanpa recipe → skip dengan log warning
- Recipe tanpa items → skip dengan log warning
- Transaction error → rollback semua perubahan

### Logging
- Info log: COGS processing started/completed
- Debug log: Skipped products/recipes
- Error log: Processing failures

---

## 10. Summary

### What Was Implemented
- ✅ Automatic COGS processing saat order completed
- ✅ Recipe-based cost calculation
- ✅ Automatic inventory movements (type 'sale')
- ✅ Automatic stock level updates
- ✅ COGS history and details creation
- ✅ Idempotency protection

### What Was NOT Implemented
- ❌ Lot-based COGS (Wave 4)
- ❌ FIFO/LIFO per lot (Wave 4)
- ❌ Real-time cost from purchase orders (Wave 4)
- ❌ COGS untuk non-recipe products (Wave 4)

### Key Principles Maintained
- ✅ Simple recipe-based approach
- ✅ No new source of truth
- ✅ Automatic system-maintained fields
- ✅ Transaction safety
- ✅ Idempotency

---

**Documentation Date:** 2024-12-19
**Wave:** 3 - Order → Recipe → Inventory Movements → COGS (Simple, Recipe-Based)

