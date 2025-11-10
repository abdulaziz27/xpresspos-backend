# Routing Guide

## URL Structure

| URL / Domain           | Fungsi                              | Source routes / entry point                     |
|-----------------------|--------------------------------------|-------------------------------------------------|
| `xpresspos.id`        | Landing / marketing site             | `routes/web.php`
| `xpresspos.id/owner`  | Owner dashboard (Filament)           | `app/Providers/Filament/OwnerPanelProvider.php`
| `xpresspos.id/admin`  | Admin panel (Filament)               | `app/Providers/Filament/AdminPanelProvider.php`
| `api.xpresspos.id`    | REST API (v1, Sanctum, dsb.)         | `routes/api.php` (tanpa prefix `/api`)

## Path-Based Routing

Owner dan Admin panels menggunakan path-based routing untuk menghindari masalah dengan domain routing:
- Owner Panel: `https://xpresspos.id/owner`
- Admin Panel: `https://xpresspos.id/admin`

Konfigurasi melalui environment variables:

```
LANDING_DOMAIN=xpresspos.id
OWNER_URL=https://xpresspos.id/owner
ADMIN_URL=https://xpresspos.id/admin
API_DOMAIN=api.xpresspos.id
```

## Filament Panels

- Owner Panel: Menggunakan `$panel->path('owner')` di `OwnerPanelProvider`
- Admin Panel: Menggunakan `$panel->path('admin')` di `AdminPanelProvider`
- Tidak lagi menggunakan domain routing untuk panels

## Nginx / Ingress

Hanya perlu konfigurasi untuk:
- `xpresspos.id` (landing + owner + admin panels via paths)
- `api.xpresspos.id` (API subdomain)

## Environment & Secrets

- `.env.production` hanya menyertakan placeholder, gunakan `.env.production.local` untuk nilai nyata.  
- Secrets GitHub `ENV_PRODUCTION` harus berisi `OWNER_URL` dan `ADMIN_URL` (bukan `OWNER_DOMAIN` dan `ADMIN_DOMAIN`).

## Checklist Deploy

1. Pastikan environment variables sudah terisi (`OWNER_URL`, `ADMIN_URL`, `API_DOMAIN`).  
2. Jalankan workflow `CI-CD` (build → deploy).  
3. Verifikasi:
   - `curl -k https://api.xpresspos.id/healthz`
   - Buka `https://xpresspos.id`, `https://xpresspos.id/owner`, `https://xpresspos.id/admin`

Dokumen ini perlu diperbarui bila ada perubahan topologi routing.
