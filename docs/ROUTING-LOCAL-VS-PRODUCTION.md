# Routing: Perbedaan Lokal vs Production

## 📋 Ringkasan

Aplikasi XpressPOS menggunakan **path-based routing** untuk Owner dan Admin panels di semua environment (local dan production). API tetap menggunakan subdomain. Dokumen ini menjelaskan routing structure.

---

## 🌐 URL Configuration

### Production Environment

| URL / Domain | Fungsi | Routing Method |
|--------------|--------|----------------|
| `xpresspos.id` | Landing page & marketing | Domain-based |
| `xpresspos.id/owner` | Owner dashboard (Filament) | Path-based |
| `xpresspos.id/admin` | Admin panel (Filament) | Path-based |
| `api.xpresspos.id` | REST API | Domain-based (subdomain) |

### Local Environment

| Path | Fungsi | Routing Method |
|------|--------|----------------|
| `http://localhost/` | Landing page | Path-based |
| `http://localhost/owner` | Owner dashboard (Filament) | Path-based |
| `http://localhost/admin` | Admin panel (Filament) | Path-based |
| `http://localhost/api/*` | REST API | Path-based |

---

## 🔄 Route Mapping Detail

### 1. Landing Page Routes

#### Production (`xpresspos.id`)
```
https://xpresspos.id/                    → LandingController@index
https://xpresspos.id/login               → LandingController@showLogin
https://xpresspos.id/register            → LandingController@showRegister
https://xpresspos.id/pricing             → LandingController@showPricing
https://xpresspos.id/checkout            → LandingController@showCheckout
https://xpresspos.id/payment/success     → LandingController@paymentSuccess
https://xpresspos.id/payment/failed      → LandingController@paymentFailed
```

#### Local (`localhost`)
```
http://localhost/                        → LandingController@index
http://localhost/login                   → LandingController@showLogin
http://localhost/register                → LandingController@showRegister
http://localhost/pricing                 → LandingController@showPricing
http://localhost/checkout                 → LandingController@showCheckout
http://localhost/payment/success          → LandingController@paymentSuccess
http://localhost/payment/failed           → LandingController@paymentFailed
```

**File:** `routes/web.php` (lines 7-55)

---

### 2. Owner Dashboard Routes (Filament Panel)

#### Production (`xpresspos.id/owner`)
```
https://xpresspos.id/owner/                         → Filament Owner Dashboard
https://xpresspos.id/owner/login                    → Filament Login Page
https://xpresspos.id/owner/categories               → Categories Resource
https://xpresspos.id/owner/products                 → Products Resource
https://xpresspos.id/owner/orders                   → Orders Resource
https://xpresspos.id/owner/cash-sessions            → Cash Sessions Resource
... (semua Filament resources dengan prefix /owner)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain routing)
- Path: `/owner`
- Method: `$panel->path('owner')`

#### Local (`localhost/owner`)
```
http://localhost/owner/                             → Filament Owner Dashboard
http://localhost/owner/login                        → Filament Login Page
http://localhost/owner/categories                   → Categories Resource
http://localhost/owner/products                     → Products Resource
http://localhost/owner/orders                       → Orders Resource
http://localhost/owner/cash-sessions                → Cash Sessions Resource
... (semua Filament resources dengan prefix /owner)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain)
- Path: `/owner`
- Method: `$panel->path('owner')`

**File:** `app/Providers/Filament/OwnerPanelProvider.php`

**Implementation:**
```php
// Path-based routing untuk semua environment
$panel->path('owner');
```

---

### 3. Admin Panel Routes (Filament Panel)

#### Production (`xpresspos.id/admin`)
```
https://xpresspos.id/admin/                         → Filament Admin Dashboard
https://xpresspos.id/admin/login                    → Filament Login Page
https://xpresspos.id/admin/stores                   → Stores Resource
https://xpresspos.id/admin/users                     → Users Resource
https://xpresspos.id/admin/subscriptions            → Subscriptions Resource
... (semua Filament admin resources dengan prefix /admin)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain routing)
- Path: `/admin`
- Method: `$panel->path('admin')`

#### Local (`localhost/admin`)
```
http://localhost/admin/                             → Filament Admin Dashboard
http://localhost/admin/login                         → Filament Login Page
http://localhost/admin/stores                       → Stores Resource
http://localhost/admin/users                         → Users Resource
http://localhost/admin/subscriptions                → Subscriptions Resource
... (semua Filament admin resources dengan prefix /admin)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain)
- Path: `/admin`
- Method: `$panel->path('admin')`

**File:** `app/Providers/Filament/AdminPanelProvider.php`

**Implementation:**
```php
// Path-based routing untuk semua environment
$panel->path('admin');
```

---

### 4. API Routes

