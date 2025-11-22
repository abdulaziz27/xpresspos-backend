# Dokumentasi Skema Database XpressPOS

## Overview

Dokumen ini berisi dokumentasi lengkap skema database sistem XpressPOS Backend, termasuk struktur tabel, relasi, dan tujuan setiap tabel dalam konteks aplikasi.

## Struktur Database

### 1. Tabel Utama (Core Tables)

#### 1.1 stores

**Tujuan:** Menyimpan data store/toko dalam sistem multi-tenant
**Kolom:**

-   `id` (uuid, primary key) - ID unik store
-   `name` (string) - Nama store
-   `email` (string, unique) - Email store
-   `phone` (string, nullable) - Nomor telepon store
-   `address` (text, nullable) - Alamat store
-   `logo` (string, nullable) - Path logo store
-   `settings` (json, nullable) - Konfigurasi store
-   `status` (enum: active, inactive, suspended) - Status store
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `status` - Untuk filtering berdasarkan status
-   `created_at` - Untuk sorting berdasarkan waktu

**Relasi:**

-   `hasMany` users, products, orders, categories, members, tables, cash_sessions, expenses

#### 1.2 users

**Tujuan:** Menyimpan data pengguna sistem (staff, manager, owner)
**Kolom:**

-   `id` (bigint, primary key) - ID unik user
-   `store_id` (uuid, foreign key) - ID store terkait
-   `name` (string) - Nama lengkap user
-   `email` (string, unique) - Email user
-   `email_verified_at` (timestamp, nullable) - Waktu verifikasi email
-   `password` (string) - Password terenkripsi
-   `two_factor_secret` (text, nullable) - Secret 2FA
-   `two_factor_recovery_codes` (text, nullable) - Recovery codes 2FA
-   `two_factor_confirmed_at` (timestamp, nullable) - Waktu konfirmasi 2FA
-   `remember_token` (string, nullable) - Remember token
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id` - Untuk filtering berdasarkan store

**Relasi:**

-   `belongsTo` stores
-   `hasMany` orders, cash_sessions, expenses, activity_logs, staff_performances

### 2. Tabel Produk (Product Tables)

#### 2.1 categories

**Tujuan:** Mengelompokkan produk berdasarkan kategori
**Kolom:**

-   `id` (bigint, primary key) - ID unik kategori
-   `store_id` (uuid, foreign key) - ID store terkait
-   `name` (string) - Nama kategori
-   `slug` (string, nullable) - Slug untuk URL
-   `description` (text, nullable) - Deskripsi kategori
-   `image` (string, nullable) - Path gambar kategori
-   `is_active` (boolean) - Status aktif kategori
-   `sort_order` (integer) - Urutan tampil
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, is_active` - Untuk filtering berdasarkan store dan status
-   `sort_order` - Untuk sorting

**Relasi:**

-   `belongsTo` stores
-   `hasMany` products

#### 2.2 products

**Tujuan:** Menyimpan data produk yang dijual
**Kolom:**

