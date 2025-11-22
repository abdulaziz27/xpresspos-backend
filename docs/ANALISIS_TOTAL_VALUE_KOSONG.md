# Analisis: Total Nilai Kosong (Rp0) di Stock Levels

## 🔍 Masalah

Di halaman "Stok per Toko", ada data dengan:
- **Stok Saat Ini:** 100.000 pcs ✅
- **Total Nilai:** Rp0 ❌ (seharusnya ada nilai)

## 📊 Root Cause Analysis

### 1. Bagaimana Total Nilai Dihitung?

```php
// app/Models/StockLevel.php line 102
$this->total_value = $this->current_stock * $this->average_cost;
```

**Kesimpulan:** `total_value = 0` karena `average_cost = 0`

### 2. Bagaimana Average Cost Di-update?

`average_cost` hanya di-update jika:
- Movement adalah **stock increase** (`adjustment_in`, `purchase`, `transfer_in`, `return`)
- Movement punya **`unit_cost`** (tidak null dan > 0)

```php
// app/Models/StockLevel.php line 91-92
if ($movement->isStockIncrease() && $movement->unit_cost) {
    $this->recalculateAverageCost($movement);
}
```

### 3. Kapan Unit Cost Bisa 0 atau Null?

**Skenario 1: Adjustment Stok Awal (Inisialisasi)**
- User buat Penyesuaian Stok untuk stok awal
- `unit_cost` di adjustment item diambil dari:
  1. `StockLevel.average_cost` (jika ada dan > 0)
  2. Fallback ke `InventoryItem.default_cost` (jika StockLevel tidak ada atau average_cost = 0)

**Masalah:**
- Jika `InventoryItem.default_cost` juga **0 atau null**, maka `unit_cost` akan 0
- Movement dibuat dengan `unit_cost = 0`
- `average_cost` tidak ter-update (karena kondisi `$movement->unit_cost` false)
- `total_value` tetap 0

**Skenario 2: Movement Tanpa Unit Cost**
- Movement dibuat tanpa `unit_cost` (misalnya dari API atau sync)
- `average_cost` tidak ter-update
- `total_value` tetap 0

## ✅ Solusi

### Opsi 1: Pastikan InventoryItem.default_cost Terisi (Recommended)

**Untuk stok awal via Penyesuaian Stok:**
1. Pastikan `InventoryItem.default_cost` sudah diisi saat buat bahan
2. Atau edit bahan dan isi `default_cost` sebelum buat adjustment

**Flow yang benar:**
```
1. Buat Bahan → Isi default_cost (misalnya Rp 10.000/kg)
2. Buat Penyesuaian Stok → unit_cost akan auto-ambil dari default_cost
3. Approve → Movement dibuat dengan unit_cost = default_cost
4. StockLevel.average_cost ter-update = default_cost
5. total_value = current_stock * average_cost ✅
```

### Opsi 2: Fallback Logic di Adjustment Item (Better UX)

**Perbaikan di `ItemsRelationManager`:**
- Jika `StockLevel.average_cost = 0` dan `InventoryItem.default_cost = 0/null`
- Tampilkan warning atau require user input `unit_cost` manual

**Perbaikan di `InventoryAdjustment::generateInventoryMovements()`:**
- Jika `unit_cost = 0/null` untuk adjustment_in, gunakan `InventoryItem.default_cost`
- Atau throw exception jika tidak ada cost

### Opsi 3: Recalculate Average Cost dari Semua Movements (Backfill)

**Command untuk backfill data existing:**
```php
// Recalculate average_cost dan total_value untuk semua StockLevel
StockLevel::chunk(100, function ($stockLevels) {
    foreach ($stockLevels as $stockLevel) {
        // Get all stock-in movements with unit_cost
        $movements = InventoryMovement::where('inventory_item_id', $stockLevel->inventory_item_id)
            ->where('store_id', $stockLevel->store_id)
            ->whereIn('type', ['purchase', 'adjustment_in', 'transfer_in', 'return'])
            ->whereNotNull('unit_cost')
            ->where('unit_cost', '>', 0)
            ->orderBy('created_at')
            ->get();
        
        if ($movements->isEmpty()) {
            // No movements with cost, use InventoryItem.default_cost
            $inventoryItem = $stockLevel->inventoryItem;
            if ($inventoryItem && $inventoryItem->default_cost > 0) {
                $stockLevel->average_cost = $inventoryItem->default_cost;
                $stockLevel->total_value = $stockLevel->current_stock * $stockLevel->average_cost;
                $stockLevel->save();
            }
            continue;
        }
        
        // Recalculate weighted average
        $totalValue = 0;
        $totalQuantity = 0;
        
        foreach ($movements as $movement) {
            $totalValue += $movement->quantity * $movement->unit_cost;
            $totalQuantity += $movement->quantity;
        }
        
        if ($totalQuantity > 0) {
            $stockLevel->average_cost = $totalValue / $totalQuantity;
            $stockLevel->total_value = $stockLevel->current_stock * $stockLevel->average_cost;
            $stockLevel->save();
        }
    }
});
```

## 🎯 Rekomendasi

**Untuk data existing:**
1. ✅ Jalankan command backfill (Opsi 3) untuk recalculate `average_cost` dan `total_value`
2. ✅ Pastikan semua `InventoryItem` punya `default_cost` terisi

**Untuk data baru:**
1. ✅ Pastikan `InventoryItem.default_cost` selalu diisi saat buat bahan
2. ✅ Atau implementasi Opsi 2 (fallback logic + validation)

## 📝 Checklist

- [ ] Cek apakah `InventoryItem.default_cost` terisi untuk bahan yang punya stok
- [ ] Cek apakah `InventoryMovement.unit_cost` terisi untuk movements yang sudah ada
- [ ] Buat command backfill untuk recalculate `average_cost` dan `total_value`
- [ ] Update validation di form InventoryItem: require `default_cost` jika `track_stock = true`
- [ ] Update validation di form Adjustment: require `unit_cost > 0` untuk adjustment_in

