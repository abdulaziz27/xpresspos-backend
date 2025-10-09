# Struktur Modul dan Controller XpressPOS

## Overview

Dokumen ini menjelaskan struktur folder `app/` dan `routes/` untuk memetakan modul dan controller yang ada dalam sistem XpressPOS Backend, beserta fungsi dan tanggung jawab masing-masing.

## Struktur Folder App/

### 1. Actions/

**Tujuan:** Menyimpan action classes untuk Fortify authentication
**Isi:**

-   `Fortify/` - Folder khusus untuk Fortify actions
    -   `CreateNewUser.php` - Action untuk membuat user baru
    -   `ResetUserPassword.php` - Action untuk reset password
    -   `UpdateUserProfileInformation.php` - Action untuk update profil
    -   `UpdateUserPassword.php` - Action untuk update password
    -   `VerifyEmail.php` - Action untuk verifikasi email

**Fungsi:** Mengelola proses authentication dan user management melalui Fortify

### 2. Console/

**Tujuan:** Menyimpan command-line interface dan scheduled tasks
**Isi:**

-   `Commands/` - Folder untuk Artisan commands
    -   `GenerateMonthlyReportCommand.php` - Command untuk generate laporan bulanan
    -   `ProcessFailedPaymentsCommand.php` - Command untuk proses pembayaran gagal
    -   `SyncInventoryCommand.php` - Command untuk sinkronisasi inventori
    -   `CleanupExpiredSessionsCommand.php` - Command untuk cleanup session expired
    -   `BackupDatabaseCommand.php` - Command untuk backup database
    -   `SendLowStockAlertsCommand.php` - Command untuk kirim alert stok rendah
    -   `CalculateCogsCommand.php` - Command untuk hitung COGS
    -   `GenerateCashFlowReportCommand.php` - Command untuk generate laporan cash flow
-   `Kernel.php` - Kernel untuk console commands

**Fungsi:** Mengelola scheduled tasks dan maintenance operations

### 3. Enums/

**Tujuan:** Menyimpan enumeration classes
**Isi:**

-   `NavigationGroup.php` - Enum untuk grup navigasi

**Fungsi:** Mendefinisikan konstanta dan enumeration yang digunakan dalam aplikasi

### 4. Exceptions/

**Tujuan:** Menyimpan custom exception classes
**Isi:**

-   `Handler.php` - Exception handler utama

**Fungsi:** Menangani exception dan error handling dalam aplikasi

### 5. Exports/

**Tujuan:** Menyimpan export classes untuk data export
**Isi:**

-   `ReportExport.php` - Class untuk export laporan

**Fungsi:** Mengelola export data ke berbagai format (Excel, PDF, CSV)

### 6. Filament/

**Tujuan:** Menyimpan konfigurasi Filament admin panel
**Isi:**

-   `Admin/` - Folder untuk admin panel
-   `Owner/` - Folder untuk owner panel
-   `AdminPanelProvider.php` - Provider untuk admin panel
-   `OwnerPanelProvider.php` - Provider untuk owner panel

**Fungsi:** Mengelola admin panel menggunakan Filament

### 7. Http/

**Tujuan:** Menyimpan HTTP-related classes (Controllers, Middleware, Requests, Resources)

#### 7.1 Controllers/

**Tujuan:** Menyimpan controller classes untuk menangani HTTP requests
**Isi:**

-   `Api/V1/` - Folder untuk API controllers versi 1
    -   `AuthController.php` - Controller untuk authentication
    -   `CashFlowReportController.php` - Controller untuk laporan cash flow
    -   `CashSessionController.php` - Controller untuk sesi kasir
    -   `CategoryController.php` - Controller untuk kategori produk
    -   `ExpenseController.php` - Controller untuk pengeluaran
    -   `InventoryController.php` - Controller untuk inventori
    -   `InventoryReportController.php` - Controller untuk laporan inventori
    -   `InvitationController.php` - Controller untuk undangan staff
    -   `MemberController.php` - Controller untuk member/pelanggan
    -   `MidtransWebhookController.php` - Controller untuk webhook Midtrans
    -   `OrderController.php` - Controller untuk pesanan
    -   `PaymentController.php` - Controller untuk pembayaran
    -   `PaymentMethodController.php` - Controller untuk metode pembayaran
    -   `PlanController.php` - Controller untuk plan subscription
    -   `ProductController.php` - Controller untuk produk
    -   `ProductOptionController.php` - Controller untuk opsi produk
    -   `RecipeController.php` - Controller untuk resep produk
    -   `ReportController.php` - Controller untuk laporan umum
    -   `StaffController.php` - Controller untuk staff management
    -   `StoreSwitchController.php` - Controller untuk switch store
    -   `SubscriptionController.php` - Controller untuk subscription
    -   `SubscriptionPaymentController.php` - Controller untuk pembayaran subscription
    -   `SyncController.php` - Controller untuk sinkronisasi data
    -   `TableController.php` - Controller untuk meja
