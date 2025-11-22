# Migration Files: Cost & Stock Management Analysis

Dokumen ini berisi migration aktual untuk tabel-tabel yang terkait dengan **cost & stock management**. Digunakan sebagai dasar untuk analisis dan refactoring schema.

---

## ✅ 1. Product-Side (Master Jualan)

### 1.1. `products` Table

**File:** `database/migrations/2024_10_04_000900_create_products_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('track_inventory')->default(false);
            // Variants handled by product_variants table
            $table->boolean('status')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index('track_inventory');
            $table->index('sort_order');
            $table->unique(['tenant_id', 'sku'], 'uk_products_tenant_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

**Field terkait cost:**
- `cost_price` (decimal 10,2, nullable) - ✅ **SNAPSHOT**: Auto-calculated dari `recipes.cost_per_unit` (resep aktif). Read-only di UI, diisi via `Product::recalculateCostPriceFromRecipe()`.

---

### 1.2. `product_variants` Table

**File:** `database/migrations/2024_10_04_001000_create_product_variants_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('value');
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['product_id', 'is_active']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_options'); // Fallback for old table name
    }
};
```

**Field terkait cost:**
- `price_adjustment` (decimal 10,2) - Hanya untuk harga jual, tidak ada cost adjustment

---

### 1.3. `recipes` Table

**File:** `database/migrations/2024_10_04_002900_create_recipes_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('yield_quantity', 10, 2)->default(1);
            $table->string('yield_unit')->default('piece');
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('cost_per_unit', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
```

**Field terkait cost:**
- `total_cost` (decimal 10,2) - ✅ **AUTO-CALCULATED**: Sum dari `recipe_items.total_cost`. Di-update via `Recipe::recalculateCosts()`.
- `cost_per_unit` (decimal 10,2) - ✅ **AUTO-CALCULATED**: `total_cost / yield_quantity`. Di-update via `Recipe::recalculateCosts()`. Read-only di UI.

**Catatan:** Recipe cost dihitung otomatis dari recipe_items. `total_cost` dan `cost_per_unit` adalah denormalized cache yang di-update via model events.

---

### 1.4. `recipe_items` Table

**File:** `database/migrations/2024_10_04_003000_create_recipe_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->decimal('quantity', 10, 3);
            $table->string('unit');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id']);
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
```

**Field terkait cost:**
- `unit_cost` (decimal 10,2) - ✅ **AUTO-CALCULATED**: Default dari `inventory_items.default_cost`. Di-update via `RecipeItem::recalculateCosts()`. Read-only di UI.
- `total_cost` (decimal 10,2) - ✅ **AUTO-CALCULATED**: `quantity * unit_cost`. Di-update via `RecipeItem::recalculateCosts()`. Read-only di UI.

**Catatan:** 
- ✅ **UPDATED (Wave 1)**: `inventory_item_id` → foreign key ke `inventory_items` (bukan `ingredient_product_id` ke `products`).
- Bahan resep sekarang mengacu ke `inventory_items`, bukan `products`.
- Cost fields dihitung otomatis dan read-only di UI.

---

### 1.5. `product_modifier_groups` Table (Konteks)

**File:** `database/migrations/2024_11_14_000025_create_product_modifier_groups_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates product_modifier_groups table for M:N relationship.
     */
    public function up(): void
    {
        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('modifier_group_id', 36);
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('modifier_group_id')->references('id')->on('modifier_groups')->onDelete('cascade');

            // Unique constraint
            $table->unique(['product_id', 'modifier_group_id'], 'uk_prod_mod_group');

            // Index
            $table->index('product_id', 'idx_prod_mod_groups_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_modifier_groups');
    }
};
```

**Tidak ada field cost** - Hanya relasi M:N antara products dan modifier_groups.

---

### 1.6. `modifier_groups` Table (Opsional - Konteks)

**File:** `database/migrations/2024_11_14_000023_create_modifier_groups_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates modifier_groups table for product modifiers (e.g., Size, Toppings).
     */
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_active'], 'idx_mod_groups_tenant_active');
            $table->index(['tenant_id', 'sort_order'], 'idx_mod_groups_tenant_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};
```

**Tidak ada field cost** - Hanya definisi grup modifier.

---

### 1.7. `modifier_items` Table (Opsional - Konteks)

**File:** `database/migrations/2024_11_14_000024_create_modifier_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates modifier_items table with denormalized store_id for POS performance.
     */
    public function up(): void
    {
        Schema::create('modifier_items', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('modifier_group_id', 36);
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_delta', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('modifier_group_id')->references('id')->on('modifier_groups')->onDelete('cascade');

            // Indexes for POS UI optimization
            $table->index(['tenant_id', 'is_active'], 'idx_mod_items_tenant_active');
            $table->index(['modifier_group_id', 'is_active'], 'idx_mod_items_group_active');
            $table->index(['modifier_group_id', 'sort_order'], 'idx_mod_items_group_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_items');
    }
};
```

**Field terkait cost:**
- `price_delta` (decimal 18,2) - Hanya untuk harga jual, tidak ada cost delta

---

## ✅ 2. Inventory-Side (Bahan & Stok Fisik)

### 2.1. `inventory_items` Table

**File:** `database/migrations/2024_10_04_000870_create_inventory_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('tenant_id', 36);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('name', 255);
                $table->string('sku', 100)->nullable();
                $table->string('category', 100)->nullable();
                $table->string('uom_id', 36);
                $table->boolean('track_lot')->default(false);
                $table->boolean('track_stock')->default(true);
                $table->decimal('min_stock_level', 18, 3)->default(0);
                $table->decimal('default_cost', 18, 4)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('uom_id')->references('id')->on('uoms')->onDelete('restrict');
                
                // Indexes for performance
                $table->index(['tenant_id', 'status'], 'idx_inv_items_tenant_status');
                $table->index(['tenant_id', 'name'], 'idx_inv_items_tenant_name');
                
                // Unique constraint: SKU must be unique per tenant (if provided)
                $table->unique(['tenant_id', 'sku'], 'uk_inv_items_tenant_sku');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
```

**Field terkait cost:**
- `default_cost` (decimal 18,4, nullable) - ✅ **DEFAULT COST**: Digunakan sebagai default `unit_cost` untuk `recipe_items` saat membuat resep. Bukan source of truth untuk COGS (yang dari `inventory_lots.unit_cost` atau `purchase_order_items.unit_cost`).

**Field terkait stock:**
- `min_stock_level` (decimal 18,3) - Global minimum stock level. Stock aktual per store ada di `stock_levels.min_stock_level` (bisa override per store).

---

### 2.2. `inventory_lots` Table

**File:** `database/migrations/2024_10_04_002550_create_inventory_lots_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates inventory_lots table for lot/batch tracking.
     */
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->string('lot_code');
            $table->date('mfg_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->decimal('initial_qty', 18, 3);
            $table->decimal('remaining_qty', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->enum('status', ['active', 'expired', 'depleted'])->default('active');
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'inventory_item_id'], 'idx_lots_store_item');
            $table->index(['store_id', 'exp_date'], 'idx_lots_store_exp');
            $table->index(['store_id', 'inventory_item_id', 'status'], 'idx_lots_store_item_status');

            // Unique constraint
            $table->unique(['store_id', 'lot_code'], 'uk_lots_store_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};
```

**Field terkait cost:**
- `unit_cost` (decimal 18,4) - ✅ **SOURCE OF TRUTH**: Cost per batch/lot dari purchase order

**Field terkait stock:**
- `initial_qty` (decimal 18,3) - Qty awal saat lot dibuat
- `remaining_qty` (decimal 18,3) - ⚠️ **POTENSI ANOMALI**: Apakah ini calculated dari movements atau manual update?

---

### 2.3. `inventory_movements` Table

**File:** `database/migrations/2024_10_04_002600_create_inventory_movements_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['sale', 'purchase', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'return', 'waste']);
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'inventory_item_id']);
            $table->index(['store_id', 'type']);
            $table->index(['store_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
```

**Field terkait cost:**
- `unit_cost` (decimal 10,2, nullable) - ✅ **SYSTEM-MAINTAINED**: Diisi oleh sistem saat movement dibuat (dari `inventory_lots.unit_cost` atau `stock_levels.average_cost`). Optional untuk adjustment manual.
- `total_cost` (decimal 10,2, nullable) - ✅ **SYSTEM-MAINTAINED**: Diisi oleh sistem (quantity * unit_cost). Optional untuk adjustment manual.

**Field terkait stock:**
- `quantity` (decimal 18,3) - ✅ **UPDATED (Wave 2)**: Diubah dari integer ke decimal untuk support inventory yang bisa pecahan (kg, liter, dll). Quantity selalu positif, arah (tambah/kurang) ditentukan oleh `type`.
- `inventory_item_id` - ✅ **UPDATED (Wave 2)**: Diubah dari `product_id` ke `inventory_item_id`. Stock tracking sekarang hanya di level inventory_items, bukan products.

**Catatan:** 
- ✅ **UPDATED (Wave 2)**: Movements sekarang untuk `inventory_items`, bukan `products`.
- Stock tracking sekarang unified: hanya di `inventory_items` level per store.
- Products tidak lagi punya stock langsung (relasi `inventoryMovements()` dan `stockLevel()` di Product model deprecated).

---

### 2.4. `stock_levels` Table

**File:** `database/migrations/2024_10_04_002700_create_stock_levels_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->decimal('current_stock', 18, 3)->default(0);
            $table->decimal('reserved_stock', 18, 3)->default(0);
            $table->decimal('available_stock', 18, 3)->default(0);
            $table->decimal('min_stock_level', 18, 3)->default(0);
            $table->decimal('average_cost', 10, 2)->default(0);
            $table->decimal('total_value', 10, 2)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'inventory_item_id']);
            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'inventory_item_id']);
            $table->index(['store_id', 'current_stock']);
            $table->index(['store_id', 'available_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
```

**Field terkait cost:**
- `average_cost` (decimal 10,2) - ✅ **SYSTEM-MAINTAINED**: Di-update otomatis dari weighted average `inventory_movements.unit_cost`. Di-update via `StockLevel::updateFromMovement()`.
- `total_value` (decimal 10,2) - ✅ **SYSTEM-MAINTAINED**: Calculated (`current_stock * average_cost`). Di-update via `StockLevel::updateFromMovement()`. Read-only di UI.

**Field terkait stock:**
- `current_stock` (decimal 18,3) - ✅ **SYSTEM-MAINTAINED**: Di-update otomatis dari `inventory_movements` via `StockLevel::updateFromMovement()`. Read-only di UI.
- `reserved_stock` (decimal 18,3) - ✅ **SYSTEM-MAINTAINED**: Untuk order yang belum selesai. Di-update via `StockLevel::reserveStock()` dan `releaseReservedStock()`.
- `available_stock` (decimal 18,3) - ✅ **SYSTEM-MAINTAINED**: Calculated (`current_stock - reserved_stock`). Di-update via `StockLevel::updateFromMovement()`. Read-only di UI.
- `min_stock_level` (decimal 18,3) - Override per store (default dari `inventory_items.min_stock_level`).

**Catatan:**
- ✅ **UPDATED (Wave 2)**: `stock_levels` sekarang untuk `inventory_items`, bukan `products`.
- Stock tracking sekarang unified: hanya di `inventory_items` level per store.
- Semua field stock dan cost adalah system-maintained (denormalized cache), bukan user-editable.
- Products tidak lagi punya stock langsung.

---

### 2.5. `purchase_orders` Table

**File:** `database/migrations/2024_11_14_000015_create_purchase_orders_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates purchase_orders table for PO management.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('supplier_id', 36);
            $table->string('po_number');
            $table->enum('status', ['draft', 'approved', 'received', 'closed', 'cancelled'])->default('draft');
            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');

            // Indexes
            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'status'], 'idx_po_store_status');
            $table->index(['store_id', 'ordered_at'], 'idx_po_store_ordered');
            
            // Unique constraint
            $table->unique(['store_id', 'po_number'], 'uk_po_store_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
```

**Field terkait cost:**
- `total_amount` (decimal 18,2) - Total dari purchase_order_items

---

### 2.6. `purchase_order_items` Table

**File:** `database/migrations/2024_11_14_000016_create_purchase_order_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates purchase_order_items table for PO line items.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('inventory_item_id', 36);
            $table->string('uom_id', 36);
            $table->decimal('quantity_ordered', 18, 3);
            $table->decimal('quantity_received', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('uom_id')->references('id')->on('uoms')->onDelete('restrict');

            // Index
            $table->index('purchase_order_id', 'idx_po_items_po');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
```

**Field terkait cost:**
- `unit_cost` (decimal 18,4) - ✅ **SOURCE OF TRUTH**: Cost dari supplier saat PO dibuat
- `total_cost` (decimal 18,2) - Calculated (unit_cost * quantity_ordered)

**Catatan:**
- PO items untuk `inventory_items`, bukan `products`. Ini benar untuk raw materials.
- Saat PO received, seharusnya create `inventory_lots` dengan `unit_cost` dari PO items.

---

## ✅ 3. COGS (Biaya Pokok Penjualan)

### 3.1. `cogs_history` Table

**File:** `database/migrations/2024_10_04_002800_create_cogs_history_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cogs_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('quantity_sold');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cogs', 10, 2);
            $table->enum('calculation_method', ['fifo', 'lifo', 'weighted_average']);
            $table->json('cost_breakdown')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'product_id']);
            $table->index(['store_id', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cogs_history');
    }
};
```

**Field terkait cost:**
- `unit_cost` (decimal 10,2) - ⚠️ **POTENSI ANOMALI**: Calculated dari method (FIFO/LIFO/WA) atau manual?
- `total_cogs` (decimal 10,2) - Calculated (unit_cost * quantity_sold)
- `calculation_method` - Method yang dipakai untuk hitung COGS
- `cost_breakdown` (json) - Detail breakdown, mungkin link ke cogs_details

**Catatan:**
- COGS untuk `products`, bukan `inventory_items`. Ini benar untuk finished goods.
- Apakah COGS dihitung dari recipe cost atau dari actual inventory consumption?

---

### 3.2. `cogs_details` Table

**File:** `database/migrations/2024_11_14_000029_create_cogs_details_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates cogs_details table for granular COGS tracking.
     */
    public function up(): void
    {
        Schema::create('cogs_details', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignUuid('cogs_history_id')->constrained('cogs_history')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('inventory_item_id', 36);
            $table->string('lot_id', 36)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('lot_id')->references('id')->on('inventory_lots')->onDelete('set null');

            // Indexes
            $table->index('cogs_history_id', 'idx_cogs_details_history');
            $table->index('inventory_item_id', 'idx_cogs_details_inv_item');
            $table->index('order_item_id', 'idx_cogs_details_order_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cogs_details');
    }
};
```

**Field terkait cost:**
- `unit_cost` (decimal 18,4) - ✅ **SOURCE OF TRUTH**: Dari `inventory_lots.unit_cost` atau `lot_id`
- `total_cost` (decimal 18,2) - Calculated (unit_cost * quantity)

**Catatan:**
- `cogs_details` link ke `inventory_items` dan `inventory_lots`. Ini benar untuk track bahan yang dipakai.
- Apakah ini diisi saat order dibuat, atau saat order selesai?

---

## 📋 Summary: Status Perubahan & Source of Truth

### ✅ Perubahan yang Sudah Dibenahi (Wave 1, 2, 2.5)

1. **✅ Unified Stock Tracking (Wave 2)**
   - `inventory_movements` dan `stock_levels` sekarang untuk `inventory_items` (bukan `products`)
   - Stock tracking unified: hanya di `inventory_items` level per store
   - Products tidak lagi punya stock langsung (relasi deprecated)

2. **✅ Cost Fields - Source of Truth Jelas (Wave 1)**
   - `products.cost_price` - ✅ Snapshot dari `recipes.cost_per_unit` (resep aktif). Auto-calculated, read-only.
   - `recipes.total_cost` & `recipes.cost_per_unit` - ✅ Auto-calculated dari `recipe_items.total_cost`. Read-only di UI.
   - `recipe_items.unit_cost` - ✅ Auto-calculated dari `inventory_items.default_cost`. Read-only di UI.
   - `recipe_items.total_cost` - ✅ Auto-calculated (`quantity * unit_cost`). Read-only di UI.
   - `stock_levels.average_cost` - ✅ System-maintained dari weighted average `inventory_movements.unit_cost`.
   - `inventory_items.default_cost` - ✅ Default cost untuk planning/resep, bukan source of truth COGS.

3. **✅ Stock Fields - Source of Truth Jelas (Wave 2)**
   - `stock_levels.current_stock` - ✅ System-maintained dari `inventory_movements` via `StockLevel::updateFromMovement()`.
   - `stock_levels.available_stock` - ✅ System-maintained (`current_stock - reserved_stock`).
   - `inventory_lots.remaining_qty` - ⚠️ Masih perlu klarifikasi: calculated dari movements atau manual update?

4. **✅ Data Type Fixed (Wave 2)**
   - `inventory_movements.quantity` - ✅ Diubah ke `decimal(18,3)` untuk support inventory pecahan
   - `stock_levels.current_stock`, `reserved_stock`, `available_stock`, `min_stock_level` - ✅ Diubah ke `decimal(18,3)`
   - `cogs_history.quantity_sold` - ⚠️ Masih integer, perlu diubah di Wave 3?

5. **✅ Recipe Items Reference Fixed (Wave 1)**
   - `recipe_items.inventory_item_id` - ✅ Diubah dari `ingredient_product_id` ke `inventory_item_id` (FK ke `inventory_items`)
   - Bahan resep sekarang mengacu ke `inventory_items`, bukan `products`

### ✅ Yang Sudah Benar (Tidak Berubah)

1. **Purchase Orders sebagai Source of Truth untuk Cost**
   - `purchase_order_items.unit_cost` → `inventory_lots.unit_cost`
   - Flow: PO received → create inventory_lots dengan unit_cost dari PO

2. **COGS Details Link ke Inventory**
   - `cogs_details` link ke `inventory_items` dan `inventory_lots`
   - Bisa track bahan mana yang dipakai per order

3. **Inventory Lots untuk Batch Tracking**
   - `inventory_lots.unit_cost` sebagai source of truth per batch
   - Support FIFO/LIFO untuk COGS calculation

---

## 🎯 Status Refactoring

### ✅ Completed (Wave 1, 2, 2.5)

1. **✅ Cost Source of Truth - CLEAR**
   - Product cost: Snapshot dari recipe (`recipes.cost_per_unit` untuk resep aktif)
   - Recipe cost: Auto-calculated dari `recipe_items.total_cost`
   - Recipe items cost: Auto-calculated dari `inventory_items.default_cost`

2. **✅ Stock Source of Truth - CLEAR**
   - Stock levels: System-maintained dari `inventory_movements` via `StockLevel::updateFromMovement()`
   - Available stock: System-maintained (`current_stock - reserved_stock`)
   - Stock tracking unified: hanya di `inventory_items` level per store

3. **✅ Unified Stock Tracking - COMPLETED**
   - Products dan inventory_items terpisah: products untuk jualan, inventory_items untuk bahan/stok fisik
   - Stock tracking hanya di `inventory_items` level
   - Products tidak lagi punya stock langsung

4. **✅ Data Types Fixed - COMPLETED**
   - `inventory_movements.quantity` → `decimal(18,3)`
   - `stock_levels` quantity fields → `decimal(18,3)`

5. **✅ Auto-Calculated Fields - CLEAR**
   - Semua cost fields di recipe/recipe_items: auto-calculated, read-only di UI
   - Semua stock fields di stock_levels: system-maintained, read-only di UI
   - Clear update mechanism via model events dan service methods

### ⚠️ Remaining Questions (Wave 3)

1. **COGS Calculation:**
   - Direct product COGS (non-recipe) sekarang deprecated
   - Recipe-based COGS sudah bekerja
   - Perlu redesign untuk full inventory-item-based COGS di Wave 3?

2. **Inventory Lots Remaining Qty:**
   - Apakah `inventory_lots.remaining_qty` calculated dari movements atau manual update?
   - Perlu klarifikasi dan implementasi jika belum ada

3. **COGS History Quantity:**
   - `cogs_history.quantity_sold` masih integer, perlu diubah ke decimal?

4. **Product-InventoryItem Mapping:**
   - Untuk products yang track_inventory, perlu mapping ke inventory_items?
   - Atau products dengan track_inventory sudah tidak relevan?

---

## 📊 Summary: Source of Truth Mapping

### Cost Flow:
1. **Purchase Order** → `purchase_order_items.unit_cost` (source of truth untuk cost per batch)
2. **Inventory Lots** → `inventory_lots.unit_cost` (dari PO items saat received)
3. **Inventory Items** → `inventory_items.default_cost` (default untuk planning/resep)
4. **Recipe Items** → `recipe_items.unit_cost` (auto dari `inventory_items.default_cost`)
5. **Recipes** → `recipes.cost_per_unit` (auto dari sum `recipe_items.total_cost`)
6. **Products** → `products.cost_price` (snapshot dari `recipes.cost_per_unit`)

### Stock Flow:
1. **Inventory Movements** → Semua pergerakan stok (source of truth untuk history)
2. **Stock Levels** → Denormalized cache per store per inventory_item (system-maintained)
3. **Inventory Lots** → Batch tracking dengan remaining_qty (perlu klarifikasi update mechanism)

---

**File ini sudah di-update sesuai dengan perubahan Wave 1, 2, dan 2.5.**

---

## 📝 Changelog: Perubahan yang Sudah Dilakukan

### Wave 1: Cost Refactoring (Completed)
- ✅ `recipe_items.ingredient_product_id` → `inventory_item_id` (FK ke `inventory_items`)
- ✅ `recipe_items.unit_cost` & `total_cost` → Auto-calculated, read-only di UI
- ✅ `recipes.total_cost` & `cost_per_unit` → Auto-calculated dari recipe_items, read-only di UI
- ✅ `products.cost_price` → Snapshot dari `recipes.cost_per_unit`, read-only di UI
- ✅ Model events untuk auto-calculation: `RecipeItem::recalculateCosts()`, `Recipe::recalculateCosts()`, `Product::recalculateCostPriceFromRecipe()`

### Wave 2: Inventory & Stock Simplification (Completed)
- ✅ `inventory_movements.product_id` → `inventory_item_id` (FK ke `inventory_items`)
- ✅ `inventory_movements.quantity` → `decimal(18,3)` (dari integer)
- ✅ `stock_levels.product_id` → `inventory_item_id` (FK ke `inventory_items`)
- ✅ `stock_levels.current_stock`, `reserved_stock`, `available_stock`, `min_stock_level` → `decimal(18,3)` (dari integer)
- ✅ Stock tracking unified: hanya di `inventory_items` level per store
- ✅ Products tidak lagi punya stock langsung (relasi deprecated)

### Wave 2.5: Service/API Refactor (Completed)
- ✅ Semua API endpoints sekarang pakai `inventory_item_id` (bukan `product_id`)
- ✅ Semua service methods sekarang pakai `inventory_item_id`
- ✅ `Product::inventoryMovements()` dan `Product::stockLevel()` deprecated
- ✅ Notifications dan jobs sekarang pakai `InventoryItem` bukan `Product`
- ✅ COGS calculation untuk non-recipe products deprecated (use recipe-based COGS)

### Remaining Ambiguities (Untuk Wave 3)
- ⚠️ `inventory_lots.remaining_qty` - Perlu klarifikasi: calculated dari movements atau manual update?
- ⚠️ `cogs_history.quantity_sold` - Masih integer, perlu diubah ke decimal?
- ⚠️ Product-InventoryItem mapping - Untuk products yang track_inventory, perlu mapping ke inventory_items?
- ⚠️ Full inventory-item-based COGS - Perlu redesign untuk non-recipe products?

---

**Last Updated:** 2024-12-19 (Wave 2.5 Complete)

