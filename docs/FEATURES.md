# Daftar Fitur XpressPOS

## Overview

Dokumen ini berisi daftar lengkap fitur yang tersedia dalam sistem XpressPOS Backend, beserta modul terkait, tabel database, dan relasi antar tabel.

## 1. Authentication & User Management

### 1.1 User Authentication

**Modul:** Auth
**Role:** Semua role
**Deskripsi:** Sistem login, logout, dan manajemen session pengguna
**Tabel Database:**

-   `users` - Data pengguna
-   `personal_access_tokens` - Token autentikasi
-   `sessions` - Session management
-   `password_reset_tokens` - Reset password

**Relasi:**

-   `users` → `stores` (belongsTo)
-   `users` → `personal_access_tokens` (hasMany)
-   `users` → `sessions` (hasMany)

### 1.2 Role & Permission Management

**Modul:** Auth
**Role:** Admin, Owner
**Deskripsi:** Manajemen role dan permission untuk kontrol akses
**Tabel Database:**

-   `roles` - Data role
-   `permissions` - Data permission
-   `model_has_roles` - Relasi user-role
-   `model_has_permissions` - Relasi user-permission
-   `role_has_permissions` - Relasi role-permission

**Relasi:**

-   `users` → `roles` (belongsToMany)
-   `users` → `permissions` (belongsToMany)
-   `roles` → `permissions` (belongsToMany)

## 2. Store Management

### 2.1 Store Configuration

**Modul:** Store
**Role:** Admin, Owner
**Deskripsi:** Manajemen konfigurasi store dan multi-store support
**Tabel Database:**

-   `stores` - Data store

**Relasi:**

-   `stores` → `users` (hasMany)
-   `stores` → `products` (hasMany)
-   `stores` → `orders` (hasMany)
-   `stores` → `categories` (hasMany)

### 2.2 Store Switching

**Modul:** Store
**Role:** Admin
**Deskripsi:** Kemampuan admin untuk switch antar store
**Tabel Database:**

-   `stores` - Data store

## 3. Product Management

### 3.1 Product CRUD

**Modul:** Product
**Role:** Owner, Manager, Staff
**Deskripsi:** Manajemen produk dengan CRUD operations
**Tabel Database:**

-   `products` - Data produk
-   `categories` - Kategori produk
-   `product_price_histories` - Riwayat harga produk

**Relasi:**

-   `products` → `categories` (belongsTo)
-   `products` → `product_price_histories` (hasMany)
-   `products` → `order_items` (hasMany)
-   `products` → `inventory_movements` (hasMany)

### 3.2 Category Management

**Modul:** Product
**Role:** Owner, Manager, Staff
**Deskripsi:** Manajemen kategori produk
**Tabel Database:**

-   `categories` - Data kategori

**Relasi:**

-   `categories` → `products` (hasMany)

### 3.3 Product Options

**Modul:** Product
**Role:** Owner, Manager, Staff
**Deskripsi:** Manajemen variant produk (size, color, dll)
**Tabel Database:**

-   `product_variants` - Variant produk

**Relasi:**

-   `product_variants` → `products` (belongsTo)

### 3.4 Recipe Management

**Modul:** Product
**Role:** Owner, Manager
**Deskripsi:** Manajemen resep produk untuk Bill of Materials (BOM)
**Tabel Database:**

-   `recipes` - Data resep
-   `recipe_items` - Item dalam resep

**Relasi:**

-   `recipes` → `products` (belongsTo)
-   `recipes` → `recipe_items` (hasMany)
-   `recipe_items` → `products` (belongsTo)

## 4. Order Management

### 4.1 Order Processing

**Modul:** Order
**Role:** Owner, Manager, Cashier
**Deskripsi:** Manajemen pesanan dari draft hingga completed
**Tabel Database:**

-   `orders` - Data pesanan
-   `order_items` - Item pesanan

**Relasi:**

-   `orders` → `stores` (belongsTo)
-   `orders` → `users` (belongsTo)
-   `orders` → `members` (belongsTo)
-   `orders` → `tables` (belongsTo)
-   `orders` → `order_items` (hasMany)
-   `orders` → `payments` (hasMany)

### 4.2 Table Management

**Modul:** Order
**Role:** Owner, Manager, Cashier
**Deskripsi:** Manajemen meja dan status occupancy
**Tabel Database:**

-   `tables` - Data meja
-   `table_occupancy_histories` - Riwayat occupancy meja

**Relasi:**

-   `tables` → `stores` (belongsTo)
-   `tables` → `orders` (hasMany)
-   `tables` → `table_occupancy_histories` (hasMany)

## 5. Payment Management

### 5.1 Payment Processing

