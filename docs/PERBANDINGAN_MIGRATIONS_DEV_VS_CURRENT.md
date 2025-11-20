# Perbandingan Migrations: Branch DEV vs Current Changes

**Tanggal Analisa:** 2024  
**Branch DEV:** `origin/dev`  
**Branch CURRENT:** `main` (with uncommitted changes)

---

## 📊 STATISTIK UMUM

| Item | Branch DEV | Current Changes | Selisih |
|------|-----------|-----------------|---------|
| **Total Migrations** | 55 files | 84 files | +29 files |
| **Files Modified** | - | 1 file | 1 file |
| **Files Baru (Untracked)** | - | 29 files | 29 files |
| **Files Dihapus** | - | 0 files | 0 files |

---

## 🔍 PERBEDAAN DETAIL

### **1. File yang Dimodifikasi**

#### **`database/migrations/2024_10_04_000100_create_users_table.php`**

**Perbedaan:**

**Di Branch DEV:**
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('midtrans_customer_id')->nullable();  // ❌ ADA
    $table->rememberToken();
    $table->timestamps();

    $table->index('store_id');
    $table->index('midtrans_customer_id');  // ❌ ADA
});
```

**Di Current Changes:**
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    // ✅ midtrans_customer_id DIHAPUS
    $table->rememberToken();
    $table->timestamps();

    $table->index('store_id');
    // ✅ index midtrans_customer_id DIHAPUS
});
```

**Alasan Perubahan:**
- Menghapus kolom `midtrans_customer_id` karena sistem tidak menggunakan Midtrans
- Sistem hanya menggunakan Xendit untuk payment gateway

---

### **2. Migration Files Baru (Tidak Ada di Branch DEV)**

Total: **29 files baru** yang belum di-commit

#### **A. Multi-Tenancy & User Access (3 files)**
1. `2024_10_03_000000_create_tenants_table.php` - Tabel untuk multi-tenancy
2. `2024_10_04_000150_create_user_tenant_access_table.php` - Pivot table user-tenant access dengan roles
3. `2024_10_04_000450_create_plan_features_table.php` - Normalisasi plan features

**Tujuan:** Mendukung arsitektur multi-tenant dan role-based access control

---

#### **B. Inventory Management - UOM & Items (4 files)**
4. `2024_10_04_000850_create_uoms_table.php` - Unit of Measurement (UOM)
5. `2024_10_04_000860_create_uom_conversions_table.php` - Konversi antar UOM
6. `2024_10_04_000870_create_inventory_items_table.php` - Master data inventory items
7. `2024_10_04_002550_create_inventory_lots_table.php` - Tracking inventory lots/batches

**Tujuan:** Sistem inventory yang lebih robust dengan UOM dan lot tracking

---

#### **C. Product Variants (1 file)**
8. `2024_10_04_001000_create_product_variants_table.php` - Product variants (duplikat dengan product_options?)

**Catatan:** Ada juga migration `2025_10_22_165338_rename_product_options_to_product_variants_table.php` yang rename `product_options` ke `product_variants`. Perlu dicek apakah ini duplikat atau memang diperlukan.

---

#### **D. Procurement & Suppliers (3 files)**
9. `2024_11_14_000014_create_suppliers_table.php` - Master data suppliers
10. `2024_11_14_000015_create_purchase_orders_table.php` - Purchase orders
11. `2024_11_14_000016_create_purchase_order_items_table.php` - Items dalam purchase order

**Tujuan:** Sistem procurement untuk pembelian dari supplier

---

#### **E. Inventory Adjustments & Transfers (4 files)**
12. `2024_11_14_000017_create_inventory_adjustments_table.php` - Inventory adjustments
13. `2024_11_14_000018_create_inventory_adjustment_items_table.php` - Items dalam adjustment
14. `2024_11_14_000019_create_inventory_transfers_table.php` - Transfer inventory antar store/location
15. `2024_11_14_000020_create_inventory_transfer_items_table.php` - Items dalam transfer

**Tujuan:** Manajemen inventory yang lebih lengkap (adjustment, transfer)

---

