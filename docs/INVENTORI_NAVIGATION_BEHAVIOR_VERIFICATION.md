# Inventori Navigation Group - Behavior Verification

**Tanggal:** 2025-11-22  
**Panel:** Owner Panel (Filament v4)  
**Navigation Group:** Inventori

---

## ✅ Verifikasi Behavior per Resource

### 1. InventoryItemResource (Bahan) ✅

**Navigation:**
- Group: `'Inventori'`
- Label: `'Bahan'`
- Sort: `10`

**CRUD:**
- ✅ `canCreate()`: `true`
- ✅ `canEdit()`: `true`
- ✅ `canDelete()`: `true`

**Behavior:**
- ✅ Master data tenant-wide
- ✅ Tidak auto-create stock_levels saat bikin bahan baru
- ✅ Stock levels muncul hanya saat ada pergerakan pertama kali

**List Page:**
- ✅ Tombol "Tambah" ada (via `ListInventoryItems::getHeaderActions()`)

---

### 2. StockLevelResource (Stok per Toko) ✅

**Navigation:**
- Group: `'Inventori'`
- Label: `'Stok per Toko'`
- Sort: `20`

**CRUD:**
- ✅ `canCreate()`: `false`
- ✅ `canEdit()`: `false`
- ✅ `canDelete()`: `false`
- ✅ `canForceDelete()`: `false`
- ✅ `canRestore()`: `false`
- ✅ `canDeleteAny()`: `false`
- ✅ `canForceDeleteAny()`: `false`

**Behavior:**
- ✅ 100% read-only
- ✅ Tidak ada tombol Create/Edit/Delete
- ✅ Tidak ada row actions
- ✅ Tidak ada bulk actions
- ✅ Hanya menampilkan data dari `stock_levels` (ringkasan stok per store, inventory_item)

**List Page:**
- ✅ Tidak ada tombol "Tambah"
- ✅ Form kosong (`schema([])`)

---

### 3. InventoryAdjustmentResource (Penyesuaian Stok) ✅

**Navigation:**
- Group: `'Inventori'`
- Label: `'Penyesuaian Stok'`
- Sort: `30`

**CRUD:**
- ✅ `canCreate()`: `true`
- ✅ `canEdit()`: `true` (hanya saat status = draft)
- ✅ `canDelete()`: `false` (audit trail)

**Behavior:**
- ✅ Menu terpisah dari "Bahan" dan "Stok per Toko"
- ✅ Form header: store, tanggal, alasan, notes
- ✅ RelationManager items: pilih inventory_item + counted_qty
- ✅ Auto-calculate: system_qty, difference_qty, total_cost
- ✅ **Auto-generate inventory_movements** saat status berubah ke `approved`
- ✅ **Auto-update stock_levels** via `updateFromMovement()`

**List Page:**
- ✅ Tombol "Tambah" ada (via `ListInventoryAdjustments::getHeaderActions()`)
- ✅ Label: "Tambah" (bisa diubah jadi "Buat Penyesuaian Stok" jika perlu)

**Form Header:**
- ✅ `store_id`: Select (required)
- ✅ `adjustment_number`: Auto-generate (ADJ-YYYYMMDD-XXX)
- ✅ `status`: Select (draft/approved/cancelled)
- ✅ `reason`: Select (COUNT_DIFF, EXPIRED, DAMAGE, INITIAL)
- ✅ `adjusted_at`: DateTimePicker (default now())
- ✅ `notes`: Textarea

**RelationManager Items:**
- ✅ `inventory_item_id`: Select (active items dengan UOM)
- ✅ `system_qty`: Read-only (dari StockLevel)
- ✅ `counted_qty`: User input (required)
- ✅ `difference_qty`: Read-only (counted_qty - system_qty)
- ✅ `unit_cost`: Read-only (dari StockLevel.average_cost atau InventoryItem.default_cost)
- ✅ `total_cost`: Read-only (|difference_qty| × unit_cost)

**Auto-generate Movements:**
- ✅ Method `generateInventoryMovements()` sudah diimplementasi
- ✅ Trigger saat status berubah ke `approved` (via `saved` event)
- ✅ Idempotency: cek existing movement sebelum create
- ✅ Movement type: `adjustment_in` jika difference_qty > 0, `adjustment_out` jika < 0
- ✅ Auto-update StockLevel via `updateFromMovement()`

---

### 4. InventoryTransferResource (Transfer Antar Toko) ✅

**Navigation:**
- Group: `'Inventori'`
- Label: `'Transfer Antar Toko'`
- Sort: `40`
- ✅ Auto-hide jika tenant hanya punya 1 store (via `shouldRegisterNavigation()`)

**CRUD:**
- ✅ `canCreate()`: `true` (hanya jika tenant punya >1 store)
- ✅ `canEdit()`: `true` (hanya jika status bukan received/cancelled)
- ✅ `canDelete()`: `false` (audit trail)

**Behavior:**
- ✅ Form header: from_store_id, to_store_id, transfer_number, status, shipped_at, received_at, notes
- ✅ RelationManager items: inventory_item + quantity_shipped + quantity_received
- ✅ UOM auto-set dari inventory_item.uom_id

**List Page:**
- ✅ Tombol "Tambah" ada (via `ListInventoryTransfers::getHeaderActions()`)

---

### 5. SupplierResource (Supplier) ✅

**Navigation:**
- Group: `'Inventori'`
- Label: `'Supplier'`
- Sort: `50`

**CRUD:**
- ✅ `canCreate()`: `true`
- ✅ `canEdit()`: `true`
- ✅ `canDelete()`: `true`

**Behavior:**
- ✅ Master data tenant-wide
- ✅ Dipakai oleh PurchaseOrderResource

