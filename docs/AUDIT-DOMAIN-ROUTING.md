# Audit Domain Routing - XpressPOS Backend

**Tanggal Audit**: 2025-11-10  
**Status**: ❌ **MASALAH KRITIS DITEMUKAN**

## 🔍 Executive Summary

Masalah utama: **POST login request tidak sampai ke server** untuk Filament Owner Panel di `dashboard.xpresspos.id`. Route list menunjukkan **TIDAK ADA POST route** untuk `filament.owner.auth.login` di domain `dashboard.xpresspos.id`.

### Root Cause
Filament login POST route tidak terdaftar dengan benar untuk domain routing. Filament menggunakan route internal yang mungkin tidak terlihat di route list, tapi POST login request tidak sampai ke server sama sekali.

## 📊 Temuan Audit

### 1. Route Registration Issue ❌

**Masalah**: Tidak ada POST route untuk `filament.owner.auth.login` di `dashboard.xpresspos.id`

**Route List di Server**:
```
GET|HEAD  dashboard.xpresspos.id/login filament.owner.auth.login ✅
POST      dashboard.xpresspos.id/logout ✅
❌ TIDAK ADA POST route untuk login
```

**Kemungkinan Penyebab**:
- Filament login POST route tidak terdaftar dengan benar untuk domain routing
- Filament menggunakan route internal yang tidak terlihat di route list
- Ada masalah dengan domain routing yang membuat POST login tidak ter-match

### 2. Log Analysis ❌

**Masalah**: Tidak ada log POST login sama sekali

**Temuan**:
- ✅ GET `/login` - Ada log
- ❌ POST `/login` - **TIDAK ADA LOG**
- ❌ "OwnerPanel auth gate: ENTRY" - **TIDAK PERNAH DIPANGGIL**
- ❌ "Authenticate middleware: Entry" untuk POST - **TIDAK ADA**

**Kesimpulan**: POST login request **TIDAK SAMPAI KE SERVER** sama sekali.

### 3. Konfigurasi Domain Routing ✅

**Status**: Konfigurasi sudah benar

**File yang Diperiksa**:
- ✅ `k8s/ingress.yaml` - Domain routing sudah benar
- ✅ `k8s/configmap-app.yaml` - Environment variables sudah benar
- ✅ `app/Providers/Filament/OwnerPanelProvider.php` - Domain routing sudah dikonfigurasi
- ✅ `config/session.php` - Session domain sudah benar (`.xpresspos.id`)
- ✅ `config/sanctum.php` - Sanctum stateful domains sudah benar

**Environment Variables di Server**:
```
SESSION_DOMAIN: .xpresspos.id ✅
SESSION_SECURE_COOKIE: true ✅
SANCTUM_STATEFUL_DOMAINS: dashboard.xpresspos.id,admin.xpresspos.id,api.xpresspos.id ✅
OWNER_DOMAIN: dashboard.xpresspos.id ✅
OWNER_URL: https://dashboard.xpresspos.id ✅
```

### 4. User Data ✅

**Status**: User data sudah benar

**Temuan**:
- ✅ User `owner@xpresspos.id` ada di database
- ✅ User punya `store_id`
- ✅ User punya role `owner`
- ✅ Email sudah verified
- ✅ Password sudah benar (`password123`)

### 5. Middleware & Auth Gate ✅

**Status**: Konfigurasi sudah benar

**Temuan**:
- ✅ `EnsureFilamentTeamContext` middleware berjalan (ada log)
- ✅ `OwnerPanelProvider` auth gate sudah dikonfigurasi dengan benar
- ❌ Auth gate **TIDAK PERNAH DIPANGGIL** karena POST login tidak sampai

## 🎯 Saran Realistis

### Option A: Perbaiki Domain Routing (Kompleks)

**Kesulitan**: ⭐⭐⭐⭐⭐ (Sangat Sulit)  
**Waktu**: 2-4 jam debugging  
**Risiko**: Tinggi - Masalah mungkin tidak teratasi

**Langkah-langkah**:
1. Debug Filament login POST route registration untuk domain routing
2. Cek apakah ada masalah dengan Nginx/proxy yang memblokir POST request
3. Cek apakah ada masalah dengan CSRF token yang tidak terkirim dengan benar
4. Mungkin perlu update Filament atau konfigurasi khusus

**Kemungkinan Masalah**:
- Filament v4 mungkin memiliki bug dengan domain routing untuk POST request
- Nginx/proxy mungkin memblokir POST request untuk subdomain
- CSRF token mungkin tidak terkirim dengan benar untuk subdomain

**Rekomendasi**: ❌ **TIDAK DISARANKAN** - Terlalu kompleks dan tidak ada jaminan berhasil

---

### Option B: Ubah ke Path-Based Routing (Sederhana) ✅ **DISARANKAN**

**Kesulitan**: ⭐⭐ (Mudah)  
**Waktu**: 30-60 menit  
**Risiko**: Rendah - Sudah terbukti bekerja di local

**Keuntungan**:
- ✅ Lebih sederhana dan mudah di-debug
- ✅ Tidak ada masalah dengan subdomain routing
- ✅ Sudah terbukti bekerja di local development
- ✅ Lebih mudah untuk maintenance
- ✅ Tidak perlu konfigurasi DNS tambahan