#### Production (`api.xpresspos.id`)
```
https://api.xpresspos.id/                          → API Home (JSON)
https://api.xpresspos.id/v1/auth/login             → API Auth Login
https://api.xpresspos.id/v1/products                → API Products
https://api.xpresspos.id/v1/orders                  → API Orders
... (semua API routes tanpa prefix /api)
```

**Konfigurasi:**
- Domain: `api.xpresspos.id`
- Routes: `routes/api.php` (tanpa prefix `/api`)
- File: `routes/web.php` (lines 72-87)

#### Local (`localhost/api/*`)
```
http://localhost/api/v1/auth/login                  → API Auth Login
http://localhost/api/v1/products                    → API Products
http://localhost/api/v1/orders                      → API Orders
... (semua API routes dengan prefix /api)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain)
- Routes: `routes/api.php` (dengan prefix `/api`)
- File: `routes/api.php`

---

## 🔀 Redirect Flows

### After Login (Landing → Owner Dashboard)

#### Production
```php
// routes/web.php line 22
Route::get('/dashboard', function () {
    return redirect()->to(config('app.owner_url') . '/dashboard');
    // Redirects to: https://xpresspos.id/owner/dashboard
});
```

#### Local
```php
// routes/web.php line 22
Route::get('/dashboard', function () {
    return redirect()->to(config('app.owner_url') . '/dashboard');
    // config('app.owner_url') = http://localhost/owner
    // Redirects to: http://localhost/owner/dashboard
});
```

**File:** `app/Http/Controllers/LandingController.php` (lines 39-47)

---

### Payment Success Redirect

#### Production
```php
// resources/views/landing/payment-success.blade.php
const dashboardUrl = '{{ config("app.owner_url", "/owner") }}';
// Redirects to: https://xpresspos.id/owner
```

#### Local
```php
// resources/views/landing/payment-success.blade.php
const dashboardUrl = '{{ config("app.owner_url", "/owner") }}';
// Redirects to: http://localhost/owner
```

---

## ⚙️ Environment Variables

### Production (`.env.production.local` / GitHub Secrets)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.xpresspos.id

# Domain Configuration
MAIN_DOMAIN=xpresspos.id
API_DOMAIN=api.xpresspos.id

# URL Configuration (path-based routing)
OWNER_URL=https://xpresspos.id/owner
ADMIN_URL=https://xpresspos.id/admin
API_URL=https://api.xpresspos.id
FRONTEND_URL=https://xpresspos.id

# Session & Cookies
SESSION_DOMAIN=.xpresspos.id
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=xpresspos.id,api.xpresspos.id
CORS_ALLOWED_ORIGINS=https://api.xpresspos.id,https://xpresspos.id
```

### Local (`.env`)

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Domain Configuration (tidak digunakan di local)
# MAIN_DOMAIN=xpresspos.id
# API_DOMAIN=api.localhost

# URL Configuration (path-based routing)
OWNER_URL=http://localhost/owner
ADMIN_URL=http://localhost/admin
API_URL=http://localhost/api

# Session & Cookies
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost
CORS_ALLOWED_ORIGINS=http://localhost
```

---

## 🔍 Filament Panel Configuration

### Owner Panel Provider

**File:** `app/Providers/Filament/OwnerPanelProvider.php`

#### Production Logic (lines 86-98)
```php
if ($this->shouldUseDomain($ownerDomain)) {
    // ✅ CRITICAL: Force root URL untuk proper route matching
    $fullDomain = 'https://' . $ownerDomain;
    URL::forceRootUrl($fullDomain);
    
    $panel->domain($ownerDomain)->path('/');
    
    \Log::info('OwnerPanelProvider: Domain routing configured', [
        'domain' => $ownerDomain,
        'full_url' => $fullDomain,
    ]);
}
```

**Implementation:**
```php
// Path-based routing untuk semua environment
$panel->path('owner');
```

**Hasil:**
- Production: `https://xpresspos.id/owner`
- Local: `http://localhost/owner`

---

### Admin Panel Provider

**File:** `app/Providers/Filament/AdminPanelProvider.php`

**Implementation:**
```php
// Path-based routing untuk semua environment
$panel->path('admin');
```

**Hasil:**
- Production: `https://xpresspos.id/admin`
- Local: `http://localhost/admin`

---

## 🚨 Potensi Masalah & Solusi

### 1. Auth Gate Tidak Dipanggil di Production

**Masalah:**
- Auth gate di `OwnerPanelProvider` tidak dipanggil setelah login
- Routes tidak ter-match dengan benar

**Penyebab:**
- Filament domain routing memerlukan `URL::forceRootUrl()` untuk proper route matching
- Tanpa ini, Filament tidak bisa match request ke panel routes