-   `id` (bigint, primary key) - ID unik produk
-   `store_id` (uuid, foreign key) - ID store terkait
-   `category_id` (bigint, foreign key) - ID kategori
-   `name` (string) - Nama produk
-   `sku` (string, nullable, index) - Stock Keeping Unit
-   `description` (text, nullable) - Deskripsi produk
-   `image` (string, nullable) - Path gambar produk
-   `price` (decimal 10,2) - Harga jual
-   `cost_price` (decimal 10,2, nullable) - Harga beli
-   `track_inventory` (boolean) - Apakah track inventori
-   `stock` (integer) - Jumlah stok
-   `min_stock_level` (integer) - Level stok minimum
-   Variants handled by product_variants table
-   `status` (boolean) - Status aktif produk
-   `is_favorite` (boolean) - Apakah produk favorit
-   `sort_order` (integer) - Urutan tampil
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, status` - Untuk filtering berdasarkan store dan status
-   `store_id, category_id` - Untuk filtering berdasarkan store dan kategori
-   `track_inventory` - Untuk filtering produk yang track inventori
-   `sort_order` - Untuk sorting

**Relasi:**

-   `belongsTo` stores, categories
-   `hasMany` order_items, product_variants, product_price_histories, inventory_movements, stock_levels, cogs_history, recipes

#### 2.3 product_variants

**Tujuan:** Menyimpan variant produk (size, color, dll)
**Kolom:**

-   `id` (uuid, primary key) - ID unik variant
-   `store_id` (uuid, foreign key) - ID store terkait
-   `product_id` (bigint, foreign key) - ID produk
-   `name` (string) - Nama variant (Size, Color, dll)
-   `value` (string) - Nilai variant (Small, Red, dll)
-   `price_adjustment` (decimal 10,2) - Penyesuaian harga
-   `sort_order` (integer) - Urutan tampil
-   `is_active` (boolean) - Status aktif
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, products

#### 2.4 product_price_histories

**Tujuan:** Menyimpan riwayat perubahan harga produk
**Kolom:**

-   `id` (bigint, primary key) - ID unik riwayat
-   `store_id` (uuid, foreign key) - ID store terkait
-   `product_id` (bigint, foreign key) - ID produk
-   `old_price` (decimal 10,2) - Harga lama
-   `new_price` (decimal 10,2) - Harga baru
-   `changed_by` (bigint, foreign key) - User yang mengubah
-   `reason` (string, nullable) - Alasan perubahan
-   `created_at` (timestamp) - Waktu perubahan

**Relasi:**

-   `belongsTo` stores, products, users

#### 2.5 recipes

**Tujuan:** Menyimpan resep produk untuk Bill of Materials (BOM)
**Kolom:**

-   `id` (bigint, primary key) - ID unik resep
-   `store_id` (uuid, foreign key) - ID store terkait
-   `product_id` (bigint, foreign key) - ID produk
-   `name` (string) - Nama resep
-   `description` (text, nullable) - Deskripsi resep
-   `serving_size` (integer) - Ukuran porsi
-   `is_active` (boolean) - Status aktif
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, products
-   `hasMany` recipe_items

#### 2.6 recipe_items

**Tujuan:** Menyimpan item-item dalam resep
**Kolom:**

-   `id` (bigint, primary key) - ID unik item resep
-   `store_id` (uuid, foreign key) - ID store terkait
-   `recipe_id` (bigint, foreign key) - ID resep
-   `product_id` (bigint, foreign key) - ID produk bahan
-   `quantity` (decimal 8,2) - Jumlah bahan
-   `unit` (string) - Satuan
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, recipes, products

### 3. Tabel Pelanggan (Customer Tables)

#### 3.1 member_tiers

**Tujuan:** Menyimpan tier membership pelanggan
**Kolom:**

-   `id` (uuid, primary key) - ID unik tier
-   `store_id` (uuid, foreign key) - ID store terkait
-   `name` (string) - Nama tier
-   `description` (text, nullable) - Deskripsi tier
-   `min_points` (integer) - Poin minimum
-   `max_points` (integer, nullable) - Poin maksimum
-   `discount_percentage` (decimal 5,2, nullable) - Persentase diskon
-   `benefits` (json, nullable) - Manfaat tier
-   `is_active` (boolean) - Status aktif
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores
-   `hasMany` members

#### 3.2 members

**Tujuan:** Menyimpan data member/pelanggan
**Kolom:**

-   `id` (uuid, primary key) - ID unik member
-   `store_id` (uuid, foreign key) - ID store terkait
-   `member_number` (string, unique) - Nomor member
-   `name` (string) - Nama lengkap member
-   `email` (string, nullable) - Email member
-   `phone` (string, nullable) - Nomor telepon member
-   `date_of_birth` (date, nullable) - Tanggal lahir
-   `address` (text, nullable) - Alamat member
-   `loyalty_points` (integer) - Poin loyalitas
-   `total_spent` (decimal 12,2) - Total pengeluaran
-   `visit_count` (integer) - Jumlah kunjungan
-   `last_visit_at` (timestamp, nullable) - Waktu kunjungan terakhir
-   `tier_id` (uuid, foreign key, nullable) - ID tier member
-   `is_active` (boolean) - Status aktif
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, is_active` - Untuk filtering berdasarkan store dan status
-   `loyalty_points` - Untuk sorting berdasarkan poin
-   `tier_id` - Untuk filtering berdasarkan tier

