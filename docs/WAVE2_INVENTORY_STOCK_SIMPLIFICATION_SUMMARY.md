# Wave 2 - Inventory & Stock Simplification Summary

## Tujuan
Menyederhanakan desain stok & inventory supaya tidak double dan tidak ambigu:
- Stok hanya ditrack di level `inventory_items`, per store
- `inventory_movements` dan `stock_levels` pakai `inventory_item_id` + quantity decimal, bukan `product_id` + integer
- Filament tidak lagi menampilkan field stok yang bisa diedit manual seenaknya

---

## A. Perubahan di Migrations

### ✅ 1. `inventory_movements` Table
**File:** `database/migrations/2024_10_04_002600_create_inventory_movements_table.php`

**Perubahan:**
- Hapus: `foreignId('product_id')->constrained('products')`
- Ganti dengan: `string('inventory_item_id', 36)` + FK ke `inventory_items.id`
- Ubah `quantity`: dari `integer()` → `decimal('quantity', 18, 3)`
- Update index: `['store_id', 'product_id']` → `['store_id', 'inventory_item_id']`

**Alasan:**
- Movement dicatat per inventory item (bahan mentah), bukan per product (barang jadi)
- Quantity harus decimal karena stok bisa pecahan (kg, liter, dll)

---

### ✅ 2. `stock_levels` Table
**File:** `database/migrations/2024_10_04_002700_create_stock_levels_table.php`

**Perubahan:**
- Hapus: `foreignId('product_id')->constrained('products')`
- Ganti dengan: `string('inventory_item_id', 36)` + FK ke `inventory_items.id`
- Ubah kolom quantity:
  - `current_stock` → `decimal(18,3)` default 0
  - `reserved_stock` → `decimal(18,3)` default 0
  - `available_stock` → `decimal(18,3)` default 0
  - `min_stock_level` → `decimal(18,3)` default 0
- Update unique constraint: `['store_id', 'product_id']` → `['store_id', 'inventory_item_id']`
- Update index: tambah `['store_id', 'inventory_item_id']`

**Alasan:**
- Stock levels adalah ringkasan stok per store per inventory item
- Quantity harus decimal untuk support pecahan

---

## B. Perubahan di Models

### ✅ 1. InventoryMovement Model
**File:** `app/Models/InventoryMovement.php`

**Perubahan:**
- Tambah `TenantScope` global scope
- Ganti `fillable`: `product_id` → `inventory_item_id`
- Update `casts`: `quantity` dari `integer` → `decimal:3`
- Ganti relasi: `product()` → `inventoryItem()` (tambah deprecated alias)
- Update `getSignedQuantity()`: return type dari `int` → `float`
- Update `createMovement()`:
  - Parameter: `$productId` → `$inventoryItemId`
  - Parameter: `int $quantity` → `float $quantity`
  - Field: `product_id` → `inventory_item_id`

**Behavior:**
- Movement dicatat per inventory item
- Quantity support decimal (e.g., 1.5 kg)

---

### ✅ 2. StockLevel Model
**File:** `app/Models/StockLevel.php`

**Perubahan:**
- Tambah `TenantScope` global scope
- Ganti `fillable`: `product_id` → `inventory_item_id`, tambah `min_stock_level`
- Update `casts`:
  - `current_stock`, `reserved_stock`, `available_stock` → `decimal:3`
  - `min_stock_level` → `decimal:3`
- Ganti relasi: `product()` → `inventoryItem()` (tambah deprecated alias)
- Update methods:
  - `reserveStock()`: parameter dari `int` → `float`
  - `releaseReservedStock()`: parameter dari `int` → `float`
  - `isLowStock()`: pakai `inventoryItem` bukan `product`
  - `isOutOfStock()`: pakai `inventoryItem` bukan `product`
  - `getOrCreateForProduct()` → `getOrCreateForInventoryItem()` (tambah deprecated alias)

**Behavior:**
- Stock levels di-maintain oleh sistem (via service/logic), bukan di-edit manual user
- Field-field ini adalah denormalized aggregates

---

### ✅ 3. InventoryItem Model
**File:** `app/Models/InventoryItem.php`

**Perubahan:**
- Tambah relasi: `stockLevels(): HasMany`
- Tambah relasi: `inventoryMovements(): HasMany`

**Behavior:**
- Bisa lihat stok per store dari InventoryItem
- Bisa lihat histori movement per item

---

## C. Perubahan di Filament Resources

### ✅ 1. StockLevelsResource
**File:** `app/Filament/Owner/Resources/StockLevels/StockLevelResource.php`

**Perubahan:**
- **Table columns:**
  - Ganti `product.name` → `inventoryItem.name` + `inventoryItem.sku`
  - Tambah `store.name` (toggleable)
  - Update `current_stock`, `reserved_stock`, `available_stock`: `numeric(decimalPlaces: 3)`
  - Tambah `min_stock_level` column
  - Tambah `last_movement_at` column
  - Tambah description untuk quantity (UOM name)
- **Filters:**
  - Ganti filter `product_id` → `inventory_item_id`
  - Update filter `is_low_stock`: pakai `inventoryItem` bukan `product`