#### **F. Modifiers System (5 files)**
16. `2024_11_14_000023_create_modifier_groups_table.php` - Modifier groups (contoh: Size, Toppings)
17. `2024_11_14_000024_create_modifier_items_table.php` - Items dalam modifier group (contoh: Small, Medium, Large)
18. `2024_11_14_000025_create_product_modifier_groups_table.php` - Pivot: product - modifier groups
19. `2024_11_14_000026_create_order_item_modifiers_table.php` - Modifiers yang dipilih di order item
20. `2024_11_14_000027_create_modifier_recipe_items_table.php` - Recipe items untuk modifiers (untuk COGS calculation)

**Tujuan:** Sistem modifiers untuk produk (contoh: size, toppings, dll) dengan recipe tracking

---

#### **G. COGS Details (1 file)**
21. `2024_11_14_000029_create_cogs_details_table.php` - Detail COGS per item

**Tujuan:** Tracking COGS yang lebih detail

---

#### **H. Promotions & Vouchers (4 files)**
22. `2024_11_14_000030_create_promotions_table.php` - Master data promotions
23. `2024_11_14_000031_create_promotion_conditions_table.php` - Conditions untuk promotion
24. `2024_11_14_000032_create_promotion_rewards_table.php` - Rewards untuk promotion
25. `2024_11_14_000033_create_vouchers_table.php` - Voucher codes
26. `2024_11_14_000034_create_voucher_redemptions_table.php` - Voucher redemption history

**Tujuan:** Sistem promosi dan voucher yang lebih kompleks

---

#### **I. Order Discounts (2 files)**
27. `2024_11_14_000035_create_order_discounts_table.php` - Discounts di level order
28. `2024_11_14_000036_create_order_item_discounts_table.php` - Discounts di level order item

**Tujuan:** Tracking discounts yang lebih detail (order-level dan item-level)

---

#### **J. Midtrans Removal (1 file)**
29. `2025_11_18_093452_remove_midtrans_customer_id_from_users_table.php` - Migration untuk hapus kolom midtrans_customer_id

**Tujuan:** Migration untuk menghapus kolom `midtrans_customer_id` dari tabel `users`

**Catatan:** Migration ini mungkin redundant karena kita sudah hapus langsung dari migration `2024_10_04_000100_create_users_table.php`. Perlu dicek apakah migration ini masih diperlukan atau bisa dihapus.

---

## 📋 MIGRATIONS YANG SAMA (Ada di DEV dan CURRENT)

Semua 55 migrations yang ada di branch DEV juga ada di current changes. Tidak ada migration yang hilang.

**Daftar lengkap migrations yang sama:**
- Semua migrations dari `2024_10_04_000000` sampai `2024_10_04_003600`
- Semua migrations dari `2025_10_22` sampai `2025_11_09`

---

## 🎯 KESIMPULAN

### **Perubahan Utama:**

1. **Modifikasi 1 File:**
   - `2024_10_04_000100_create_users_table.php` - Hapus `midtrans_customer_id`

2. **29 Migration Files Baru:**
   - Multi-tenancy (3 files)
   - Inventory management (4 files)
   - Procurement (3 files)
   - Inventory adjustments & transfers (4 files)
   - Modifiers system (5 files)
   - COGS details (1 file)
   - Promotions & vouchers (4 files)
   - Order discounts (2 files)
   - Midtrans removal (1 file)

### **Tujuan Perubahan:**

1. **Code Cleanup:**
   - Hapus referensi Midtrans dari migration users table

2. **Feature Development:**
   - Persiapan untuk fitur-fitur baru:
     - Multi-tenancy architecture
     - Advanced inventory management
     - Procurement system
     - Modifiers system
     - Promotions & vouchers
     - Advanced discount tracking

### **Rekomendasi:**

1. **Review Migration Baru:**
   - Pastikan tidak ada duplikat (contoh: `product_variants` vs `product_options`)
   - Pastikan urutan migration benar (chronological order)
   - Pastikan foreign key dependencies sudah benar

2. **Migration `2025_11_18_093452_remove_midtrans_customer_id_from_users_table.php`:**
   - Perlu dicek apakah masih diperlukan
   - Jika kolom sudah dihapus dari migration `2024_10_04_000100`, migration ini mungkin redundant

3. **Commit Strategy:**
   - Pisahkan commit:
     - Commit 1: Hapus Midtrans (modifikasi users table)
     - Commit 2: Tambah migrations baru (grouped by feature)

---

**Last Updated:** Berdasarkan analisa git diff antara branch dev dan current changes