**Relasi:**

-   `belongsTo` stores, member_tiers
-   `hasMany` orders, loyalty_point_transactions

#### 3.3 loyalty_point_transactions

**Tujuan:** Menyimpan transaksi poin loyalitas
**Kolom:**

-   `id` (bigint, primary key) - ID unik transaksi
-   `store_id` (uuid, foreign key) - ID store terkait
-   `member_id` (uuid, foreign key) - ID member
-   `order_id` (uuid, foreign key, nullable) - ID order terkait
-   `type` (enum: earned, redeemed, expired, adjusted) - Jenis transaksi
-   `points` (integer) - Jumlah poin
-   `description` (string, nullable) - Deskripsi transaksi
-   `expires_at` (timestamp, nullable) - Waktu kadaluarsa poin
-   `created_at` (timestamp) - Waktu transaksi

**Relasi:**

-   `belongsTo` stores, members, orders

### 4. Tabel Pesanan (Order Tables)

#### 4.1 tables

**Tujuan:** Menyimpan data meja untuk restoran
**Kolom:**

-   `id` (uuid, primary key) - ID unik meja
-   `store_id` (uuid, foreign key) - ID store terkait
-   `table_number` (string) - Nomor meja
-   `name` (string, nullable) - Nama meja
-   `capacity` (integer) - Kapasitas meja
-   `status` (enum: available, occupied, reserved, maintenance) - Status meja
-   `location` (string, nullable) - Lokasi meja
-   `description` (text, nullable) - Deskripsi meja
-   `qr_code` (string, nullable) - QR code meja
-   `customer_count` (integer, nullable) - Jumlah pelanggan
-   `is_active` (boolean) - Status aktif
-   `occupied_at` (timestamp, nullable) - Waktu mulai ditempati
-   `last_cleared_at` (timestamp, nullable) - Waktu terakhir dibersihkan
-   `current_order_id` (uuid, nullable) - ID order saat ini
-   `total_occupancy_count` (integer) - Total jumlah occupancy
-   `average_occupancy_duration` (decimal 8,2) - Durasi rata-rata occupancy
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, status` - Untuk filtering berdasarkan store dan status
-   `store_id, is_active` - Untuk filtering berdasarkan store dan status aktif
-   `store_id, table_number` (unique) - Untuk memastikan nomor meja unik per store
-   `occupied_at` - Untuk sorting berdasarkan waktu occupancy
-   `current_order_id` - Untuk referensi order saat ini

**Relasi:**

-   `belongsTo` stores
-   `hasMany` orders, table_occupancy_histories

#### 4.2 orders

**Tujuan:** Menyimpan data pesanan
**Kolom:**

-   `id` (uuid, primary key) - ID unik pesanan
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key, nullable) - ID user yang membuat pesanan
-   `member_id` (uuid, foreign key, nullable) - ID member
-   `table_id` (uuid, foreign key, nullable) - ID meja
-   `order_number` (string, unique) - Nomor pesanan
-   `status` (enum: draft, open, completed, cancelled) - Status pesanan
-   `subtotal` (decimal 12,2) - Subtotal
-   `tax_amount` (decimal 12,2) - Jumlah pajak
-   `discount_amount` (decimal 12,2) - Jumlah diskon
-   `service_charge` (decimal 12,2) - Service charge
-   `total_amount` (decimal 12,2) - Total amount
-   `payment_method` (string, nullable) - Metode pembayaran
-   `total_items` (integer) - Total item
-   `notes` (text, nullable) - Catatan
-   `completed_at` (timestamp, nullable) - Waktu selesai
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, status` - Untuk filtering berdasarkan store dan status
-   `store_id, created_at` - Untuk filtering berdasarkan store dan waktu
-   `status` - Untuk filtering berdasarkan status

