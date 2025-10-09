# Scope Proyek XpressPOS

## Overview

Dokumen ini menjelaskan scope dan ruang lingkup proyek XpressPOS Backend, termasuk modul-modul yang tersedia, role pengguna, dan batasan-batasan sistem.

## Role Pengguna

### 1. Admin Sistem (admin_sistem)

**Tanggung Jawab:**

-   Manajemen multi-store
-   Switch antar store untuk monitoring
-   Akses ke semua data sistem
-   Konfigurasi sistem global

**Fitur yang Dapat Diakses:**

-   Store switching dan management
-   Global reports dan analytics
-   System configuration
-   User management across stores

**Batasan:**

-   Tidak dapat melakukan transaksi langsung
-   Tidak dapat mengubah data operasional store

### 2. Store Owner (owner)

**Tanggung Jawab:**

-   Manajemen lengkap store yang dimiliki
-   Konfigurasi store settings
-   Manajemen staff dan permissions
-   Akses ke semua laporan dan analytics

**Fitur yang Dapat Diakses:**

-   Semua fitur operasional store
-   Staff management dan role assignment
-   Store configuration
-   Financial reports dan analytics
-   Subscription management

**Batasan:**

-   Hanya dapat mengakses store yang dimiliki
-   Tidak dapat mengakses data store lain

### 3. Store Manager (manager)

**Tanggung Jawab:**

-   Manajemen operasional harian
-   Supervisi staff
-   Monitoring performance
-   Manajemen inventori

**Fitur yang Dapat Diakses:**

-   Order management
-   Inventory management
-   Staff performance monitoring
-   Operational reports
-   Customer management

**Batasan:**

-   Tidak dapat mengubah store settings
-   Tidak dapat mengelola subscription
-   Akses terbatas pada financial reports

### 4. Cashier (cashier)

**Tanggung Jawab:**

-   Proses transaksi harian
-   Manajemen pesanan
-   Pelayanan pelanggan
-   Operasi kasir

**Fitur yang Dapat Diakses:**

-   Order creation dan management
-   Payment processing
-   Table management
-   Basic customer service
-   Daily sales reports

**Batasan:**

-   Tidak dapat mengakses financial reports detail
-   Tidak dapat mengelola staff
-   Tidak dapat mengubah harga produk
-   Tidak dapat mengakses inventory management

### 5. Staff (staff)

**Tanggung Jawab:**

-   Operasi dasar sistem
-   Input data produk
-   Pelayanan pelanggan dasar

**Fitur yang Dapat Diakses:**

-   View products dan categories
-   Basic order operations
-   Customer information view
-   Basic reports

**Batasan:**

-   Akses sangat terbatas
-   Tidak dapat melakukan transaksi
-   Tidak dapat mengakses laporan

## Modul Sistem

### 1. Authentication & Authorization

**Scope:**

-   User registration dan login
-   Password management
-   Role-based access control
-   Session management

**Fitur:**

-   Multi-role authentication
-   Password reset functionality
-   Token-based API authentication
-   Permission-based access control

**Batasan:**

-   Tidak mendukung SSO (Single Sign-On)
-   Tidak mendukung 2FA (Two-Factor Authentication)

### 2. Store Management

**Scope:**

-   Multi-store architecture
-   Store configuration
-   Store switching untuk admin

**Fitur:**

-   Store creation dan management
-   Store settings configuration
-   Multi-tenant data isolation
-   Store performance monitoring

**Batasan:**

-   Maksimal 1 store per owner (dalam scope saat ini)
-   Tidak mendukung store hierarchy

### 3. Product Management

**Scope:**

-   Manajemen produk dan kategori
-   Product options dan variants
-   Price management
-   Product recipes

**Fitur:**

-   CRUD operations untuk products
-   Category management
-   Product options (size, color, dll)
-   Price history tracking
-   Recipe management untuk BOM

**Batasan:**

-   Tidak mendukung product bundling
-   Tidak mendukung dynamic pricing
-   Tidak mendukung product variants yang kompleks

### 4. Order Management

**Scope:**

-   Manajemen pesanan dari draft hingga completed
-   Table management
-   Order item management
-   Order status tracking

**Fitur:**

-   Order lifecycle management
-   Table assignment dan management
-   Order item CRUD
-   Order status updates
-   Order cancellation

**Batasan:**

-   Tidak mendukung order splitting
-   Tidak mendukung order merging
-   Tidak mendukung advance booking

### 5. Payment Management

**Scope:**

-   Multiple payment methods
-   Payment processing
-   Refund management
-   Payment method configuration

**Fitur:**