-   `Admin/` - Folder untuk admin controllers
-   `Owner/` - Folder untuk owner controllers

**Fungsi:** Menangani HTTP requests dan mengembalikan responses

#### 7.2 Middleware/

**Tujuan:** Menyimpan middleware classes untuk request processing
**Isi:**

-   `Authenticate.php` - Middleware untuk authentication
-   `EncryptCookies.php` - Middleware untuk enkripsi cookies
-   `PreventRequestsDuringMaintenance.php` - Middleware untuk maintenance mode
-   `RedirectIfAuthenticated.php` - Middleware untuk redirect jika sudah login
-   `TrimStrings.php` - Middleware untuk trim strings
-   `TrustProxies.php` - Middleware untuk trust proxies
-   `VerifyCsrfToken.php` - Middleware untuk verifikasi CSRF token
-   `RoleMiddleware.php` - Middleware untuk role-based access
-   `PermissionMiddleware.php` - Middleware untuk permission-based access
-   `StoreScopeMiddleware.php` - Middleware untuk store scope
-   `ApiVersionMiddleware.php` - Middleware untuk API versioning
-   `RateLimitMiddleware.php` - Middleware untuk rate limiting
-   `LogActivityMiddleware.php` - Middleware untuk logging aktivitas
-   `ValidateApiKeyMiddleware.php` - Middleware untuk validasi API key
-   `CorsMiddleware.php` - Middleware untuk CORS
-   `MaintenanceModeMiddleware.php` - Middleware untuk maintenance mode

**Fungsi:** Memproses HTTP requests sebelum mencapai controller

#### 7.3 Requests/

**Tujuan:** Menyimpan form request classes untuk validasi
**Isi:**

-   `AddOrderItemRequest.php` - Request untuk tambah item pesanan
-   `StoreExpenseRequest.php` - Request untuk simpan pengeluaran
-   `StoreMemberRequest.php` - Request untuk simpan member
-   `StoreOrderRequest.php` - Request untuk simpan pesanan
-   `StoreRefundRequest.php` - Request untuk simpan refund
-   `StoreTableRequest.php` - Request untuk simpan meja
-   `UpdateExpenseRequest.php` - Request untuk update pengeluaran
-   `UpdateMemberRequest.php` - Request untuk update member
-   `UpdateOrderRequest.php` - Request untuk update pesanan
-   `UpdateTableRequest.php` - Request untuk update meja
-   `LoginRequest.php` - Request untuk login
-   `RegisterRequest.php` - Request untuk registrasi
-   `ForgotPasswordRequest.php` - Request untuk lupa password
-   `ResetPasswordRequest.php` - Request untuk reset password
-   `ChangePasswordRequest.php` - Request untuk ganti password
-   `StoreProductRequest.php` - Request untuk simpan produk
-   `UpdateProductRequest.php` - Request untuk update produk
-   `StoreCategoryRequest.php` - Request untuk simpan kategori
-   `UpdateCategoryRequest.php` - Request untuk update kategori
-   `StorePaymentRequest.php` - Request untuk simpan pembayaran
-   `StoreInventoryMovementRequest.php` - Request untuk simpan pergerakan inventori

**Fungsi:** Validasi input data sebelum diproses oleh controller

#### 7.4 Resources/

**Tujuan:** Menyimpan API resource classes untuk data transformation
**Isi:**

-   `UserResource.php` - Resource untuk data user
-   `ProductResource.php` - Resource untuk data produk
-   `OrderResource.php` - Resource untuk data pesanan
-   `MemberResource.php` - Resource untuk data member
-   `PaymentResource.php` - Resource untuk data pembayaran
-   `InventoryResource.php` - Resource untuk data inventori
-   `ReportResource.php` - Resource untuk data laporan
-   `StoreResource.php` - Resource untuk data store

**Fungsi:** Mentransformasi data model menjadi format API response

### 8. Jobs/

**Tujuan:** Menyimpan job classes untuk background processing
**Isi:**