**Relasi:**

-   `belongsTo` stores, users, members, tables
-   `hasMany` order_items, payments, refunds

#### 4.3 order_items

**Tujuan:** Menyimpan item-item dalam pesanan
**Kolom:**

-   `id` (uuid, primary key) - ID unik item pesanan
-   `store_id` (uuid, foreign key) - ID store terkait
-   `order_id` (uuid, foreign key) - ID pesanan
-   `product_id` (bigint, foreign key) - ID produk
-   `product_name` (string) - Nama produk (snapshot)
-   `product_sku` (string, nullable) - SKU produk (snapshot)
-   `quantity` (integer) - Jumlah
-   `unit_price` (decimal 10,2) - Harga satuan
-   `total_price` (decimal 12,2) - Total harga
-   `product_options` (json, nullable) - Snapshot opsi produk saat order dibuat (historical data, bukan FK)
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, order_id` - Untuk filtering berdasarkan store dan pesanan
-   `product_id` - Untuk filtering berdasarkan produk

**Relasi:**

-   `belongsTo` stores, orders, products

#### 4.4 table_occupancy_histories

**Tujuan:** Menyimpan riwayat occupancy meja
**Kolom:**

-   `id` (bigint, primary key) - ID unik riwayat
-   `store_id` (uuid, foreign key) - ID store terkait
-   `table_id` (uuid, foreign key) - ID meja
-   `order_id` (uuid, foreign key, nullable) - ID pesanan
-   `started_at` (timestamp) - Waktu mulai
-   `ended_at` (timestamp, nullable) - Waktu selesai
-   `duration_minutes` (integer, nullable) - Durasi dalam menit
-   `customer_count` (integer, nullable) - Jumlah pelanggan
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan

**Relasi:**

-   `belongsTo` stores, tables, orders

### 5. Tabel Pembayaran (Payment Tables)

#### 5.1 payment_methods

**Tujuan:** Menyimpan metode pembayaran yang tersedia
**Kolom:**

-   `id` (uuid, primary key) - ID unik metode pembayaran
-   `store_id` (uuid, foreign key) - ID store terkait
-   `name` (string) - Nama metode pembayaran
-   `type` (enum: cash, card, digital, bank_transfer) - Jenis metode
-   `is_active` (boolean) - Status aktif
-   `requires_verification` (boolean) - Apakah memerlukan verifikasi
-   `fee_percentage` (decimal 5,2, nullable) - Persentase fee
-   `fee_fixed` (decimal 8,2, nullable) - Fee tetap
-   `settings` (json, nullable) - Konfigurasi metode
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores
-   `hasMany` payments

#### 5.2 payments

**Tujuan:** Menyimpan data pembayaran
**Kolom:**

-   `id` (uuid, primary key) - ID unik pembayaran
-   `store_id` (uuid, foreign key) - ID store terkait
-   `order_id` (uuid, foreign key) - ID pesanan
-   `payment_method` (enum: cash, credit_card, debit_card, qris, bank_transfer, e_wallet) - Metode pembayaran
-   `gateway` (string, nullable) - Payment gateway
-   `gateway_transaction_id` (string, nullable) - ID transaksi gateway
-   `payment_method_id` (uuid, foreign key, nullable) - ID metode pembayaran
-   `invoice_id` (uuid, foreign key, nullable) - ID invoice
-   `gateway_fee` (decimal 8,2) - Fee gateway
-   `gateway_response` (json, nullable) - Response gateway
-   `amount` (decimal 12,2) - Jumlah pembayaran
-   `reference_number` (string, nullable) - Nomor referensi
-   `status` (enum: pending, completed, failed, cancelled) - Status pembayaran
-   `processed_at` (timestamp, nullable) - Waktu diproses
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, status` - Untuk filtering berdasarkan store dan status
-   `store_id, payment_method` - Untuk filtering berdasarkan store dan metode
-   `gateway, gateway_transaction_id` - Untuk integrasi gateway
-   `processed_at` - Untuk sorting berdasarkan waktu
-   `payment_method_id` - Untuk referensi metode pembayaran
-   `invoice_id` - Untuk referensi invoice

