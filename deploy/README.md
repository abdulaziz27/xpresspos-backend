# Deploy dengan Docker compose

Struktur
```
/srv/apps/xpresspos-backend
├── docker-compose.yml
└── .env         (isi dari secret `ENV_PRODUCTION`)
```

Perintah manual di server:
```bash
cd /srv/apps/xpresspos-backend
docker compose pull
docker compose up -d --remove-orphans
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan octane:reload || docker compose exec -T app rr reset
```

Pastikan Nginx mem-proxy subdomain ke port `8083` (atau port sesuai env `APP_PORT`).