**Solusi:**
```php
// ✅ SUDAH DIPERBAIKI
if ($this->shouldUseDomain($ownerDomain)) {
    $fullDomain = 'https://' . $ownerDomain;
    URL::forceRootUrl($fullDomain); // CRITICAL!
    $panel->domain($ownerDomain)->path('/');
}
```

**File:** `app/Providers/Filament/OwnerPanelProvider.php` (lines 89-90)

---

### 2. SQL Ambiguity Error

**Masalah:**
- Error: "Column 'store_id' in where clause is ambiguous"
- Terjadi saat `$user->getRoleNames()` atau `$user->hasRole()`

**Penyebab:**
- Team context tidak di-set sebelum role checks
- Spatie Permission melakukan JOIN yang menyebabkan ambiguous column

**Solusi:**
```php
// ✅ SUDAH DIPERBAIKI
// Set team context FIRST before any role checks
$storeId = $user->store_id ?? $user->primaryStore()?->id;
if ($storeId) {
    setPermissionsTeamId($storeId); // CRITICAL!
}
$userRoles = $user->getRoleNames()->toArray(); // Now safe
```

**File:** `app/Providers/Filament/OwnerPanelProvider.php` (lines 128-131)

---

### 3. Route Tidak Terdaftar dengan Domain

**Masalah:**
- Routes tidak muncul saat `php artisan route:list --domain=dashboard.xpresspos.id`
- Routes hanya muncul dengan path-based

**Penyebab:**
- `shouldUseDomain()` return `false` di production
- Domain tidak ter-set dengan benar di environment

**Solusi:**
- Pastikan `OWNER_DOMAIN=dashboard.xpresspos.id` di `.env.production.local`
- Pastikan `APP_ENV=production` di production
- Check log untuk "shouldUseDomain check"

---

### 4. Redirect Setelah Login Salah

**Masalah:**
- Setelah login, redirect ke URL yang salah
- Menggunakan URL lokal di production atau sebaliknya

**Penyebab:**
- `config('app.owner_url')` tidak ter-set dengan benar
- Hardcoded URL di views/controllers

**Solusi:**
```php
// ✅ SUDAH DIPERBAIKI
// Gunakan config() bukan hardcoded URL
return redirect()->to(config('app.owner_url', '/owner'));
```

**Files:**
- `app/Http/Controllers/LandingController.php` (line 44)
- `resources/views/landing/payment-success.blade.php`

---

## 📝 Checklist Deployment

### Pre-Deployment

- [ ] Pastikan semua URL environment variables ter-set di `.env.production.local` (`OWNER_URL`, `ADMIN_URL`)
- [ ] Pastikan `APP_ENV=production` di production
- [ ] Pastikan `config('app.owner_url')` dan `config('app.admin_url')` ter-set dengan benar
- [ ] Pastikan Kubernetes ConfigMap dan Ingress sudah diupdate (hapus domain routing untuk owner/admin)

### Post-Deployment

- [ ] Test login di `https://xpresspos.id/owner/login`
- [ ] Test login di `https://xpresspos.id/admin/login`
- [ ] Verify routes terdaftar: `php artisan route:list | grep owner` dan `php artisan route:list | grep admin`
- [ ] Check log untuk "OwnerPanel auth gate: ENTRY"
- [ ] Verify redirect setelah login berfungsi
- [ ] Test semua Filament resources dapat diakses di `/owner` dan `/admin`

---

## 🔗 Related Files

### Route Files
- `routes/web.php` - Main routes (landing, API domain, Filament domains)
- `routes/api.php` - API routes (local only, production uses domain)
- `routes/owner.php` - Owner-specific routes (minimal)
- `routes/admin.php` - Admin-specific routes (minimal)
- `routes/landing.php` - Landing routes (alternative)

### Configuration Files
- `config/domains.php` - Domain configuration
- `config/app.php` - Application configuration (owner_url)
- `.env` - Local environment
- `.env.production.local` - Production environment (GitHub Secrets)

### Provider Files
- `app/Providers/Filament/OwnerPanelProvider.php` - Owner panel configuration
- `app/Providers/Filament/AdminPanelProvider.php` - Admin panel configuration

### View Files
- `resources/views/landing/payment-success.blade.php` - Payment success redirect

### Controller Files
- `app/Http/Controllers/LandingController.php` - Landing page controller

---

## 🎯 Key Takeaways

1. **Production menggunakan domain-based routing** - setiap subdomain memiliki routes sendiri
2. **Local menggunakan path-based routing** - semua routes dengan prefix path
3. **Filament domain routing memerlukan `URL::forceRootUrl()`** - critical untuk route matching
4. **Team context harus di-set sebelum role checks** - mencegah SQL ambiguity
5. **Gunakan `config('app.owner_url')` bukan hardcoded URL** - untuk compatibility lokal/production

---

**Last Updated:** 2025-11-09
**Author:** AI Assistant
**Version:** 1.0