**Relasi:**

-   `belongsTo` stores, orders, payment_methods, invoices
-   `hasMany` refunds

#### 5.3 refunds

**Tujuan:** Menyimpan data refund
**Kolom:**

-   `id` (uuid, primary key) - ID unik refund
-   `store_id` (uuid, foreign key) - ID store terkait
-   `payment_id` (uuid, foreign key) - ID pembayaran
-   `amount` (decimal 12,2) - Jumlah refund
-   `reason` (string, nullable) - Alasan refund
-   `status` (enum: pending, completed, failed, cancelled) - Status refund
-   `processed_at` (timestamp, nullable) - Waktu diproses
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, payments

### 6. Tabel Inventori (Inventory Tables)

#### 6.1 stock_levels

**Tujuan:** Menyimpan level stok produk
**Kolom:**

-   `id` (bigint, primary key) - ID unik level stok
-   `store_id` (uuid, foreign key) - ID store terkait
-   `product_id` (bigint, foreign key) - ID produk
-   `current_stock` (integer) - Stok saat ini
-   `reserved_stock` (integer) - Stok yang direservasi
-   `available_stock` (integer) - Stok yang tersedia
-   `min_stock_level` (integer) - Level stok minimum
-   `max_stock_level` (integer, nullable) - Level stok maksimum
-   `last_updated_at` (timestamp) - Waktu update terakhir
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, products

#### 6.2 inventory_movements

**Tujuan:** Menyimpan pergerakan inventori
**Kolom:**

-   `id` (bigint, primary key) - ID unik pergerakan
-   `store_id` (uuid, foreign key) - ID store terkait
-   `product_id` (bigint, foreign key) - ID produk
-   `user_id` (bigint, foreign key, nullable) - ID user yang melakukan
-   `type` (enum: in, out, adjustment, transfer) - Jenis pergerakan
-   `quantity` (integer) - Jumlah
-   `unit_cost` (decimal 10,2, nullable) - Harga satuan
-   `total_cost` (decimal 12,2, nullable) - Total biaya
-   `reference_type` (string, nullable) - Tipe referensi
-   `reference_id` (string, nullable) - ID referensi
-   `reason` (string, nullable) - Alasan pergerakan
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pergerakan

**Relasi:**

-   `belongsTo` stores, products, users

#### 6.3 cogs_history

**Tujuan:** Menyimpan riwayat Cost of Goods Sold (COGS)
**Kolom:**

-   `id` (bigint, primary key) - ID unik riwayat COGS
-   `store_id` (uuid, foreign key) - ID store terkait
-   `product_id` (bigint, foreign key) - ID produk
-   `old_cogs` (decimal 10,2, nullable) - COGS lama
-   `new_cogs` (decimal 10,2) - COGS baru
-   `calculation_method` (enum: fifo, lifo, average) - Metode perhitungan
-   `quantity_sold` (integer, nullable) - Jumlah terjual
-   `total_cost` (decimal 12,2, nullable) - Total biaya
-   `created_at` (timestamp) - Waktu perhitungan

**Relasi:**

-   `belongsTo` stores, products

### 7. Tabel Keuangan (Financial Tables)

#### 7.1 cash_sessions

**Tujuan:** Menyimpan data sesi kasir
**Kolom:**

-   `id` (uuid, primary key) - ID unik sesi kasir
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key) - ID user kasir
-   `opening_balance` (decimal 12,2) - Saldo awal
-   `closing_balance` (decimal 12,2, nullable) - Saldo akhir
-   `expected_balance` (decimal 12,2, nullable) - Saldo yang diharapkan
-   `cash_sales` (decimal 12,2) - Penjualan tunai
-   `cash_expenses` (decimal 12,2) - Pengeluaran tunai
-   `variance` (decimal 12,2) - Selisih
-   `status` (enum: open, closed) - Status sesi
-   `opened_at` (timestamp) - Waktu buka
-   `closed_at` (timestamp, nullable) - Waktu tutup
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Index:**

