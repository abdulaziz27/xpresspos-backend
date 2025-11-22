# Analisis Tabel UOM & Unit - Laporan Pembersihan

## 📋 Ringkasan Eksekutif

Setelah scan semua migration dan model yang mengandung kata "uom" atau "unit", berikut adalah tabel yang **TIDAK DIPAKAI** di logic bisnis dan bisa dihapus/ignore:

### ❌ Tabel yang Bisa Dihapus/Ignore

| Tabel | Status | Alasan |
|-------|--------|--------|
| **`uom_conversions`** | ❌ **TIDAK DIPAKAI** | Hanya dipakai di RelationManager yang sudah di-hide. Tidak ada logic bisnis yang menggunakan konversi runtime. |

---

## ✅ Tabel UOM yang DIPAKAI (Tetap)

### 1. `uoms` Table
**Status:** ✅ **DIPAKAI** - Tetap diperlukan

**Referensi di Schema:**
- `inventory_items.uom_id` (FK ke `uoms.id`)
- `recipe_items.uom_id` (FK ke `uoms.id`)
- `purchase_order_items.uom_id` (FK ke `uoms.id`)
- `inventory_transfer_items.uom_id` (FK ke `uoms.id`)

**Model:** `App\Models\Uom` - Aktif dipakai

**Resource:** `UomResource` (di-hide dari navigation, tapi tetap bisa diakses untuk master data)

**Kesimpulan:** Tabel ini adalah **source of truth** untuk satuan pengukuran. Tetap diperlukan.

---

## ❌ Tabel UOM yang TIDAK DIPAKAI

### 1. `uom_conversions` Table
**Status:** ❌ **TIDAK DIPAKAI** di logic bisnis

**Migration:** `database/migrations/2024_10_04_000860_create_uom_conversions_table.php`

**Model:** `App\Models\UomConversion` - Sudah deprecated dengan comment:
```php
/**
 * @deprecated UOM conversions are not used in runtime for Wave UOM Simplification.
 * All quantities are stored in base UOM from inventory_items.uom_id.
 * This model and table are kept for future use, but conversion logic is disabled.
 */
```

**Penggunaan Saat Ini:**
1. ✅ `Uom` model - Relations `conversions()` dan `inverseConversions()` (hanya untuk RelationManager)
2. ✅ `ConversionsRelationManager` - RelationManager di `UomResource`
3. ✅ `UomConversionSeeder` - Seeder (tidak critical)
4. ✅ `VerifyMigrationIntegrity` command - Verification (tidak critical)

**TIDAK DIPAKAI di:**
- ❌ Services (tidak ada `CogsService`, `InventoryService`, dll yang menggunakan konversi)
- ❌ Controllers (tidak ada API endpoint yang menggunakan konversi)
- ❌ Business Logic (tidak ada runtime conversion logic)
- ❌ Resource yang visible (`UomResource` sudah di-hide dari navigation)

**Kesimpulan:** 
- Tabel ini **TIDAK DIPAKAI** di logic bisnis runtime
- Hanya dipakai di RelationManager yang sudah di-hide
- Bisa dihapus/ignore tanpa mempengaruhi sistem
- Jika ingin dihapus, perlu:
  1. Hapus migration (atau buat migration drop)
  2. Hapus model `UomConversion`
  3. Hapus relations di `Uom` model (`conversions()`, `inverseConversions()`)
  4. Hapus `ConversionsRelationManager`
  5. Update `VerifyMigrationIntegrity` command

---

## 📝 Kolom "unit" (String) - Bukan Masalah

### `recipes.yield_unit` (String)
**Status:** ✅ **DIPAKAI** - Bukan masalah

**Lokasi:** `recipes.yield_unit` (string, default 'piece')

**Penggunaan:**
- Form: `RecipeForm` - Input field
- Table: `RecipesTable` - Display dengan suffix
- API: `RecipeController` - Validation & update
- Widget: `RecipePerformanceWidget` - Display

**Kesimpulan:** 
- Ini adalah **string field untuk display label**, bukan FK ke `uoms`
- Digunakan untuk menampilkan unit hasil resep (misal: "piece", "serving", "portion")
- **Bukan untuk konversi**, hanya label
- Tidak perlu diubah ke `uom_id` karena ini berbeda dengan UOM bahan

---

## 🎯 Rekomendasi

### 1. Tabel `uom_conversions` - HAPUS/IGNORE
**Prioritas:** Medium (tidak urgent, tapi bisa dibersihkan)

**Opsi:**
- **Opsi A (Recommended):** Hapus sepenuhnya
  - Buat migration untuk drop table
  - Hapus model, RelationManager, seeder
  - Update `Uom` model (hapus relations)
  
- **Opsi B:** Keep tapi ignore
  - Biarkan tabel ada (untuk future use)
  - Pastikan tidak ada logic yang mengakses
  - Tambahkan comment di migration bahwa ini deprecated

### 2. Tabel `uoms` - TETAP
**Prioritas:** Critical - Jangan dihapus

### 3. Kolom `recipes.yield_unit` - TETAP
**Prioritas:** Low - Tidak perlu diubah (bukan masalah)

---

## 📊 Daftar File yang Terkait `uom_conversions`

### Files yang Perlu Diupdate jika Hapus:
1. `database/migrations/2024_10_04_000860_create_uom_conversions_table.php` - Migration
2. `app/Models/UomConversion.php` - Model (hapus)
3. `app/Models/Uom.php` - Hapus relations `conversions()` dan `inverseConversions()`
4. `app/Filament/Owner/Resources/Uoms/RelationManagers/ConversionsRelationManager.php` - Hapus
5. `app/Filament/Owner/Resources/Uoms/UomResource.php` - Hapus dari `getRelations()`
6. `database/seeders/UomConversionSeeder.php` - Hapus (optional)
7. `app/Console/Commands/VerifyMigrationIntegrity.php` - Update verification

---

## ✅ Checklist Pembersihan (jika memilih Opsi A)

- [ ] Buat migration untuk drop `uom_conversions` table
- [ ] Hapus `app/Models/UomConversion.php`
- [ ] Update `app/Models/Uom.php` (hapus relations)
- [ ] Hapus `app/Filament/Owner/Resources/Uoms/RelationManagers/ConversionsRelationManager.php`
- [ ] Update `app/Filament/Owner/Resources/Uoms/UomResource.php` (hapus dari getRelations)
- [ ] Hapus `database/seeders/UomConversionSeeder.php` (optional)
- [ ] Update `app/Console/Commands/VerifyMigrationIntegrity.php`
- [ ] Test: Pastikan tidak ada error saat akses `UomResource`
- [ ] Test: Pastikan tidak ada referensi ke `UomConversion` di kode

---

**Generated:** {{ date('Y-m-d H:i:s') }}
**Analyst:** AI Assistant
**Scope:** All migrations and models containing "uom" or "unit"