**Struktur URL Baru**:
- Landing: `https://xpresspos.id/`
- Owner Panel: `https://xpresspos.id/owner-panel`
- Admin Panel: `https://xpresspos.id/admin-panel`
- API: `https://api.xpresspos.id/` (tetap subdomain untuk API)

**Langkah-langkah**:
1. Update `OwnerPanelProvider` untuk menggunakan path-based routing
2. Update `AdminPanelProvider` untuk menggunakan path-based routing
3. Update `k8s/configmap-app.yaml` - Hapus `OWNER_DOMAIN` dan `ADMIN_DOMAIN`
4. Update `k8s/ingress.yaml` - Hapus routing untuk `dashboard.xpresspos.id` dan `admin.xpresspos.id`
5. Update semua redirect dan link di codebase
6. Update environment variables di GitHub Secrets
7. Test di local dan production

**File yang Perlu Diubah**:
- `app/Providers/Filament/OwnerPanelProvider.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `k8s/configmap-app.yaml`
- `k8s/ingress.yaml`
- Semua file yang menggunakan `config('app.owner_url')` atau `config('domains.owner')`
- Semua redirect ke owner/admin panel

**Rekomendasi**: ✅ **SANGAT DISARANKAN** - Lebih sederhana, lebih mudah di-debug, dan sudah terbukti bekerja

---

## 📋 Perbandingan Option A vs Option B

| Aspek | Option A (Domain Routing) | Option B (Path-Based) |
|-------|---------------------------|----------------------|
| **Kompleksitas** | ⭐⭐⭐⭐⭐ Sangat Sulit | ⭐⭐ Mudah |
| **Waktu** | 2-4 jam | 30-60 menit |
| **Risiko** | Tinggi | Rendah |
| **Maintenance** | Sulit | Mudah |
| **Debugging** | Sulit | Mudah |
| **DNS Setup** | Perlu 4 subdomain | Hanya 1 domain + 1 subdomain (API) |
| **Proven** | ❌ Belum terbukti | ✅ Sudah terbukti di local |
| **Rekomendasi** | ❌ Tidak disarankan | ✅ **Sangat disarankan** |

---

## 🚀 Rekomendasi Final

**Saran**: **UBAH KE PATH-BASED ROUTING (Option B)**

**Alasan**:
1. ✅ Lebih sederhana dan mudah di-debug
2. ✅ Sudah terbukti bekerja di local development
3. ✅ Tidak ada masalah dengan subdomain routing
4. ✅ Lebih mudah untuk maintenance
5. ✅ Waktu implementasi lebih cepat (30-60 menit vs 2-4 jam)
6. ✅ Risiko lebih rendah

**Catatan**: API tetap menggunakan subdomain (`api.xpresspos.id`) karena itu adalah best practice untuk API.

---

## 📝 Next Steps (Jika Memilih Option B)

1. ✅ Update `OwnerPanelProvider` untuk path-based routing
2. ✅ Update `AdminPanelProvider` untuk path-based routing
3. ✅ Update `k8s/configmap-app.yaml`
4. ✅ Update `k8s/ingress.yaml`
5. ✅ Update semua redirect dan link
6. ✅ Update environment variables
7. ✅ Test di local
8. ✅ Deploy ke production
9. ✅ Test di production

---

## 🔧 Detail Teknis Option B

### 1. Update OwnerPanelProvider

```php
// Hapus domain routing, gunakan path-based
$panel->path('owner-panel'); // Bukan $panel->domain($ownerDomain)->path('/');
```

### 2. Update AdminPanelProvider

```php
// Hapus domain routing, gunakan path-based
$panel->path('admin-panel'); // Bukan $panel->domain($adminDomain)->path('/');
```

### 3. Update k8s/configmap-app.yaml

```yaml
# Hapus:
# OWNER_DOMAIN: dashboard.xpresspos.id
# OWNER_URL: https://dashboard.xpresspos.id
# ADMIN_DOMAIN: admin.xpresspos.id

# Tambah:
OWNER_URL: https://xpresspos.id/owner-panel
ADMIN_URL: https://xpresspos.id/admin-panel
```

### 4. Update k8s/ingress.yaml

```yaml
# Hapus routing untuk dashboard.xpresspos.id dan admin.xpresspos.id
# Hanya keep:
# - api.xpresspos.id (untuk API)
# - xpresspos.id (untuk semua web routes)
```

### 5. Update Semua Redirect

Cari semua penggunaan:
- `config('app.owner_url')`
- `config('domains.owner')`
- `config('domains.admin')`
- Redirect ke `dashboard.xpresspos.id`
- Redirect ke `admin.xpresspos.id`

Ubah menjadi:
- `config('app.owner_url')` → `https://xpresspos.id/owner-panel`
- `config('app.admin_url')` → `https://xpresspos.id/admin-panel`

---

## ✅ Kesimpulan

**Masalah**: POST login request tidak sampai ke server untuk domain routing.

**Solusi Terbaik**: **Ubah ke path-based routing** (Option B)

**Alasan**: Lebih sederhana, lebih mudah di-debug, sudah terbukti bekerja, dan lebih mudah untuk maintenance.

**Waktu Implementasi**: 30-60 menit

**Risiko**: Rendah