-   `store_id, status` - Untuk filtering berdasarkan store dan status
-   `store_id, opened_at` - Untuk filtering berdasarkan store dan waktu
-   `user_id` - Untuk filtering berdasarkan user

**Relasi:**

-   `belongsTo` stores, users

#### 7.2 expenses

**Tujuan:** Menyimpan data pengeluaran
**Kolom:**

-   `id` (bigint, primary key) - ID unik pengeluaran
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key) - ID user yang membuat
-   `category` (string) - Kategori pengeluaran
-   `description` (string) - Deskripsi pengeluaran
-   `amount` (decimal 12,2) - Jumlah pengeluaran
-   `payment_method` (string, nullable) - Metode pembayaran
-   `receipt_number` (string, nullable) - Nomor kwitansi
-   `date` (date) - Tanggal pengeluaran
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, users

#### 7.3 discounts

**Tujuan:** Menyimpan data diskon dan promo
**Kolom:**

-   `id` (bigint, primary key) - ID unik diskon
-   `store_id` (uuid, foreign key) - ID store terkait
-   `name` (string) - Nama diskon
-   `type` (enum: percentage, fixed, buy_x_get_y) - Jenis diskon
-   `value` (decimal 10,2) - Nilai diskon
-   `min_purchase` (decimal 12,2, nullable) - Minimum pembelian
-   `max_discount` (decimal 12,2, nullable) - Maksimum diskon
-   `start_date` (date) - Tanggal mulai
-   `end_date` (date, nullable) - Tanggal berakhir
-   `is_active` (boolean) - Status aktif
-   `usage_limit` (integer, nullable) - Batas penggunaan
-   `used_count` (integer) - Jumlah digunakan
-   `applicable_to` (enum: all, categories, products) - Berlaku untuk
-   `applicable_ids` (json, nullable) - ID yang berlaku
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores

### 8. Tabel Staff (Staff Tables)

#### 8.1 staff_invitations

**Tujuan:** Menyimpan undangan staff
**Kolom:**

-   `id` (bigint, primary key) - ID unik undangan
-   `store_id` (uuid, foreign key) - ID store terkait
-   `invited_by` (bigint, foreign key) - ID user yang mengundang
-   `email` (string) - Email yang diundang
-   `role` (string) - Role yang ditawarkan
-   `token` (string, unique) - Token undangan
-   `status` (enum: pending, accepted, expired, cancelled) - Status undangan
-   `expires_at` (timestamp) - Waktu kadaluarsa
-   `accepted_at` (timestamp, nullable) - Waktu diterima
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, users (invited_by)

#### 8.2 staff_performances

**Tujuan:** Menyimpan data performance staff
**Kolom:**

-   `id` (bigint, primary key) - ID unik performance
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key) - ID user staff
-   `period_start` (date) - Periode mulai
-   `period_end` (date) - Periode akhir
-   `orders_processed` (integer) - Jumlah pesanan diproses
-   `total_sales` (decimal 12,2) - Total penjualan
-   `average_order_value` (decimal 10,2) - Rata-rata nilai pesanan
-   `customer_satisfaction` (decimal 3,2, nullable) - Kepuasan pelanggan
-   `attendance_rate` (decimal 5,2, nullable) - Tingkat kehadiran
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, users

#### 8.3 activity_logs

**Tujuan:** Menyimpan log aktivitas sistem
**Kolom:**

-   `id` (bigint, primary key) - ID unik log
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key, nullable) - ID user
-   `action` (string) - Aksi yang dilakukan
-   `model_type` (string, nullable) - Tipe model
-   `model_id` (string, nullable) - ID model
-   `old_values` (json, nullable) - Nilai lama
-   `new_values` (json, nullable) - Nilai baru
-   `ip_address` (string, nullable) - Alamat IP
-   `user_agent` (text, nullable) - User agent
-   `created_at` (timestamp) - Waktu aktivitas

**Relasi:**

-   `belongsTo` stores, users

### 9. Tabel Subscription (Subscription Tables)

#### 9.1 plans

