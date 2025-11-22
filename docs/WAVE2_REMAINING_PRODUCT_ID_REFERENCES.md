# Wave 2 - Remaining product_id References

## Status
Setelah Wave 2, masih ada banyak file yang menggunakan `product_id` untuk inventory operations. File-file ini perlu direfactor di wave berikutnya.

**PENTING:** File-file ini masih berfungsi karena ada deprecated alias di model, tapi akan error karena:
- Migration sudah diubah: `inventory_movements.product_id` dan `stock_levels.product_id` sudah tidak ada
- Model sudah diubah: `createMovement()` sekarang pakai `inventory_item_id`

## File yang Perlu Direfactor

### 🔴 Critical - Akan Error (Harus Segera Diperbaiki)

#### 1. API Controllers
**File:** `app/Http/Controllers/Api/V1/InventoryController.php`

**Masalah:**
- Line 100: `StockLevel::getOrCreateForProduct($productId, $storeId)` - akan throw exception
- Line 103: `InventoryMovement::where('product_id', $productId)` - kolom tidak ada
- Line 183: `InventoryMovement::with(['product:id,name,sku', ...])` - relasi tidak ada
- Line 341-342: `InventoryMovement::createMovement($product->id, ...)` - parameter salah (harus inventory_item_id)
- Line 353: `StockLevel::getOrCreateForProduct($product->id, $storeId)` - akan throw exception

**Action Required:**
- Ganti semua `product_id` → `inventory_item_id`
- Ganti `getOrCreateForProduct()` → `getOrCreateForInventoryItem()`
- Ganti `createMovement($productId, ...)` → `createMovement($inventoryItemId, ...)`
- Update query untuk pakai `inventory_item_id` bukan `product_id`

---

#### 2. Services
**File:** `app/Services/InventoryService.php`

**Masalah:**
- Line 28, 70, 126, 248: `StockLevel::getOrCreateForProduct($productId)` - akan throw exception
- Line 36-37: `InventoryMovement::createMovement($productId, ...)` - parameter salah
- Line 78-79: `InventoryMovement::createMovement($productId, ...)` - parameter salah
- Line 129-130: `InventoryMovement::createMovement($productId, ...)` - parameter salah
- Line 156: `StockLevel::with('product:id,name,sku')` - relasi tidak ada
- Line 180: `InventoryMovement::with('product:id,name,sku')` - relasi tidak ada
- Line 216: `StockLevel::with('product:id,name,sku,min_stock_level')` - relasi tidak ada
- Line 289: `StockLevel::where('product_id', $productId)` - kolom tidak ada

**Action Required:**
- Refactor semua method untuk pakai `inventory_item_id`
- Ganti `getOrCreateForProduct()` → `getOrCreateForInventoryItem()`
- Ganti `createMovement($productId, ...)` → `createMovement($inventoryItemId, ...)`
- Update eager loading: `product` → `inventoryItem`

---

#### 3. COGS Service
**File:** `app/Services/CogsService.php`

**Masalah:**
- Line 66: `StockLevel::where('product_id', $ingredient->id)` - kolom tidak ada
- Line 228: `StockLevel::with('product:id,name,sku')` - relasi tidak ada
- Line 242: `$product = $stockLevel->product` - relasi tidak ada
- Line 249, 269: `InventoryMovement::where('product_id', $product->id)` - kolom tidak ada

**Action Required:**
- Refactor untuk pakai `inventory_item_id`
- Update query dan relasi

---

#### 4. COGS History Model
**File:** `app/Models/CogsHistory.php`

**Masalah:**
- Line 90: `StockLevel::getOrCreateForProduct($productId)` - akan throw exception
- Line 138, 193: `InventoryMovement::where('product_id', $product->id)` - kolom tidak ada

**Action Required:**
- Refactor untuk pakai `inventory_item_id`
- Update query

---

### 🟡 Medium Priority - Akan Error (Perlu Diperbaiki)

#### 5. FnB Inventory Service
**File:** `app/Services/FnBInventoryService.php`

**Masalah:**
- Line 124: `InventoryMovement::with('product')` - relasi tidak ada
- Line 126: `->groupBy('product_id')` - kolom tidak ada
- Line 130: `$product = $productMovements->first()->product` - relasi tidak ada
- Line 149-151: `InventoryMovement::create([..., 'product_id' => $product->id, ...])` - kolom tidak ada
- Line 184: `InventoryMovement::where('product_id', $product->id)` - kolom tidak ada

