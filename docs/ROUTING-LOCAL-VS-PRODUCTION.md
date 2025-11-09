# Routing: Perbedaan Lokal vs Production

## 📋 Ringkasan

Aplikasi XpressPOS menggunakan **domain-based routing** di production dan **path-based routing** di lokal. Dokumen ini menjelaskan semua perbedaan routing antara kedua environment.

---

## 🌐 Domain Configuration

### Production Environment

| Domain | Fungsi | Routing Method |
|--------|--------|----------------|
| `xpresspos.id` | Landing page & marketing | Domain-based |
| `dashboard.xpresspos.id` | Owner dashboard (Filament) | Domain-based |
| `admin.xpresspos.id` | Admin panel (Filament) | Domain-based |
| `api.xpresspos.id` | REST API | Domain-based |

### Local Environment

| Path | Fungsi | Routing Method |
|------|--------|----------------|
| `http://localhost/` | Landing page | Path-based |
| `http://localhost/owner-panel` | Owner dashboard (Filament) | Path-based |
| `http://localhost/admin-panel` | Admin panel (Filament) | Path-based |
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

#### Production (`dashboard.xpresspos.id`)
```
https://dashboard.xpresspos.id/                    → Filament Owner Dashboard
https://dashboard.xpresspos.id/login               → Filament Login Page
https://dashboard.xpresspos.id/categories          → Categories Resource
https://dashboard.xpresspos.id/products            → Products Resource
https://dashboard.xpresspos.id/orders              → Orders Resource
https://dashboard.xpresspos.id/cash-sessions       → Cash Sessions Resource
... (semua Filament resources di root path)
```

**Konfigurasi:**
- Domain: `dashboard.xpresspos.id`
- Path: `/` (root)
- Method: `$panel->domain('dashboard.xpresspos.id')->path('/')`
- URL Force: `URL::forceRootUrl('https://dashboard.xpresspos.id')` ✅ **CRITICAL**

#### Local (`localhost/owner-panel`)
```
http://localhost/owner-panel/                      → Filament Owner Dashboard
http://localhost/owner-panel/login                  → Filament Login Page
http://localhost/owner-panel/categories            → Categories Resource
http://localhost/owner-panel/products               → Products Resource
http://localhost/owner-panel/orders                → Orders Resource
http://localhost/owner-panel/cash-sessions          → Cash Sessions Resource
... (semua Filament resources dengan prefix /owner-panel)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain)
- Path: `/owner-panel`
- Method: `$panel->path('owner-panel')`

**File:** `app/Providers/Filament/OwnerPanelProvider.php` (lines 85-101)

**Conditional Logic:**
```php
if ($this->shouldUseDomain($ownerDomain)) {
    // Production: domain-based routing
    $fullDomain = 'https://' . $ownerDomain;
    URL::forceRootUrl($fullDomain); // CRITICAL untuk route matching
    $panel->domain($ownerDomain)->path('/');
} else {
    // Local: path-based routing
    $panel->path('owner-panel');
}
```

---

### 3. Admin Panel Routes (Filament Panel)

#### Production (`admin.xpresspos.id`)
```
https://admin.xpresspos.id/                        → Filament Admin Dashboard
https://admin.xpresspos.id/login                   → Filament Login Page
https://admin.xpresspos.id/stores                  → Stores Resource
https://admin.xpresspos.id/users                    → Users Resource
https://admin.xpresspos.id/subscriptions           → Subscriptions Resource
... (semua Filament admin resources di root path)
```

**Konfigurasi:**
- Domain: `admin.xpresspos.id`
- Path: `/` (root)
- Method: `$panel->domain('admin.xpresspos.id')->path('/')`

#### Local (`localhost/admin-panel`)
```
http://localhost/admin-panel/                      → Filament Admin Dashboard
http://localhost/admin-panel/login                  → Filament Login Page
http://localhost/admin-panel/stores                 → Stores Resource
http://localhost/admin-panel/users                  → Users Resource
http://localhost/admin-panel/subscriptions         → Subscriptions Resource
... (semua Filament admin resources dengan prefix /admin-panel)
```

**Konfigurasi:**
- Domain: `null` (tidak menggunakan domain)
- Path: `/admin-panel`
- Method: `$panel->path('admin-panel')`

**File:** `app/Providers/Filament/AdminPanelProvider.php` (lines 77-81)

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
    // Redirects to: https://dashboard.xpresspos.id/dashboard
});
```

