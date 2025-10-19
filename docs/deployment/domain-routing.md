# Domain Routing Guide

## Domain Mapping

| Domain                | Fungsi                              | Source routes / entry point                     |
|-----------------------|--------------------------------------|-------------------------------------------------|
| `xpresspos.id`        | Landing / marketing site             | `routes/web.php`
| `api.xpresspos.id`    | REST API (v1, Sanctum, dsb.)         | `routes/api.php` (tanpa prefix `/api`)
| `user.xpresspos.id`   | Owner dashboard (autentikasi web)    | `routes/owner.php`
| `admin.xpresspos.id`  | Filament admin panel                 | `app/Providers/Filament/AdminPanelProvider.php`

Semua domain dikonfigurasi lewat `config/domains.php` dan parameter env:

```
LANDING_DOMAIN=xpresspos.id
OWNER_DOMAIN=user.xpresspos.id
ADMIN_DOMAIN=admin.xpresspos.id
API_DOMAIN=api.xpresspos.id
```

## RouteServiceProvider
- `app/Providers/RouteServiceProvider.php` mendeteksi domain produksi.  
- Jika domain tidak diisi / masih `*.localhost`, framework kembali ke prefix (`/api`, `/owner`).  
- Untuk domain produksi, route langsung diikat via `Route::domain($domain)`.

## Filament Admin
- Panel admin menggunakan domain (`config('domains.admin')`).  
- Jika domain valid, path panel otomatis root (`/`). Jika tidak, fallback ke `/admin`.

## Nginx
Tambahkan server block untuk setiap domain yang mem-proxy ke service Octane (`127.0.0.1:8083`). Contoh `api.xpresspos.id` ada di server. Untuk domain lain, salin block dan ubah `server_name`.

## Environment & Secrets
- `.env.production` hanya menyertakan placeholder, gunakan `.env.production.local` untuk nilai nyata.  
- Secrets GitHub `ENV_PRODUCTION` harus berisi semua domain + kredensial DB dan mail.

## Checklist Deploy Multi Domain
1. Pastikan domain environment sudah terisi (landing, owner, admin, api).  
2. Jalankan workflow `CI-CD` (build → deploy).  
3. Verifikasi:
   - `curl -k https://api.xpresspos.id/healthz`
   - Buka `https://xpresspos.id`, `https://user.xpresspos.id`, `https://admin.xpresspos.id`

Dokumen ini perlu diperbarui bila ada domain tambahan atau perubahan topologi (misal migrasi ke Kubernetes / layanan terpisah).