**Action Required:**
- Refactor untuk pakai `inventory_item_id`
- Update query dan relasi

---

#### 6. Flexible Inventory Service
**File:** `app/Services/FlexibleInventoryService.php`

**Masalah:**
- Line 111, 158, 200, 221, 280: `InventoryMovement::where('product_id', $product->id)` - kolom tidak ada

**Action Required:**
- Refactor untuk pakai `inventory_item_id`

---

#### 7. Sync Service
**File:** `app/Services/Sync/SyncService.php`

**Masalah:**
- Line 320: `InventoryMovement::where('product_id', $data['product_id'])` - kolom tidak ada
- Line 334: `InventoryMovement::create([..., 'product_id' => $data['product_id'], ...])` - kolom tidak ada
- Line 347-350: Update product stock (tidak relevan lagi, stock sekarang di StockLevel)

**Action Required:**
- Refactor untuk pakai `inventory_item_id`
- Hapus logic update product stock

---

#### 8. Reporting Service
**File:** `app/Services/Reporting/ReportService.php`

**Masalah:**
- Line 654: `InventoryMovement::where('product_id', $productId)` - kolom tidak ada

**Action Required:**
- Refactor untuk pakai `inventory_item_id`

---

#### 9. Inventory Report Controller
**File:** `app/Http/Controllers/Api/V1/InventoryReportController.php`

**Masalah:**
- Line 41: `StockLevel::with(['product' => ...])` - relasi tidak ada
- Line 113: `InventoryMovement::with(['product:id,name,sku', ...])` - relasi tidak ada

**Action Required:**
- Update eager loading: `product` → `inventoryItem`

---

### 🟢 Low Priority - Deprecated (Bisa Diabaikan Sementara)

#### 10. Product Controller
**File:** `app/Http/Controllers/Api/V1/ProductController.php`

**Masalah:**
- Line 293: `$product->inventoryMovements()->delete()` - relasi tidak ada
- Line 296: `$product->stockLevel()?->delete()` - relasi tidak ada

**Action Required:**
- Hapus atau refactor (products tidak lagi punya relasi langsung ke inventory)

---

#### 11. Product Model
**File:** `app/Models/Product.php`

**Masalah:**
- Line 116-119: `inventoryMovements()` - relasi tidak ada
- Line 124-127: `stockLevel()` - relasi tidak ada

**Action Required:**
- Hapus atau deprecate relasi ini

---

#### 12. Product Variant Model
**File:** `app/Models/ProductVariant.php`

**Masalah:**
- Line 119: `$stockLevel = $product->stockLevel` - relasi tidak ada

**Action Required:**
- Refactor untuk tidak pakai stockLevel dari product

---

#### 13. Notifications & Jobs
**File:** `app/Notifications/LowStockAlert.php`
**File:** `app/Jobs/SendLowStockNotification.php`

**Masalah:**
- Masih pakai `Product` dan `StockLevel` dengan relasi `product()`

**Action Required:**
- Update untuk pakai `InventoryItem` dan `StockLevel` dengan relasi `inventoryItem()`

---

## Strategi Refactoring

### Phase 1: Critical Fixes (Harus Segera)
1. **API Controllers** - Fix InventoryController
2. **Services** - Fix InventoryService, CogsService, CogsHistory

### Phase 2: Medium Priority
3. **Other Services** - Fix FnBInventoryService, FlexibleInventoryService, SyncService, ReportService
4. **Report Controllers** - Fix InventoryReportController

### Phase 3: Cleanup
5. **Models** - Remove deprecated relasi dari Product
6. **Notifications/Jobs** - Update untuk pakai InventoryItem

---

## Catatan Penting

1. **Mapping Product → InventoryItem:**
   - Tidak ada mapping langsung 1:1
   - Products adalah barang jadi, InventoryItems adalah bahan mentah
   - Untuk products yang track_inventory, perlu cari inventory_item yang sesuai (atau buat mapping baru)

2. **Backward Compatibility:**
   - Deprecated methods (`product()`, `getOrCreateForProduct()`) akan throw exception
   - Tidak bisa digunakan untuk backward compatibility

3. **Testing:**
   - Setelah refactor, test semua API endpoints
   - Test semua service methods
   - Test COGS calculation

---

**Status:** Dokumentasi ini akan digunakan untuk Wave 3 atau refactoring berikutnya.