**Modul:** Payment
**Role:** Owner, Manager, Cashier
**Deskripsi:** Proses pembayaran dengan berbagai metode
**Tabel Database:**

-   `payments` - Data pembayaran
-   `payment_methods` - Metode pembayaran
-   `refunds` - Data refund

**Relasi:**

-   `payments` → `orders` (belongsTo)
-   `payments` → `payment_methods` (belongsTo)
-   `payments` → `refunds` (hasMany)

### 5.2 Payment Method Management

**Modul:** Payment
**Role:** Owner, Manager
**Deskripsi:** Manajemen metode pembayaran yang tersedia
**Tabel Database:**

-   `payment_methods` - Data metode pembayaran

**Relasi:**

-   `payment_methods` → `payments` (hasMany)

### 5.3 Refund Management

**Modul:** Payment
**Role:** Owner, Manager
**Deskripsi:** Manajemen refund untuk pembayaran
**Tabel Database:**

-   `refunds` - Data refund

**Relasi:**

-   `refunds` → `payments` (belongsTo)

## 6. Inventory Management

### 6.1 Stock Level Management

**Modul:** Inventory
**Role:** Owner, Manager
**Deskripsi:** Monitoring dan manajemen level stok
**Tabel Database:**

-   `stock_levels` - Level stok produk
-   `inventory_movements` - Pergerakan inventori
-   `cogs_history` - Riwayat COGS

**Relasi:**

-   `stock_levels` → `products` (belongsTo)
-   `inventory_movements` → `products` (belongsTo)
-   `cogs_history` → `products` (belongsTo)

### 6.2 Inventory Adjustments

**Modul:** Inventory
**Role:** Owner, Manager
**Deskripsi:** Penyesuaian stok dan pergerakan inventori
**Tabel Database:**

-   `inventory_movements` - Pergerakan inventori

**Relasi:**

-   `inventory_movements` → `products` (belongsTo)
-   `inventory_movements` → `users` (belongsTo)

## 7. Customer Management

### 7.1 Member Management

**Modul:** Customer
**Role:** Owner, Manager, Cashier
**Deskripsi:** Manajemen member dan customer data
**Tabel Database:**

-   `members` - Data member
-   `member_tiers` - Tier member
-   `loyalty_point_transactions` - Transaksi poin loyalitas

**Relasi:**

-   `members` → `stores` (belongsTo)
-   `members` → `member_tiers` (belongsTo)
-   `members` → `orders` (hasMany)
-   `members` → `loyalty_point_transactions` (hasMany)

### 7.2 Loyalty Program

**Modul:** Customer
**Role:** Owner, Manager
**Deskripsi:** Program loyalitas dengan sistem poin
**Tabel Database:**

-   `loyalty_point_transactions` - Transaksi poin loyalitas
-   `member_tiers` - Tier member

**Relasi:**

-   `loyalty_point_transactions` → `members` (belongsTo)
-   `member_tiers` → `members` (hasMany)

## 8. Staff Management

### 8.1 Staff CRUD

**Modul:** Staff
**Role:** Owner, Manager
**Deskripsi:** Manajemen staff dengan CRUD operations
**Tabel Database:**

-   `users` - Data staff (dengan role staff)
-   `staff_invitations` - Undangan staff
-   `staff_performances` - Performance staff

**Relasi:**

-   `staff_invitations` → `users` (belongsTo)
-   `staff_performances` → `users` (belongsTo)

### 8.2 Staff Invitation System

**Modul:** Staff
**Role:** Owner, Manager
**Deskripsi:** Sistem undangan untuk staff baru
**Tabel Database:**

-   `staff_invitations` - Data undangan staff

**Relasi:**

-   `staff_invitations` → `users` (belongsTo)

### 8.3 Performance Tracking

**Modul:** Staff
**Role:** Owner, Manager
**Deskripsi:** Tracking performance dan aktivitas staff
**Tabel Database:**

-   `staff_performances` - Data performance staff
-   `activity_logs` - Log aktivitas

**Relasi:**

-   `staff_performances` → `users` (belongsTo)
-   `activity_logs` → `users` (belongsTo)

## 9. Financial Management

### 9.1 Cash Session Management

**Modul:** Financial
**Role:** Owner, Manager, Cashier
**Deskripsi:** Manajemen sesi kasir dan perhitungan kas
**Tabel Database:**

-   `cash_sessions` - Data sesi kasir

**Relasi:**

-   `cash_sessions` → `users` (belongsTo)
-   `cash_sessions` → `stores` (belongsTo)

### 9.2 Expense Management

**Modul:** Financial
**Role:** Owner, Manager
**Deskripsi:** Manajemen pengeluaran dan biaya operasional
**Tabel Database:**

-   `expenses` - Data pengeluaran