-   `ExportReportJob.php` - Job untuk export laporan
-   `GenerateMonthlyReportJob.php` - Job untuk generate laporan bulanan
-   `ProcessFailedPayments.php` - Job untuk proses pembayaran gagal
-   `SendLowStockAlertJob.php` - Job untuk kirim alert stok rendah
-   `CalculateCogsJob.php` - Job untuk hitung COGS
-   `SyncInventoryJob.php` - Job untuk sinkronisasi inventori
-   `CleanupExpiredSessionsJob.php` - Job untuk cleanup session expired
-   `BackupDatabaseJob.php` - Job untuk backup database
-   `SendEmailNotificationJob.php` - Job untuk kirim email notification
-   `ProcessWebhookJob.php` - Job untuk proses webhook

**Fungsi:** Menangani operasi background yang memakan waktu lama

### 9. Mail/

**Tujuan:** Menyimpan mail classes untuk email notifications
**Isi:**

-   `MonthlyReportFailed.php` - Email untuk laporan bulanan gagal
-   `MonthlyReportReady.php` - Email untuk laporan bulanan siap
-   `ReportExportFailed.php` - Email untuk export laporan gagal
-   `LowStockAlert.php` - Email untuk alert stok rendah
-   `StaffInvitation.php` - Email untuk undangan staff
-   `PasswordReset.php` - Email untuk reset password
-   `WelcomeEmail.php` - Email untuk welcome user baru

**Fungsi:** Mengelola template dan pengiriman email

### 10. Models/

**Tujuan:** Menyimpan Eloquent model classes
**Isi:**

-   `User.php` - Model untuk user
-   `Store.php` - Model untuk store
-   `Product.php` - Model untuk produk
-   `Category.php` - Model untuk kategori
-   `Order.php` - Model untuk pesanan
-   `OrderItem.php` - Model untuk item pesanan
-   `Member.php` - Model untuk member
-   `Payment.php` - Model untuk pembayaran
-   `PaymentMethod.php` - Model untuk metode pembayaran
-   `Table.php` - Model untuk meja
-   `CashSession.php` - Model untuk sesi kasir
-   `Expense.php` - Model untuk pengeluaran
-   `InventoryMovement.php` - Model untuk pergerakan inventori
-   `StockLevel.php` - Model untuk level stok
-   `CogsHistory.php` - Model untuk riwayat COGS
-   `Recipe.php` - Model untuk resep
-   `RecipeItem.php` - Model untuk item resep
-   `MemberTier.php` - Model untuk tier member
-   `LoyaltyPointTransaction.php` - Model untuk transaksi poin loyalitas
-   `StaffInvitation.php` - Model untuk undangan staff
-   `StaffPerformance.php` - Model untuk performance staff
-   `ActivityLog.php` - Model untuk log aktivitas
-   `Discount.php` - Model untuk diskon
-   `Refund.php` - Model untuk refund
-   `ProductOption.php` - Model untuk opsi produk
-   `ProductPriceHistory.php` - Model untuk riwayat harga produk
-   `TableOccupancyHistory.php` - Model untuk riwayat occupancy meja
-   `SyncHistory.php` - Model untuk riwayat sinkronisasi
-   `SyncQueue.php` - Model untuk queue sinkronisasi
-   `Plan.php` - Model untuk plan subscription
-   `Subscription.php` - Model untuk subscription
-   `SubscriptionUsage.php` - Model untuk usage subscription
-   `Invoice.php` - Model untuk invoice
-   `LandingSubscription.php` - Model untuk subscription landing page
-   `Concerns/` - Folder untuk model concerns
    -   `BelongsToStore.php` - Concern untuk relasi store
-   `Scopes/` - Folder untuk model scopes
    -   `StoreScope.php` - Scope untuk filtering berdasarkan store

**Fungsi:** Mengelola data dan relasi database menggunakan Eloquent ORM

### 11. Notifications/

**Tujuan:** Menyimpan notification classes
**Isi:**

-   `LowStockAlert.php` - Notification untuk alert stok rendah
-   `OrderCompleted.php` - Notification untuk pesanan selesai
-   `PaymentReceived.php` - Notification untuk pembayaran diterima
-   `StaffInvitation.php` - Notification untuk undangan staff
-   `MonthlyReportReady.php` - Notification untuk laporan bulanan siap

**Fungsi:** Mengelola notifikasi real-time dan push notifications

### 12. Observers/

**Tujuan:** Menyimpan observer classes untuk model events
**Isi:**

-   `OrderObserver.php` - Observer untuk model Order
-   `ProductObserver.php` - Observer untuk model Product
-   `InventoryObserver.php` - Observer untuk model Inventory
-   `PaymentObserver.php` - Observer untuk model Payment
-   `MemberObserver.php` - Observer untuk model Member

**Fungsi:** Menangani event yang terjadi pada model (created, updated, deleted)

### 13. Policies/