**Tujuan:** Menyimpan data plan subscription
**Kolom:**

-   `id` (bigint, primary key) - ID unik plan
-   `name` (string) - Nama plan
-   `description` (text, nullable) - Deskripsi plan
-   `price` (decimal 10,2) - Harga plan
-   `billing_cycle` (enum: monthly, yearly) - Siklus billing
-   `features` (json, nullable) - Fitur plan
-   `limits` (json, nullable) - Batasan plan
-   `is_active` (boolean) - Status aktif
-   `sort_order` (integer) - Urutan tampil
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `hasMany` subscriptions

#### 9.2 subscriptions

**Tujuan:** Menyimpan data subscription store
**Kolom:**

-   `id` (bigint, primary key) - ID unik subscription
-   `store_id` (uuid, foreign key) - ID store
-   `plan_id` (bigint, foreign key) - ID plan
-   `status` (enum: active, inactive, cancelled, expired) - Status subscription
-   `started_at` (timestamp) - Waktu mulai
-   `ends_at` (timestamp, nullable) - Waktu berakhir
-   `cancelled_at` (timestamp, nullable) - Waktu dibatalkan
-   `cancellation_reason` (string, nullable) - Alasan pembatalan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, plans
-   `hasMany` subscription_usage, invoices

#### 9.3 subscription_usage

**Tujuan:** Menyimpan data penggunaan subscription
**Kolom:**

-   `id` (bigint, primary key) - ID unik usage
-   `subscription_id` (bigint, foreign key) - ID subscription
-   `feature` (string) - Fitur yang digunakan
-   `usage_count` (integer) - Jumlah penggunaan
-   `limit` (integer, nullable) - Batas penggunaan
-   `period_start` (date) - Periode mulai
-   `period_end` (date) - Periode akhir
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` subscriptions

#### 9.4 invoices

**Tujuan:** Menyimpan data invoice subscription
**Kolom:**

-   `id` (uuid, primary key) - ID unik invoice
-   `subscription_id` (bigint, foreign key) - ID subscription
-   `invoice_number` (string, unique) - Nomor invoice
-   `amount` (decimal 12,2) - Jumlah invoice
-   `tax_amount` (decimal 12,2) - Jumlah pajak
-   `total_amount` (decimal 12,2) - Total amount
-   `status` (enum: draft, sent, paid, overdue, cancelled) - Status invoice
-   `due_date` (date) - Tanggal jatuh tempo
-   `paid_at` (timestamp, nullable) - Waktu dibayar
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` subscriptions
-   `hasMany` payments

### 10. Tabel Sinkronisasi (Sync Tables)

#### 10.1 sync_histories

**Tujuan:** Menyimpan riwayat sinkronisasi data
**Kolom:**

-   `id` (bigint, primary key) - ID unik riwayat sync
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key, nullable) - ID user yang melakukan sync
-   `sync_type` (string) - Jenis sinkronisasi
-   `status` (enum: pending, running, completed, failed) - Status sync
-   `total_records` (integer, nullable) - Total record
-   `processed_records` (integer, nullable) - Record yang diproses
-   `failed_records` (integer, nullable) - Record yang gagal
-   `started_at` (timestamp, nullable) - Waktu mulai
-   `completed_at` (timestamp, nullable) - Waktu selesai
-   `error_message` (text, nullable) - Pesan error
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, users

#### 10.2 sync_queues

**Tujuan:** Menyimpan queue sinkronisasi data
**Kolom:**

-   `id` (bigint, primary key) - ID unik queue sync
-   `store_id` (uuid, foreign key) - ID store terkait
-   `user_id` (bigint, foreign key, nullable) - ID user yang membuat queue
-   `sync_type` (string) - Jenis sinkronisasi
-   `payload` (json) - Data yang akan disinkronkan
-   `priority` (integer) - Prioritas sync
-   `status` (enum: pending, processing, completed, failed) - Status queue
-   `attempts` (integer) - Jumlah percobaan
-   `max_attempts` (integer) - Maksimum percobaan
-   `processed_at` (timestamp, nullable) - Waktu diproses
-   `failed_at` (timestamp, nullable) - Waktu gagal
-   `error_message` (text, nullable) - Pesan error
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   `belongsTo` stores, users