**Relasi:**

-   `expenses` → `stores` (belongsTo)
-   `expenses` → `users` (belongsTo)

### 9.3 Discount Management

**Modul:** Financial
**Role:** Owner, Manager
**Deskripsi:** Manajemen diskon dan promo
**Tabel Database:**

-   `discounts` - Data diskon

**Relasi:**

-   `discounts` → `stores` (belongsTo)

## 10. Reporting & Analytics

### 10.1 Sales Reports

**Modul:** Report
**Role:** Owner, Manager
**Deskripsi:** Laporan penjualan dan analisis trend
**Tabel Database:**

-   `orders` - Data pesanan
-   `order_items` - Item pesanan
-   `payments` - Data pembayaran

**Relasi:**

-   Menggunakan relasi dari tabel orders, order_items, dan payments

### 10.2 Inventory Reports

**Modul:** Report
**Role:** Owner, Manager
**Deskripsi:** Laporan inventori dan analisis stok
**Tabel Database:**

-   `stock_levels` - Level stok
-   `inventory_movements` - Pergerakan inventori
-   `products` - Data produk

**Relasi:**

-   Menggunakan relasi dari tabel inventory dan products

### 10.3 Financial Reports

**Modul:** Report
**Role:** Owner, Manager
**Deskripsi:** Laporan keuangan dan cash flow
**Tabel Database:**

-   `cash_sessions` - Sesi kasir
-   `expenses` - Pengeluaran
-   `payments` - Pembayaran

**Relasi:**

-   Menggunakan relasi dari tabel financial

### 10.4 Customer Analytics

**Modul:** Report
**Role:** Owner, Manager
**Deskripsi:** Analisis customer behavior dan loyalty
**Tabel Database:**

-   `members` - Data member
-   `orders` - Data pesanan
-   `loyalty_point_transactions` - Transaksi poin

**Relasi:**

-   Menggunakan relasi dari tabel customer dan orders

## 11. Subscription Management

### 11.1 Plan Management

**Modul:** Subscription
**Role:** Owner
**Deskripsi:** Manajemen plan subscription
**Tabel Database:**

-   `plans` - Data plan
-   `subscriptions` - Data subscription
-   `subscription_usage` - Usage subscription

**Relasi:**

-   `subscriptions` → `plans` (belongsTo)
-   `subscriptions` → `subscription_usage` (hasMany)

### 11.2 Subscription Lifecycle

**Modul:** Subscription
**Role:** Owner
**Deskripsi:** Manajemen lifecycle subscription
**Tabel Database:**

-   `subscriptions` - Data subscription
-   `invoices` - Data invoice

**Relasi:**

-   `subscriptions` → `invoices` (hasMany)

## 12. System Integration

### 12.1 Sync Management

**Modul:** Sync
**Role:** Owner, Manager
**Deskripsi:** Manajemen sinkronisasi data
**Tabel Database:**

-   `sync_histories` - Riwayat sync
-   `sync_queues` - Queue sync

**Relasi:**

-   `sync_histories` → `users` (belongsTo)
-   `sync_queues` → `users` (belongsTo)

### 12.2 Webhook Integration

**Modul:** Integration
**Role:** System
**Deskripsi:** Integrasi webhook untuk payment gateway
**Tabel Database:**

-   Tidak menggunakan tabel khusus

## 13. Notification System

### 13.1 Low Stock Alerts

**Modul:** Notification
**Role:** Owner, Manager
**Deskripsi:** Alert untuk stok rendah
**Tabel Database:**

-   `stock_levels` - Level stok
-   `products` - Data produk

**Relasi:**

-   Menggunakan relasi dari tabel inventory

### 13.2 Activity Logging

**Modul:** Notification
**Role:** Owner, Manager
**Deskripsi:** Logging aktivitas sistem
**Tabel Database:**

-   `activity_logs` - Log aktivitas

**Relasi:**

-   `activity_logs` → `users` (belongsTo)

## 14. Data Export & Import

### 14.1 Report Export

**Modul:** Export
**Role:** Owner, Manager
**Deskripsi:** Export laporan ke berbagai format
**Tabel Database:**

-   Menggunakan semua tabel yang relevan

### 14.2 Data Backup

**Modul:** Export
**Role:** Owner
**Deskripsi:** Backup data sistem
**Tabel Database:**

-   Menggunakan semua tabel sistem

## Kesimpulan

Sistem XpressPOS Backend menyediakan fitur-fitur yang komprehensif untuk mengelola operasional bisnis, mulai dari manajemen produk, pesanan, pembayaran, hingga laporan dan analisis. Setiap fitur dirancang dengan relasi database yang jelas dan kontrol akses berdasarkan role pengguna.
