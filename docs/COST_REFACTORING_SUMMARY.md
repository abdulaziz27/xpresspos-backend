# Ringkasan Refactoring Cost Management

## Tujuan
Menyederhanakan dan memperjelas alur COST di level products, recipes, recipe_items, dan inventory_items dengan prinsip:
- **Satu sumber kebenaran**: Cost resmi dari COGS, di level product/recipe hanya estimasi HPP
- **Simple untuk user & dev**: Cost dihitung otomatis, field cost read-only di UI
- **Scope terbatas**: Hanya menyentuh products, recipes, recipe_items, inventory_items.default_cost

---

## A. Perubahan di Migrations

### ✅ 1. `recipe_items` Table
**File:** `database/migrations/2024_10_04_003000_create_recipe_items_table.php`

**Perubahan:**
- Ganti `ingredient_product_id` (FK ke products) → `inventory_item_id` (FK ke inventory_items)
- Update index dari `ingredient_product_id` → `inventory_item_id`

**Alasan:**
- Bahan resep harus mengacu ke inventory_items (bahan mentah), bukan products (barang jadi)
- Cost dihitung dari `inventory_items.default_cost`

---

## B. Perubahan di Models

### ✅ 1. Product Model
**File:** `app/Models/Product.php`

**Perubahan:**
- Tambah method `getActiveRecipe(): ?Recipe` - ambil resep aktif
- Tambah method `recalculateCostPriceFromRecipe(): void` - update cost_price dari resep aktif

**Behavior:**
- `cost_price` = snapshot dari `recipes.cost_per_unit` (resep aktif)
- Bukan source of truth COGS, hanya estimasi HPP

---

### ✅ 2. Recipe Model
**File:** `app/Models/Recipe.php`

**Perubahan:**
- Update `recalculateCosts()` untuk handle division by zero (treat yield_quantity 0 sebagai 1)
- Auto-update product cost_price saat resep aktif di-recalc

**Behavior:**
- `total_cost` = sum(recipe_items.total_cost) - **auto-calculated**
- `cost_per_unit` = total_cost / yield_quantity - **auto-calculated**
- Tidak boleh diisi manual dari UI

---

### ✅ 3. RecipeItem Model
**File:** `app/Models/RecipeItem.php`

**Perubahan:**
- Ganti relasi `ingredient()` (ke Product) → `inventoryItem()` (ke InventoryItem)
- Tambah method `recalculateCosts(): void`
- Update event `saving` untuk panggil `recalculateCosts()`

**Behavior:**
- `unit_cost` = dari `inventory_item.default_cost` (jika belum di-set)
- `total_cost` = quantity × unit_cost - **auto-calculated**
- Event: saat saved/deleted, trigger `recipe->recalculateCosts()`

---

### ✅ 4. InventoryItem Model
**File:** `app/Models/InventoryItem.php`

**Perubahan:**
- Tambah relasi `recipeItems(): HasMany`

**Behavior:**
- `default_cost` = default planning cost per unit
- Digunakan sebagai `unit_cost` default saat membuat recipe_items

---

## C. Perubahan di Filament Resources

### ✅ 1. ProductResource
**File:** `app/Filament/Owner/Resources/Products/Schemas/ProductForm.php`
**File:** `app/Filament/Owner/Resources/Products/Tables/ProductsTable.php`
**File:** `app/Filament/Owner/Resources/Products/Pages/EditProduct.php`

**Perubahan:**
- **Form:** `cost_price` menjadi read-only dengan label "Estimasi HPP (dari resep)"
- **Table:** Tambah kolom `margin_percent` (virtual, calculated)
- **Page:** Tambah action button "Hitung HPP dari Resep"

**Behavior:**
- User tidak bisa input cost_price manual
- Cost_price dihitung dari resep aktif
- Margin % = (price - cost_price) / price * 100

---

### ✅ 2. RecipeForm
**File:** `app/Filament/Owner/Resources/Recipes/Schemas/RecipeForm.php`

