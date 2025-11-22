# Inventori CRUD Matrix - Laporan Implementasi

**Tanggal:** {{ date('Y-m-d H:i:s') }}  
**Panel:** Owner Panel (Filament v4)  
**Navigation Group:** Inventori

---

## 📋 Matrix CRUD per Resource

| Resource | Menu | Tabel | Create | Edit | Delete | Alasan |
|----------|------|-------|--------|------|--------|--------|
| **InventoryItemResource** | Bahan | `inventory_items` | ✅ | ✅ | ✅ | Master data. FK constraints akan mencegah delete jika sudah dipakai. |
| **StockLevelResource** | Stok per Toko | `stock_levels` | ❌ | ❌ | ❌ | Read-only. Agregat sistem dari inventory_movements. |
| **InventoryAdjustmentResource** | Penyesuaian Stok | `inventory_adjustments` | ✅ | ✅ (draft only) | ❌ | Audit trail. Adjustment = bukti koreksi stok. |
| **InventoryTransferResource** | Transfer Antar Toko | `inventory_transfers` | ✅ (>1 store) | ✅ (not received/cancelled) | ❌ | Audit trail. Pergerakan barang antar cabang. |
| **SupplierResource** | Supplier | `suppliers` | ✅ | ✅ | ✅ | Master data. FK di purchase_orders akan mencegah delete jika sudah dipakai. |
| **PurchaseOrderResource** | Purchase Order | `purchase_orders` | ✅ | ✅ (not received/closed/cancelled) | ❌ | Audit trail. Dokumen finansial. |

---

## 🔧 Implementasi di Filament Resources

### 1. InventoryItemResource (Bahan) - Full CRUD ✅

**File:** `app/Filament/Owner/Resources/InventoryItems/InventoryItemResource.php`

**Methods:**
```php
public static function canCreate(): bool { return true; }
public static function canEdit(Model $record): bool { return true; }
public static function canDelete(Model $record): bool { return true; }
```

**Table Actions:**
- ✅ Create button visible
- ✅ Edit action visible
- ✅ Delete bulk action visible

**Edit Page:**
- ✅ Delete button akan muncul (karena canDelete = true)
- ✅ FK constraints akan mencegah delete jika item digunakan di recipes, PO, dll.

---

### 2. StockLevelResource (Stok per Toko) - Read-only ❌

**File:** `app/Filament/Owner/Resources/StockLevels/StockLevelResource.php`

**Methods:**
```php
public static function canCreate(): bool { return false; }
public static function canEdit(Model $record): bool { return false; }
public static function canDelete(Model $record): bool { return false; }
public static function canForceDelete(Model $record): bool { return false; }
public static function canRestore(Model $record): bool { return false; }
public static function canDeleteAny(): bool { return false; }
public static function canForceDeleteAny(): bool { return false; }
```

**Table Actions:**
- ❌ No Create button
- ❌ No Edit action
- ❌ No Delete action
- ❌ No bulk actions

**Pages:**
- ✅ Hanya List page (view only)

---

### 3. InventoryAdjustmentResource (Penyesuaian Stok) - Create + Edit, NO Delete ❌

**File:** `app/Filament/Owner/Resources/InventoryAdjustments/InventoryAdjustmentResource.php`

**Methods:**
```php
public static function canCreate(): bool { return true; }
public static function canEdit(Model $record): bool { 
    return $record->status === InventoryAdjustment::STATUS_DRAFT; 
}
public static function canDelete(Model $record): bool { return false; }
public static function canForceDelete(Model $record): bool { return false; }
public static function canRestore(Model $record): bool { return false; }
```

**Table Actions:**
- ✅ Create button visible
- ✅ Edit action visible (hanya jika status = draft)
- ❌ No Delete bulk action (removed from bulkActions array)

**Edit Page:**
- ✅ Edit allowed (hanya jika status = draft)
- ❌ No Delete button (getHeaderActions() return empty array)

**Catatan:** Adjustment tidak boleh dihapus untuk audit trail.

---

### 4. InventoryTransferResource (Transfer Antar Toko) - Create + Edit, NO Delete ❌

**File:** `app/Filament/Owner/Resources/InventoryTransfers/InventoryTransferResource.php`

**Methods:**
```php
public static function canCreate(): bool { 
    // Only if tenant has more than 1 store
    return $stores->count() > 1; 
}
public static function canEdit(Model $record): bool { 
    return !in_array($record->status, [
        InventoryTransfer::STATUS_RECEIVED, 
        InventoryTransfer::STATUS_CANCELLED
    ]); 
}
public static function canDelete(Model $record): bool { return false; }
public static function canForceDelete(Model $record): bool { return false; }
public static function canRestore(Model $record): bool { return false; }
```