- **Query:**
  - Eager load: `inventoryItem.uom` bukan `product`
- **Permissions:**
  - Sudah read-only (canCreate, canEdit, canDelete = false)

**Behavior:**
- Semua field read-only
- StockLevels di-maintain oleh sistem, bukan via UI manual

---

### ✅ 2. InventoryMovementsResource
**File:** `app/Filament/Owner/Resources/InventoryMovements/InventoryMovementResource.php`
**File:** `app/Filament/Owner/Resources/InventoryMovements/Schemas/InventoryMovementForm.php`
**File:** `app/Filament/Owner/Resources/InventoryMovements/Tables/InventoryMovementsTable.php`

**Perubahan:**
- **Form:**
  - Ganti `product_id` → `inventory_item_id` (Select dari InventoryItem)
  - Update `quantity`: `minValue(1)` → `minValue(0.001)`, tambah `step(0.001)`
  - Update method: `productOptions()` → `inventoryItemOptions()`
- **Table:**
  - Ganti `product.name` → `inventoryItem.name` + `inventoryItem.sku`
  - Tambah `store.name` column
  - Update `quantity`: `numeric(decimalPlaces: 3)` + description (UOM name)
  - Ganti filter `product_id` → `inventory_item_id`
- **Query:**
  - Eager load: `inventoryItem.uom` bukan `product`

**Behavior:**
- Movement dicatat per inventory item
- Quantity support decimal
- Type lain (sale/purchase/transfer) bisa dibuat manual untuk adjustment

---

### ✅ 3. InventoryItemsResource
**File:** `app/Filament/Owner/Resources/InventoryItems/InventoryItemResource.php`

**Status:**
- ✅ Sudah benar - tidak ada field stok yang bisa diedit manual
- `min_stock_level` = setting minimum global (bukan stok aktual)
- Stok aktual ada di StockLevelsResource (read-only)

---

## D. Prinsip yang Diikuti

1. **Stock = hanya milik `inventory_items`, per store**
   - Products tidak punya stok langsung
   - Stok fisik hanya di inventory_items

2. **Schema konsisten**
   - `inventory_movements` & `stock_levels` pakai `inventory_item_id`
   - Quantity selalu decimal (18,3)

3. **Field stok agregat = denormalized / sistem yang isi**
   - StockLevels di-maintain oleh sistem (via service/logic)
   - Tidak bisa di-edit manual dari UI

4. **User tidak lagi ngedit stok lewat banyak tempat yang ambigu**
   - StockLevelsResource = read-only
   - InventoryMovementsResource = untuk adjustment manual (optional)

---

## E. Checklist Setelah Perubahan

✅ 1. Migration sudah diubah:
   - `inventory_movements`: product_id → inventory_item_id, quantity integer → decimal
   - `stock_levels`: product_id → inventory_item_id, quantity fields integer → decimal

✅ 2. Models sudah diupdate:
   - InventoryMovement: relasi ke InventoryItem, quantity decimal
   - StockLevel: relasi ke InventoryItem, quantity fields decimal
   - InventoryItem: tambah relasi stockLevels & inventoryMovements

✅ 3. Filament Resources sudah disesuaikan:
   - StockLevelsResource: semua field read-only, pakai inventory_item_id
   - InventoryMovementsResource: pakai inventory_item_id, quantity decimal
   - InventoryItemsResource: tidak ada field stok yang bisa diedit manual

✅ 4. Tidak ada lagi referensi ke `product_id` di:
   - inventory_movements table
   - stock_levels table
   - model terkait (ada deprecated alias untuk backward compatibility)

---

## F. Files yang Diubah

### Migrations:
- `database/migrations/2024_10_04_002600_create_inventory_movements_table.php`
- `database/migrations/2024_10_04_002700_create_stock_levels_table.php`

### Models:
- `app/Models/InventoryMovement.php`
- `app/Models/StockLevel.php`
- `app/Models/InventoryItem.php`

### Filament Resources:
- `app/Filament/Owner/Resources/StockLevels/StockLevelResource.php`
- `app/Filament/Owner/Resources/InventoryMovements/InventoryMovementResource.php`
- `app/Filament/Owner/Resources/InventoryMovements/Schemas/InventoryMovementForm.php`
- `app/Filament/Owner/Resources/InventoryMovements/Tables/InventoryMovementsTable.php`

---

## G. Catatan Penting

1. **Backward Compatibility**
   - Model InventoryMovement & StockLevel masih punya deprecated method `product()` untuk backward compatibility
   - Method `getOrCreateForProduct()` di StockLevel sudah deprecated

2. **Scope Terbatas**
   - Tidak menyentuh logic: cogs_history, cogs_details, perhitungan otomatis COGS (Wave 3)
   - Tidak mengubah schema products, recipes, recipe_items (sudah Wave 1)

3. **Future Work**
   - Logic untuk maintain StockLevels dari InventoryMovements akan di Wave 3
   - Service untuk auto-update stock levels saat ada movement

---

**Wave 2 selesai!** ✅

Stok sekarang hanya ditrack di level inventory_items, per store, dengan quantity decimal. Schema sudah konsisten dan tidak ambigu.