-   Cash, card, dan digital payment
-   Midtrans integration
-   Refund processing
-   Payment method management
-   Payment history tracking

**Batasan:**

-   Tidak mendukung payment splitting
-   Tidak mendukung installment payments
-   Tidak mendukung cryptocurrency

### 6. Inventory Management

**Scope:**

-   Stock level monitoring
-   Inventory movements tracking
-   COGS calculation
-   Low stock alerts

**Fitur:**

-   Real-time stock tracking
-   Inventory adjustments
-   Movement history
-   COGS calculation
-   Stock level alerts
-   Inventory valuation

**Batasan:**

-   Tidak mendukung batch tracking
-   Tidak mendukung serial number tracking
-   Tidak mendukung expiration date management

### 7. Customer Management

**Scope:**

-   Member registration dan management
-   Loyalty program
-   Customer analytics
-   Member tier management

**Fitur:**

-   Member CRUD operations
-   Loyalty points system
-   Member tier management
-   Customer behavior analytics
-   Member performance tracking

**Batasan:**

-   Tidak mendukung customer segmentation
-   Tidak mendukung advanced loyalty rules
-   Tidak mendukung customer communication

### 8. Staff Management

**Scope:**

-   Staff registration dan management
-   Role assignment
-   Performance tracking
-   Invitation system

**Fitur:**

-   Staff CRUD operations
-   Role dan permission management
-   Performance monitoring
-   Staff invitation system
-   Activity logging

**Batasan:**

-   Tidak mendukung shift management
-   Tidak mendukung payroll integration
-   Tidak mendukung advanced performance metrics

### 9. Reporting & Analytics

**Scope:**

-   Sales reports
-   Inventory reports
-   Financial reports
-   Performance analytics

**Fitur:**

-   Dashboard analytics
-   Sales trend analysis
-   Inventory reports
-   Cash flow reports
-   Product performance analysis
-   Customer analytics
-   Export functionality

**Batasan:**

-   Tidak mendukung custom report builder
-   Tidak mendukung real-time dashboard
-   Tidak mendukung advanced data visualization

### 10. Subscription Management

**Scope:**

-   Plan management
-   Subscription lifecycle
-   Usage tracking
-   Payment processing

**Fitur:**

-   Plan selection dan management
-   Subscription upgrade/downgrade
-   Usage monitoring
-   Invoice management
-   Payment processing

**Batasan:**

-   Tidak mendukung custom pricing
-   Tidak mendukung usage-based billing
-   Tidak mendukung subscription sharing

## Integrasi Eksternal

### 1. Payment Gateway

**Provider:** Midtrans
**Scope:** Payment processing untuk subscription dan orders
**Batasan:** Hanya mendukung Midtrans, tidak mendukung multiple payment gateways

### 2. Email Service

**Provider:** Laravel Mail
**Scope:** Notification dan communication
**Batasan:** Menggunakan default Laravel mail configuration

### 3. File Storage

**Provider:** Laravel Storage
**Scope:** File upload dan management
**Batasan:** Menggunakan local storage, tidak mendukung cloud storage

## Batasan Teknis

### 1. Database

-   **Primary:** SQLite (development)
-   **Production:** MySQL/PostgreSQL
-   **Batasan:** Tidak mendukung database clustering

### 2. Caching

-   **Provider:** Laravel Cache
-   **Batasan:** Tidak mendukung distributed caching

### 3. Queue System

-   **Provider:** Laravel Queue
-   **Batasan:** Tidak mendukung multiple queue drivers

### 4. API

-   **Version:** v1
-   **Format:** JSON
-   **Batasan:** Tidak mendukung GraphQL, hanya REST API

## Roadmap dan Pengembangan Masa Depan

### Fitur yang Direncanakan

1. **Advanced Analytics** - Real-time dashboard dan custom reports
2. **Mobile App Integration** - Native mobile applications
3. **Multi-language Support** - Internationalization
4. **Advanced Inventory** - Batch tracking dan expiration management
5. **Customer Communication** - SMS dan email marketing
6. **Advanced Payment** - Multiple gateways dan payment splitting

### Batasan yang Akan Diatasi

1. **Scalability** - Database clustering dan load balancing
2. **Performance** - Caching optimization dan query optimization
3. **Security** - 2FA dan advanced security features
4. **Integration** - API untuk third-party integrations

## Kesimpulan

XpressPOS Backend adalah sistem POS yang komprehensif dengan scope yang jelas untuk bisnis kecil hingga menengah. Sistem ini dirancang dengan arsitektur yang scalable dan modular, memungkinkan pengembangan lebih lanjut sesuai kebutuhan bisnis yang berkembang.