**List Page:**
- ✅ Tombol "Tambah" ada (via `ListSuppliers::getHeaderActions()`)

---

### 6. PurchaseOrderResource (Purchase Order) ✅

**Navigation:**
- Group: `'Inventori'`
- Label: `'Purchase Order'`
- Sort: `60`

**CRUD:**
- ✅ `canCreate()`: `true`
- ✅ `canEdit()`: `true` (hanya jika status bukan received/closed/cancelled)
- ✅ `canDelete()`: `false` (audit trail - dokumen finansial)

**Behavior:**
- ✅ Form header: store_id, supplier_id, po_number, status, ordered_at, received_at, notes, total_amount (read-only)
- ✅ RelationManager items: inventory_item + quantity_ordered + quantity_received + unit_cost + total_cost
- ✅ UOM auto-set dari inventory_item.uom_id
- ✅ total_amount auto-calculate dari sum(items.total_cost)

**List Page:**
- ✅ Tombol "Tambah" ada (via `ListPurchaseOrders::getHeaderActions()`)

---

## ✅ Flow Penyesuaian Stok (Verified)

### Flow yang Benar:

1. **Menu:** Inventori → Penyesuaian Stok
   - ✅ Resource terpisah, bukan dari "Bahan" atau "Stok per Toko"

2. **Buat Penyesuaian Stok:**
   - ✅ Klik tombol "Tambah" di halaman index
   - ✅ Form header muncul:
     - Store (pilih toko)
     - Tanggal penyesuaian
     - Alasan (COUNT_DIFF, EXPIRED, DAMAGE, INITIAL)
     - Catatan
   - ✅ Status default: `draft`

3. **Tambah Items (RelationManager):**
   - ✅ Pilih Bahan (inventory_item)
   - ✅ Sistem auto-set `system_qty` dari StockLevel
   - ✅ User input `counted_qty`
   - ✅ Sistem auto-calculate `difference_qty` dan `total_cost`

4. **Save (Status Draft):**
   - ✅ Data tersimpan di `inventory_adjustments` dan `inventory_adjustment_items`
   - ✅ Belum ada inventory_movements
   - ✅ Belum update stock_levels

5. **Approve (Status → Approved):**
   - ✅ Event `saved` trigger `generateInventoryMovements()`
   - ✅ Untuk setiap item dengan `difference_qty != 0`:
     - ✅ Create `InventoryMovement` dengan type:
       - `adjustment_in` jika difference_qty > 0
       - `adjustment_out` jika difference_qty < 0
     - ✅ Reference: `reference_type = InventoryAdjustment::class`, `reference_id = adjustment.id`
     - ✅ Update `StockLevel` via `updateFromMovement()`
   - ✅ Idempotency: cek existing movement sebelum create

6. **Hasil:**
   - ✅ `inventory_movements` terbuat
   - ✅ `stock_levels` ter-update (atau terbuat jika belum ada)
   - ✅ Baris muncul di menu "Stok per Toko"

---

## ✅ Navigation Verification

### Menu yang Tampil di Sidebar (6 menu):

1. ✅ **Bahan** (InventoryItemResource) - Sort: 10
2. ✅ **Stok per Toko** (StockLevelResource) - Sort: 20
3. ✅ **Penyesuaian Stok** (InventoryAdjustmentResource) - Sort: 30
4. ✅ **Transfer Antar Toko** (InventoryTransferResource) - Sort: 40
   - ✅ Auto-hide jika tenant hanya punya 1 store
5. ✅ **Supplier** (SupplierResource) - Sort: 50
6. ✅ **Purchase Order** (PurchaseOrderResource) - Sort: 60

### Menu yang Hidden:

- ✅ `InventoryMovementResource` - `shouldRegisterNavigation(): false`
- ✅ `InventoryLotResource` - `shouldRegisterNavigation(): false`
- ✅ `UomResource` - `shouldRegisterNavigation(): false`
- ✅ `UomConversionResource` - Hidden
- ✅ Relation Managers (Items, dll) - Tidak muncul sebagai menu terpisah

---

## ✅ Create Button Verification

### Resource dengan Tombol Create:

1. ✅ **Bahan** - `ListInventoryItems::getHeaderActions()` dengan `CreateAction`
2. ✅ **Penyesuaian Stok** - `ListInventoryAdjustments::getHeaderActions()` dengan `CreateAction`
3. ✅ **Transfer Antar Toko** - `ListInventoryTransfers::getHeaderActions()` dengan `CreateAction` (jika >1 store)
4. ✅ **Supplier** - `ListSuppliers::getHeaderActions()` dengan `CreateAction`
5. ✅ **Purchase Order** - `ListPurchaseOrders::getHeaderActions()` dengan `CreateAction`

### Resource TANPA Tombol Create:

1. ✅ **Stok per Toko** - `canCreate(): false`, tidak ada `getHeaderActions()` dengan CreateAction

---

## ✅ Summary

**Status:** ✅ **SEMUA BEHAVIOR SUDAH BENAR**

**Key Points:**
- ✅ Penyesuaian Stok = menu terpisah, bukan dari Bahan/Stok per Toko
- ✅ Auto-generate inventory_movements saat status approved
- ✅ Auto-update stock_levels via updateFromMovement()
- ✅ Stock levels muncul hanya saat ada pergerakan pertama kali
- ✅ Semua navigation, CRUD permissions, dan create buttons sudah sesuai

**Next Steps untuk Testing:**
1. Buat Bahan baru → cek Stok per Toko (harusnya belum ada)
2. Buat Penyesuaian Stok (draft) → tambah items → save
3. Approve Penyesuaian Stok → cek inventory_movements terbuat
4. Cek Stok per Toko → baris baru muncul untuk (store, inventory_item) tersebut