**Tujuan:** Menyimpan policy classes untuk authorization
**Isi:**

-   `CategoryPolicy.php` - Policy untuk kategori
-   `MemberPolicy.php` - Policy untuk member
-   `OrderPolicy.php` - Policy untuk pesanan
-   `ProductPolicy.php` - Policy untuk produk
-   `PaymentPolicy.php` - Policy untuk pembayaran
-   `InventoryPolicy.php` - Policy untuk inventori
-   `ReportPolicy.php` - Policy untuk laporan
-   `StaffPolicy.php` - Policy untuk staff
-   `StorePolicy.php` - Policy untuk store

**Fungsi:** Mengelola authorization dan kontrol akses berdasarkan role dan permission

### 14. Providers/

**Tujuan:** Menyimpan service provider classes
**Isi:**

-   `AppServiceProvider.php` - Provider utama aplikasi
-   `AuthServiceProvider.php` - Provider untuk authentication
-   `BroadcastServiceProvider.php` - Provider untuk broadcasting
-   `EventServiceProvider.php` - Provider untuk events
-   `RouteServiceProvider.php` - Provider untuk routes
-   `Filament/` - Folder untuk Filament providers
    -   `AdminPanelProvider.php` - Provider untuk admin panel
    -   `OwnerPanelProvider.php` - Provider untuk owner panel

**Fungsi:** Mengelola service container dan dependency injection

### 15. Services/

**Tujuan:** Menyimpan service classes untuk business logic
**Isi:**

-   `CogsService.php` - Service untuk perhitungan COGS
-   `InventoryService.php` - Service untuk manajemen inventori
-   `InvoiceService.php` - Service untuk manajemen invoice
-   `PaymentService.php` - Service untuk proses pembayaran
-   `OrderService.php` - Service untuk manajemen pesanan
-   `MemberService.php` - Service untuk manajemen member
-   `ReportService.php` - Service untuk generate laporan
-   `SyncService.php` - Service untuk sinkronisasi data
-   `EmailService.php` - Service untuk pengiriman email
-   `NotificationService.php` - Service untuk notifikasi
-   `FileService.php` - Service untuk manajemen file
-   `ExportService.php` - Service untuk export data
-   `ImportService.php` - Service untuk import data
-   `AnalyticsService.php` - Service untuk analisis data
-   `LoyaltyService.php` - Service untuk program loyalitas
-   `Reporting/` - Folder untuk reporting services
    -   `SalesReportService.php` - Service untuk laporan penjualan
    -   `InventoryReportService.php` - Service untuk laporan inventori
    -   `FinancialReportService.php` - Service untuk laporan keuangan
    -   `CustomerReportService.php` - Service untuk laporan pelanggan
-   `Sync/` - Folder untuk sync services
    -   `DataSyncService.php` - Service untuk sinkronisasi data
    -   `InventorySyncService.php` - Service untuk sinkronisasi inventori
    -   `OrderSyncService.php` - Service untuk sinkronisasi pesanan
    -   `PaymentSyncService.php` - Service untuk sinkronisasi pembayaran
    -   `MemberSyncService.php` - Service untuk sinkronisasi member
    -   `ProductSyncService.php` - Service untuk sinkronisasi produk

**Fungsi:** Mengelola business logic dan operasi kompleks

## Struktur Routes/

### 1. api.php

**Tujuan:** Mendefinisikan API routes untuk aplikasi
**Isi:**

-   Routes untuk API v1 dengan prefix `/api/v1`
-   Authentication routes (`/auth/*`)
-   Protected routes dengan middleware `auth:sanctum`
-   Public routes untuk plans dan invitations
-   Webhook routes untuk Midtrans
-   Fallback route untuk endpoint tidak ditemukan

**Fungsi:** Mengelola routing untuk API endpoints

### 2. admin.php

**Tujuan:** Mendefinisikan routes untuk admin panel
**Isi:**

-   Routes untuk Filament admin panel
-   Admin-specific routes dengan middleware role
-   Dashboard routes untuk admin

**Fungsi:** Mengelola routing untuk admin panel

### 3. owner.php

**Tujuan:** Mendefinisikan routes untuk owner panel
**Isi:**

-   Routes untuk Filament owner panel
-   Owner-specific routes dengan middleware role
-   Dashboard routes untuk owner

**Fungsi:** Mengelola routing untuk owner panel

### 4. web.php

**Tujuan:** Mendefinisikan web routes untuk aplikasi
**Isi:**

-   Routes untuk landing page
-   Routes untuk company page
-   Routes untuk welcome page
-   Routes untuk authentication (login, register, dll)

**Fungsi:** Mengelola routing untuk web interface