**Table Actions:**
- ✅ Create button visible (hanya jika tenant punya >1 store)
- ✅ Edit action visible (hanya jika status bukan received/cancelled)
- ❌ No Delete bulk action (removed from bulkActions array)

**Edit Page:**
- ✅ Edit allowed (hanya jika status bukan received/cancelled)
- ❌ No Delete button (getHeaderActions() return empty array)

**Catatan:** 
- Transfer tidak boleh dihapus untuk audit trail.
- Menu auto-hide jika tenant hanya punya 1 store (via `shouldRegisterNavigation()`).

---

### 5. SupplierResource (Supplier) - Full CRUD ✅

**File:** `app/Filament/Owner/Resources/Suppliers/SupplierResource.php`

**Methods:**
```php
public static function canCreate(): bool { return true; }
public static function canEdit(Model $record): bool { return true; }
public static function canDelete(Model $record): bool { return true; }
```

**Table Actions:**
- ✅ Create button visible
- ✅ Edit action visible
- ✅ Delete bulk action visible

**Edit Page:**
- ✅ Delete button akan muncul (karena canDelete = true)
- ✅ FK constraints akan mencegah delete jika supplier digunakan di purchase_orders.

---

### 6. PurchaseOrderResource (Purchase Order) - Create + Edit, NO Delete ❌

**File:** `app/Filament/Owner/Resources/PurchaseOrders/PurchaseOrderResource.php`

**Methods:**
```php
public static function canCreate(): bool { return true; }
public static function canEdit(Model $record): bool { 
    return !in_array($record->status, [
        PurchaseOrder::STATUS_RECEIVED, 
        PurchaseOrder::STATUS_CLOSED, 
        PurchaseOrder::STATUS_CANCELLED
    ]); 
}
public static function canDelete(Model $record): bool { return false; }
public static function canForceDelete(Model $record): bool { return false; }
public static function canRestore(Model $record): bool { return false; }
```

**Table Actions:**
- ✅ Create button visible
- ✅ Edit action visible (hanya jika status bukan received/closed/cancelled)
- ❌ No Delete bulk action (removed from bulkActions array)

**Edit Page:**
- ✅ Edit allowed (hanya jika status bukan received/closed/cancelled)
- ❌ No Delete button (getHeaderActions() return empty array)

**Catatan:** 
- Purchase Order tidak boleh dihapus untuk audit trail (dokumen finansial).
- Edit hanya bisa saat status draft/approved, tidak bisa saat received/closed/cancelled.

---

## 📊 Ringkasan CRUD Matrix

| Resource | canCreate | canEdit | canDelete | List Actions (Create/Delete) | Edit Actions (Delete) | Catatan Khusus |
|----------|-----------|---------|-----------|------------------------------|----------------------|----------------|
| **Bahan** | ✅ true | ✅ true | ✅ true | ✅ Create + Delete visible | ✅ Delete visible | Full CRUD - FK akan prevent delete jika dipakai |
| **Stok per Toko** | ❌ false | ❌ false | ❌ false | ❌ No Create/Delete | ❌ No Delete | Read-only - hanya view |
| **Penyesuaian Stok** | ✅ true | ✅ (draft only) | ❌ false | ✅ Create, ❌ No Delete | ❌ No Delete | Edit hanya saat draft - NO DELETE untuk audit |
| **Transfer Antar Toko** | ✅ (>1 store) | ✅ (not received/cancelled) | ❌ false | ✅ Create, ❌ No Delete | ❌ No Delete | Create hanya jika >1 store - NO DELETE untuk audit |
| **Supplier** | ✅ true | ✅ true | ✅ true | ✅ Create + Delete visible | ✅ Delete visible | Full CRUD - FK akan prevent delete jika dipakai |
| **Purchase Order** | ✅ true | ✅ (not received/closed/cancelled) | ❌ false | ✅ Create, ❌ No Delete | ❌ No Delete | Edit hanya saat draft/approved - NO DELETE untuk audit |

---

## 🔍 Validasi UI (Expected Behavior)

### Bahan (InventoryItemResource)
- ✅ Ada tombol "Tambah" di list page
- ✅ Bisa Edit dari list atau detail
- ✅ Bisa Delete dari list (bulk) atau detail
- ✅ FK constraints akan prevent delete jika item digunakan

### Stok per Toko (StockLevelResource)
- ❌ Tidak ada tombol "Tambah"
- ❌ Tidak ada Edit action
- ❌ Tidak ada Delete action
- ✅ Hanya view/list (read-only)

### Penyesuaian Stok (InventoryAdjustmentResource)
- ✅ Ada tombol "Tambah" di list page
- ✅ Bisa Edit (hanya saat status = draft)
- ❌ Tidak ada Delete button di list atau detail
- ✅ Audit trail preserved