#### Local
```php
// routes/web.php line 22
Route::get('/dashboard', function () {
    return redirect()->to(config('app.owner_url') . '/dashboard');
    // config('app.owner_url') = http://localhost/owner-panel
    // Redirects to: http://localhost/owner-panel/dashboard
});
```

**File:** `app/Http/Controllers/LandingController.php` (lines 39-47)

---

### Payment Success Redirect

#### Production
```php
// resources/views/landing/payment-success.blade.php
const dashboardUrl = '{{ config("app.owner_url", "https://dashboard.xpresspos.id") }}';
// Redirects to: https://dashboard.xpresspos.id
```

#### Local
```php
// resources/views/landing/payment-success.blade.php
const dashboardUrl = '{{ "/owner-panel" }}';
// Redirects to: http://localhost/owner-panel
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
OWNER_DOMAIN=dashboard.xpresspos.id
ADMIN_DOMAIN=admin.xpresspos.id
API_DOMAIN=api.xpresspos.id

# URL Configuration
OWNER_URL=https://dashboard.xpresspos.id
ADMIN_URL=https://admin.xpresspos.id
API_URL=https://api.xpresspos.id
FRONTEND_URL=https://xpresspos.id

# Session & Cookies
SESSION_DOMAIN=.xpresspos.id
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=dashboard.xpresspos.id,admin.xpresspos.id,api.xpresspos.id
CORS_ALLOWED_ORIGINS=https://api.xpresspos.id,https://xpresspos.id,https://admin.xpresspos.id,https://dashboard.xpresspos.id
```

### Local (`.env`)

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Domain Configuration (tidak digunakan di local)
# MAIN_DOMAIN=xpresspos.id
# OWNER_DOMAIN=owner.localhost
# ADMIN_DOMAIN=admin.localhost
# API_DOMAIN=api.localhost

# URL Configuration
OWNER_URL=http://localhost/owner-panel
ADMIN_URL=http://localhost/admin-panel
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

#### Local Logic (lines 99-101)
```php
else {
    $panel->path('owner-panel');
}
```

#### shouldUseDomain() Method (lines 268-291)
```php
protected function shouldUseDomain(?string $domain): bool
{
    if (blank($domain)) {
        return false;
    }

    $isProduction = app()->environment('production');
    $hasLocalhost = Str::contains($domain, ['localhost', '127.0.0.1']);
    $shouldUse = $isProduction && !$hasLocalhost;

    // Only use domain routing in production environment
    return $shouldUse;
}
```

**Hasil:**
- Production: `true` → menggunakan domain routing
- Local: `false` → menggunakan path routing

---

### Admin Panel Provider

**File:** `app/Providers/Filament/AdminPanelProvider.php`

#### Production Logic (lines 77-78)
```php
if ($this->shouldUseDomain($adminDomain)) {
    $panel->domain($adminDomain)->path('/');
}
```

#### Local Logic (lines 79-81)
```php
else {
    $panel->path('admin-panel');
}
```

**Note:** Admin panel **BELUM** menggunakan `URL::forceRootUrl()` - mungkin perlu ditambahkan juga!

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
return redirect()->to(config('app.owner_url', 'https://dashboard.xpresspos.id'));
```

**Files:**
- `app/Http/Controllers/LandingController.php` (line 44)
- `resources/views/landing/payment-success.blade.php`

---

## 📝 Checklist Deployment

### Pre-Deployment

- [ ] Pastikan semua domain environment variables ter-set di `.env.production.local`
- [ ] Pastikan `APP_ENV=production` di production
- [ ] Pastikan `URL::forceRootUrl()` dipanggil untuk domain routing
- [ ] Pastikan `config('app.owner_url')` ter-set dengan benar

### Post-Deployment

- [ ] Test login di `https://dashboard.xpresspos.id/login`
- [ ] Verify routes terdaftar: `php artisan route:list --domain=dashboard.xpresspos.id`
- [ ] Check log untuk "Domain routing configured"
- [ ] Check log untuk "OwnerPanel auth gate: ENTRY"
- [ ] Verify redirect setelah login berfungsi
- [ ] Test semua Filament resources dapat diakses

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