### 5. console.php

**Tujuan:** Mendefinisikan console routes untuk Artisan commands
**Isi:**

-   Routes untuk custom Artisan commands
-   Scheduled task routes

**Fungsi:** Mengelola routing untuk console commands

### 6. channels.php

**Tujuan:** Mendefinisikan broadcast channels
**Isi:**

-   Channel definitions untuk real-time features
-   Channel authorization

**Fungsi:** Mengelola routing untuk broadcasting channels

## Mapping Controller dengan Fitur

### 1. Authentication Module

**Controller:** `AuthController`
**Fitur:**

-   User login/logout
-   Password management
-   Session management
-   User profile management

**Routes:** `/api/v1/auth/*`

### 2. Product Management Module

**Controllers:** `ProductController`, `CategoryController`, `ProductOptionController`, `RecipeController`
**Fitur:**

-   CRUD operations untuk produk
-   Manajemen kategori
-   Opsi produk (size, color, dll)
-   Resep produk untuk BOM

**Routes:** `/api/v1/products/*`, `/api/v1/categories/*`, `/api/v1/product-options/*`, `/api/v1/recipes/*`

### 3. Order Management Module

**Controllers:** `OrderController`, `TableController`
**Fitur:**

-   Manajemen pesanan
-   Manajemen meja
-   Order lifecycle management
-   Table occupancy tracking

**Routes:** `/api/v1/orders/*`, `/api/v1/tables/*`

### 4. Payment Management Module

**Controllers:** `PaymentController`, `PaymentMethodController`
**Fitur:**

-   Proses pembayaran
-   Manajemen metode pembayaran
-   Refund management
-   Payment gateway integration

**Routes:** `/api/v1/payments/*`, `/api/v1/payment-methods/*`

### 5. Inventory Management Module

**Controllers:** `InventoryController`, `InventoryReportController`
**Fitur:**

-   Manajemen stok
-   Pergerakan inventori
-   Laporan inventori
-   COGS calculation

**Routes:** `/api/v1/inventory/*`, `/api/v1/inventory-reports/*`

### 6. Customer Management Module

**Controllers:** `MemberController`
**Fitur:**

-   Manajemen member
-   Loyalty program
-   Customer analytics
-   Member tier management

**Routes:** `/api/v1/members/*`

### 7. Staff Management Module

**Controllers:** `StaffController`, `InvitationController`
**Fitur:**

-   Manajemen staff
-   Role dan permission management
-   Staff invitation system
-   Performance tracking

**Routes:** `/api/v1/staff/*`, `/api/v1/invitations/*`

### 8. Financial Management Module

**Controllers:** `CashSessionController`, `ExpenseController`, `CashFlowReportController`
**Fitur:**

-   Manajemen sesi kasir
-   Manajemen pengeluaran
-   Laporan cash flow
-   Financial analytics

**Routes:** `/api/v1/cash-sessions/*`, `/api/v1/expenses/*`, `/api/v1/cash-flow-reports/*`

### 9. Reporting Module

**Controller:** `ReportController`
**Fitur:**

-   Dashboard analytics
-   Sales reports
-   Inventory reports
-   Financial reports
-   Customer analytics
-   Export functionality

**Routes:** `/api/v1/reports/*`

### 10. Subscription Management Module

**Controllers:** `SubscriptionController`, `PlanController`, `SubscriptionPaymentController`
**Fitur:**

-   Manajemen subscription
-   Plan management
-   Payment processing
-   Usage tracking

**Routes:** `/api/v1/subscription/*`, `/api/v1/plans/*`, `/api/v1/subscription-payments/*`

### 11. Store Management Module

**Controller:** `StoreSwitchController`
**Fitur:**

-   Store switching untuk admin
-   Multi-store management
-   Store context management

**Routes:** `/api/v1/admin/stores/*`

### 12. Sync Module

**Controller:** `SyncController`
**Fitur:**

-   Data synchronization
-   Batch sync operations
-   Sync status monitoring
-   Queue management

**Routes:** `/api/v1/sync/*`

### 13. Integration Module

**Controller:** `MidtransWebhookController`
**Fitur:**

-   Webhook handling
-   Payment gateway integration
-   Event processing

**Routes:** `/api/v1/webhooks/*`

## Kesimpulan

Struktur modul dan controller XpressPOS dirancang dengan prinsip separation of concerns yang jelas. Setiap modul memiliki tanggung jawab yang spesifik dan terorganisir dengan baik dalam folder yang sesuai. Controller mengikuti pola RESTful API dengan versioning yang konsisten, sementara service layer menangani business logic yang kompleks.