### Transfer Antar Toko (InventoryTransferResource)
- ✅ Ada tombol "Tambah" (hanya jika tenant punya >1 store)
- ✅ Bisa Edit (hanya jika status bukan received/cancelled)
- ❌ Tidak ada Delete button di list atau detail
- ✅ Menu auto-hide jika tenant hanya 1 store
- ✅ Audit trail preserved

### Supplier (SupplierResource)
- ✅ Ada tombol "Tambah" di list page
- ✅ Bisa Edit dari list atau detail
- ✅ Bisa Delete dari list (bulk) atau detail
- ✅ FK constraints akan prevent delete jika supplier digunakan di PO

### Purchase Order (PurchaseOrderResource)
- ✅ Ada tombol "Tambah" di list page
- ✅ Bisa Edit (hanya saat status draft/approved, tidak bisa saat received/closed/cancelled)
- ❌ Tidak ada Delete button di list atau detail
- ✅ Audit trail preserved (dokumen finansial)

---

## 📝 File yang Diubah

### Resource Files:
1. ✅ `app/Filament/Owner/Resources/InventoryItems/InventoryItemResource.php`
   - Added: `canEdit()`, `canDelete()`
   - Added: `use Illuminate\Database\Eloquent\Model;`

2. ✅ `app/Filament/Owner/Resources/InventoryAdjustments/InventoryAdjustmentResource.php`
   - Updated: `canDelete()` → `return false`
   - Added: `canForceDelete()`, `canRestore()` → `return false`
   - Removed: `DeleteBulkAction` dari `bulkActions` array

3. ✅ `app/Filament/Owner/Resources/InventoryTransfers/InventoryTransferResource.php`
   - Updated: `canDelete()` → `return false`
   - Added: `canForceDelete()`, `canRestore()` → `return false`
   - Removed: `DeleteBulkAction` dari `bulkActions` array

4. ✅ `app/Filament/Owner/Resources/Suppliers/SupplierResource.php`
   - Added: `canEdit()`, `canDelete()`
   - Added: `use Illuminate\Database\Eloquent\Model;`

5. ✅ `app/Filament/Owner/Resources/PurchaseOrders/PurchaseOrderResource.php`
   - Updated: `canDelete()` → `return false`
   - Added: `canForceDelete()`, `canRestore()` → `return false`
   - Removed: `DeleteBulkAction` dari `bulkActions` array

6. ✅ `app/Filament/Owner/Resources/StockLevels/StockLevelResource.php`
   - Sudah benar (tidak perlu diubah) - semua can* methods return false

### Edit Page Files:
1. ✅ `app/Filament/Owner/Resources/InventoryAdjustments/Pages/EditInventoryAdjustment.php`
   - Added: `getHeaderActions()` return empty array (no delete button)

2. ✅ `app/Filament/Owner/Resources/InventoryTransfers/Pages/EditInventoryTransfer.php`
   - Added: `getHeaderActions()` return empty array (no delete button)

3. ✅ `app/Filament/Owner/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php`
   - Added: `getHeaderActions()` return empty array (no delete button)

---

## ✅ Konfirmasi Implementasi

### Resource Level Permissions:
- ✅ Semua resource memiliki method `canCreate()`, `canEdit()`, `canDelete()` yang sesuai matrix
- ✅ Resource yang tidak boleh delete memiliki `canForceDelete()` dan `canRestore()` return false

### Table Actions:
- ✅ Create button hanya muncul untuk resource yang `canCreate() = true`
- ✅ Delete bulk action dihapus dari resource yang tidak boleh delete
- ✅ Edit action visibility sesuai status (untuk adjustment, transfer, PO)

### Edit Page Actions:
- ✅ Delete button tidak muncul di Edit page untuk resource yang `canDelete() = false`
- ✅ `getHeaderActions()` di-override untuk return empty array pada resource no-delete

### Status-based Logic:
- ✅ `InventoryAdjustmentResource`: Edit hanya saat status = draft
- ✅ `InventoryTransferResource`: Edit hanya saat status bukan received/cancelled
- ✅ `PurchaseOrderResource`: Edit hanya saat status bukan received/closed/cancelled

### Auto-hide Logic:
- ✅ `InventoryTransferResource`: Menu auto-hide jika tenant hanya punya 1 store (via `shouldRegisterNavigation()`)

---

## 🎯 Summary

✅ **Full CRUD (3 resources):**
- InventoryItemResource (Bahan)
- SupplierResource (Supplier)

✅ **Create + Edit, NO Delete (3 resources):**
- InventoryAdjustmentResource (Penyesuaian Stok)
- InventoryTransferResource (Transfer Antar Toko)
- PurchaseOrderResource (Purchase Order)

✅ **Read-only (1 resource):**
- StockLevelResource (Stok per Toko)

**Status:** ✅ **SEMUA IMPLEMENTASI SELESAI**

**Tidak ada migration/schema yang diubah** - hanya logic di Filament resources dan pages.