**Perubahan:**
- **Recipe Items:**
  - Ganti `ingredient_product_id` → `inventory_item_id` (Select dari InventoryItem)
  - `unit_cost` menjadi read-only (dari inventory_item.default_cost)
  - `total_cost` menjadi read-only (calculated)
- **Recipe Summary:**
  - `total_cost` menjadi read-only dengan helper text "dihitung otomatis"
  - `cost_per_unit` menjadi read-only dengan helper text "dihitung otomatis"

**Behavior:**
- User input: inventory_item_id, quantity, unit
- Cost fields otomatis dihitung dan read-only

---

### ✅ 3. InventoryItemResource
**File:** `app/Filament/Owner/Resources/InventoryItems/InventoryItemResource.php`

**Perubahan:**
- Update label `default_cost` menjadi "Default Cost (per unit)"
- Tambah helper text: "Biaya default per satuan. Akan digunakan sebagai unit_cost saat membuat resep."

**Behavior:**
- Owner bisa set default_cost
- Default_cost digunakan sebagai unit_cost default di recipe_items

---

## D. Flow Cost Calculation

### 1. Saat membuat RecipeItem:
```
User input: inventory_item_id, quantity, unit
↓
RecipeItem::recalculateCosts():
  - unit_cost = inventory_item.default_cost (jika belum di-set)
  - total_cost = quantity × unit_cost
↓
RecipeItem::saved event:
  - Trigger recipe->recalculateCosts()
```

### 2. Saat Recipe di-recalc:
```
Recipe::recalculateCosts():
  - total_cost = sum(recipe_items.total_cost)
  - cost_per_unit = total_cost / yield_quantity
  - Jika resep aktif: update product.cost_price = cost_per_unit
```

### 3. Saat melihat Product:
```
Product.cost_price = snapshot dari Recipe.cost_per_unit (resep aktif)
- Read-only di form
- Bisa di-recalc manual via action button
```

---

## E. Checklist Testing

Setelah perubahan, test:

1. ✅ Buat 1 inventory item dengan default_cost
2. ✅ Buat 1 product
3. ✅ Buat 1 recipe untuk product tersebut
4. ✅ Tambah beberapa recipe_items (bahan + qty)
5. ✅ Verifikasi:
   - RecipeItem.unit_cost = default_cost inventory item
   - RecipeItem.total_cost = quantity × unit_cost
   - Recipe.total_cost = sum(total_cost semua recipe_items)
   - Recipe.cost_per_unit = total_cost / yield_quantity
   - Product.cost_price = recipe.cost_per_unit (jika resep aktif)

6. ✅ Di Filament:
   - Field cost di Recipe & RecipeItem tampil tapi read-only
   - Di Product, "Estimasi HPP" muncul dan sesuai
   - Tidak ada form yang minta user isi cost manual

---

## F. Files yang Diubah

### Migrations:
- `database/migrations/2024_10_04_003000_create_recipe_items_table.php`

### Models:
- `app/Models/Product.php`
- `app/Models/Recipe.php`
- `app/Models/RecipeItem.php`
- `app/Models/InventoryItem.php`

### Filament Resources:
- `app/Filament/Owner/Resources/Products/Schemas/ProductForm.php`
- `app/Filament/Owner/Resources/Products/Tables/ProductsTable.php`
- `app/Filament/Owner/Resources/Products/Pages/EditProduct.php`
- `app/Filament/Owner/Resources/Recipes/Schemas/RecipeForm.php`
- `app/Filament/Owner/Resources/InventoryItems/InventoryItemResource.php`

---

## G. Catatan Penting

1. **Migration sudah diubah** - karena masih dev stage, migration langsung diubah (tidak drop/add baru)
2. **Backward compatibility** - RecipeItem masih punya alias `ingredient()` untuk backward compatibility
3. **Auto-calculation** - Semua field cost dihitung otomatis, tidak boleh input manual
4. **Source of truth** - Cost resmi tetap dari COGS (cogs_history + cogs_details), ini hanya estimasi HPP
5. **Scope terbatas** - Tidak menyentuh inventory_movements, stock_levels, cogs_history, cogs_details

---

**Refactoring selesai!** ✅