### 11. Tabel Landing Page (Landing Tables)

#### 11.1 landing_subscriptions

**Tujuan:** Menyimpan subscription dari landing page
**Kolom:**

-   `id` (bigint, primary key) - ID unik subscription landing
-   `email` (string, unique) - Email subscriber
-   `name` (string, nullable) - Nama subscriber
-   `company` (string, nullable) - Nama perusahaan
-   `phone` (string, nullable) - Nomor telepon
-   `status` (enum: active, inactive, unsubscribed) - Status subscription
-   `source` (string, nullable) - Sumber subscription
-   `notes` (text, nullable) - Catatan
-   `created_at` (timestamp) - Waktu pembuatan
-   `updated_at` (timestamp) - Waktu update

**Relasi:**

-   Tidak ada relasi langsung dengan tabel lain

## Relasi Antar Tabel

### Relasi Utama

1. **stores** → **users** (hasMany)
2. **stores** → **products** (hasMany)
3. **stores** → **orders** (hasMany)
4. **stores** → **categories** (hasMany)
5. **stores** → **members** (hasMany)
6. **stores** → **tables** (hasMany)
7. **stores** → **cash_sessions** (hasMany)
8. **stores** → **expenses** (hasMany)

### Relasi Produk

1. **categories** → **products** (hasMany)
2. **products** → **order_items** (hasMany)
3. **products** → **product_variants** (hasMany)
4. **products** → **product_price_histories** (hasMany)
5. **products** → **recipes** (hasMany)
6. **products** → **inventory_movements** (hasMany)
7. **products** → **stock_levels** (hasMany)
8. **products** → **cogs_history** (hasMany)

### Relasi Pesanan

1. **orders** → **order_items** (hasMany)
2. **orders** → **payments** (hasMany)
3. **orders** → **refunds** (hasMany)
4. **orders** → **tables** (belongsTo)
5. **orders** → **members** (belongsTo)
6. **orders** → **users** (belongsTo)

### Relasi Pembayaran

1. **payments** → **refunds** (hasMany)
2. **payments** → **payment_methods** (belongsTo)
3. **payments** → **invoices** (belongsTo)

### Relasi Pelanggan

1. **members** → **orders** (hasMany)
2. **members** → **loyalty_point_transactions** (hasMany)
3. **members** → **member_tiers** (belongsTo)

### Relasi Staff

1. **users** → **orders** (hasMany)
2. **users** → **cash_sessions** (hasMany)
3. **users** → **expenses** (hasMany)
4. **users** → **activity_logs** (hasMany)
5. **users** → **staff_performances** (hasMany)

### Relasi Subscription

1. **subscriptions** → **subscription_usage** (hasMany)
2. **subscriptions** → **invoices** (hasMany)
3. **subscriptions** → **plans** (belongsTo)

## Index dan Optimasi

### Index Utama

-   Semua tabel memiliki index pada `store_id` untuk multi-tenant support
-   Tabel dengan status memiliki index pada `status`
-   Tabel dengan timestamp memiliki index pada `created_at`
-   Tabel dengan foreign key memiliki index pada kolom foreign key

### Index Komposit

-   `store_id, status` - Untuk filtering berdasarkan store dan status
-   `store_id, created_at` - Untuk filtering berdasarkan store dan waktu
-   `store_id, is_active` - Untuk filtering berdasarkan store dan status aktif

### Index Unik

-   `email` pada tabel users
-   `member_number` pada tabel members
-   `order_number` pada tabel orders
-   `table_number` pada tabel tables (per store)
-   `invoice_number` pada tabel invoices

## Kesimpulan

Skema database XpressPOS dirancang dengan struktur yang jelas dan relasi yang terdefinisi dengan baik. Setiap tabel memiliki tujuan yang spesifik dalam konteks aplikasi POS, dengan dukungan multi-tenant melalui kolom `store_id` dan optimasi melalui index yang tepat.
