# Panduan Secrets & Variabel Lingkungan

Gunakan tabel berikut sebagai referensi saat mengisi GitHub Secrets atau membuat `Secret` di kluster Kubernetes.

| Nama                    | Sumber / Contoh                         | Wajib | Keterangan |
|-------------------------|-----------------------------------------|:-----:|------------|
| `APP_KEY`               | `base64:eHaQW0RVq9Ojp0pVo0Zs847rjgWeBuS7t1l3q8wC6XQ=` | ✅ | Harus sama dengan `.env.production`. |
| `DB_HOST`               | mis. `31.97.187.17`                     | ✅ | Host MySQL produksi. |
| `DB_DATABASE`           | mis. `xpresspos_prod`                   | ✅ | Nama database. |
| `DB_USERNAME`           | mis. `xpresspos_prod`                   | ✅ | Username database. |
| `DB_PASSWORD`           | -                                       | ✅ | Password database. |
| `MAIL_HOST`             | mis. `smtp.gmail.com`                   | ✅ | Host SMTP. |
| `MAIL_PORT`             | `465` atau `587`                        | ✅ | Port SMTP. |
| `MAIL_USERNAME`         | `hello@xpresspos.id`                   | ✅ | Username/Email pengirim. |
| `MAIL_PASSWORD`         | sandi aplikasi                          | ✅ | Gunakan App Password. |
| `MAIL_ENCRYPTION`       | `ssl` atau `tls`                        | ✅ | Protokol enkripsi SMTP. |
| `MAIL_FROM_ADDRESS`     | `no-reply@xpresspos.id`                 | ✅ | Diisi kalau ingin override default. |
| `MAIL_FROM_NAME`        | `XpressPOS`                             | ✅ | Nama pengirim default. |
| `MIDTRANS_SERVER_KEY`   | (kosong)                                | ⭕️ | Isi saat integrasi Midtrans siap. |
| `MIDTRANS_CLIENT_KEY`   | (kosong)                                | ⭕️ | Isi saat integrasi Midtrans siap. |
| `MIDTRANS_PARTNER_CODE` | (kosong)                                | ⭕️ | Opsional. |
| `SENTRY_LARAVEL_DSN`    | (kosong)                                | ⭕️ | Isi bila memakai Sentry. |
| `REGISTRY_USERNAME`     | Username GitHub / PAT                   | ✅ | Untuk login ke GHCR. |
| `REGISTRY_PASSWORD`     | PAT dengan scope `write:packages`       | ✅ | Token akses GHCR. |
| `SSH_HOST`              | `31.97.187.17`                          | ✅ | Host VPS untuk deploy. |
| `SSH_USER`              | `root` (atau user lain)                 | ✅ | Username SSH. |
| `SSH_KEY`               | Private key OpenSSH                     | ✅ | Key yang punya akses ke server. |
| `ENV_PRODUCTION`        | Salin isi `deploy/.env.example` (isi nilai produksi) | ✅ | Digunakan untuk menulis `.env` di server. |

> Baris dengan simbol ⭕️ opsional; biarkan kosong sampai layanannya tersedia.
