# XpressPOS Backend

## Deskripsi Proyek

XpressPOS Backend adalah sistem Point of Sale (POS) berbasis Laravel yang dirancang untuk membantu bisnis restoran, kafe, dan retail dalam mengelola operasional harian. Sistem ini menyediakan API yang komprehensif untuk mengelola produk, pesanan, pembayaran, inventori, dan berbagai fitur bisnis lainnya.

## Tujuan dan Latar Belakang

Sistem ini dikembangkan untuk memenuhi kebutuhan bisnis modern yang memerlukan:

-   Manajemen produk dan kategori yang fleksibel
-   Sistem pesanan yang terintegrasi dengan meja dan pelanggan
-   Manajemen inventori yang akurat dengan perhitungan COGS
-   Sistem pembayaran yang mendukung berbagai metode pembayaran
-   Pelaporan bisnis yang komprehensif
-   Manajemen staff dengan sistem role dan permission
-   Sistem membership dan loyalty program

## Teknologi Utama

### Backend Framework

-   **Laravel 12.0** - Framework PHP utama
-   **PHP 8.2+** - Bahasa pemrograman
-   **Laravel Sanctum** - API Authentication
-   **Spatie Laravel Permission** - Role dan Permission Management

### Database

-   **SQLite** - Database utama (development)
-   **MySQL/PostgreSQL** - Database production (dapat dikonfigurasi)

### Admin Panel

-   **Filament 4.0** - Admin panel untuk manajemen data
-   **Livewire 3.0** - Komponen interaktif

### Payment Gateway

-   **Midtrans** - Payment gateway untuk pembayaran online

### Lainnya

-   **Laravel DomPDF** - Generate PDF untuk laporan
-   **Laravel Queue** - Background job processing
-   **Laravel Pail** - Log monitoring

## Arsitektur Aplikasi

### Struktur Umum

```
Frontend (Filament Admin Panel)
    ↓
API Layer (Laravel REST API)
    ↓
Business Logic Layer (Services, Controllers)
    ↓
Data Access Layer (Models, Repositories)
    ↓
Database Layer (SQLite/MySQL/PostgreSQL)
```

### Komponen Utama

1. **API Layer** - RESTful API dengan versioning (v1)
2. **Authentication** - Token-based authentication dengan Sanctum
3. **Authorization** - Role-based access control (RBAC)
4. **Business Logic** - Service layer untuk logika bisnis
5. **Data Models** - Eloquent models dengan relationships
6. **Background Jobs** - Queue system untuk operasi async

## Cara Setup dan Menjalankan Proyek

### Prerequisites

-   PHP 8.2 atau lebih tinggi
-   Composer
-   Node.js dan NPM (untuk asset compilation)
-   SQLite (default) atau MySQL/PostgreSQL

### Instalasi

1. **Clone repository**

    ```bash
    git clone <repository-url>
    cd xpresspos-backend
    ```

2. **Install dependencies**

    ```bash
    composer install
    npm install
    ```

3. **Setup environment**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Konfigurasi database**

    - Untuk SQLite (default): File `database/database.sqlite` sudah tersedia
    - Untuk MySQL/PostgreSQL: Update konfigurasi di `.env`

5. **Jalankan migration**

    ```bash
    php artisan migrate
    ```

6. **Seed database (opsional)**

    ```bash
    php artisan db:seed
    ```

7. **Compile assets**
    ```bash
    npm run build
    ```

### Menjalankan Aplikasi

#### Development Mode

```bash
# Jalankan semua service sekaligus
composer run dev

# Atau jalankan secara terpisah
php artisan serve          # Web server
php artisan queue:listen  # Queue worker
php artisan pail          # Log monitoring
npm run dev               # Asset compilation
```

#### Production Mode

```bash
# Optimize untuk production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Jalankan web server
php artisan serve --host=0.0.0.0 --port=8000
```

### Testing

```bash
# Jalankan semua test
composer run test

# Atau dengan artisan
php artisan test
```

## Struktur Folder Utama

```
app/
├── Http/Controllers/Api/V1/    # API Controllers
├── Models/                     # Eloquent Models
├── Services/                   # Business Logic Services
├── Jobs/                       # Background Jobs
├── Mail/                       # Email Templates
├── Notifications/              # Notification Classes
├── Policies/                   # Authorization Policies
└── Providers/                  # Service Providers

database/
├── migrations/                 # Database Migrations
├── seeders/                    # Database Seeders
└── factories/                  # Model Factories

routes/
├── api.php                     # API Routes
├── admin.php                    # Admin Panel Routes
└── web.php                      # Web Routes

resources/
├── views/                      # Blade Templates
├── css/                        # Stylesheets
└── js/                         # JavaScript Assets
```

## API Endpoints

Sistem menyediakan API endpoints yang terorganisir dalam beberapa grup:

-   **Authentication** (`/api/v1/auth/*`)
-   **Products & Categories** (`/api/v1/products/*`, `/api/v1/categories/*`)
-   **Orders & Tables** (`/api/v1/orders/*`, `/api/v1/tables/*`)
-   **Payments** (`/api/v1/payments/*`, `/api/v1/payment-methods/*`)
-   **Inventory** (`/api/v1/inventory/*`)
-   **Reports** (`/api/v1/reports/*`)
-   **Staff Management** (`/api/v1/staff/*`)
-   **Members** (`/api/v1/members/*`)

## Dokumentasi Tambahan

-   [SCOPE.md](./SCOPE.md) - Scope proyek per modul dan role
-   [FEATURES.md](./FEATURES.md) - Daftar fitur yang tersedia
-   [DATABASE.md](./DATABASE.md) - Dokumentasi skema database
-   [MODULES.md](./MODULES.md) - Struktur modul dan controller

## Kontribusi

Untuk berkontribusi pada proyek ini, silakan:

1. Fork repository
2. Buat feature branch
3. Commit perubahan
4. Push ke branch
5. Buat Pull Request

## Lisensi

Proyek ini menggunakan lisensi MIT. Lihat file `LICENSE` untuk detail lebih lanjut.
