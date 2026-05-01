# Previse v2 — Deployment segédletek

Ez a mappa az éles élesbe-helyezés sablonjait és scriptjeit tartalmazza.

## Fájlok

| Fájl | Cél |
|------|-----|
| `deploy.sh` | Bash script: backend + frontend build, rsync feltöltés, szerver-oldali optimalizáció |
| `cpanel-cron.txt` | A CPanel Cron Jobs felületen beállítandó cron szabályok |

## Gyors start

1. **Másold át** a `deploy.sh`-t a saját környezetedhez igazítva (SSH_USER, SSH_HOST, REMOTE_*).
2. **Állítsd be** a szerveren a `.env`-t a `previse-api/.env.production.example` alapján.
3. **Hozd létre a DB-t** a CPanel-en + user/jelszó.
4. **Első deploy**:
   ```bash
   ./deploy/deploy.sh
   ```
5. **Egyszeri inicializálás** a szerveren:
   ```bash
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```
6. **Cron beállítás**: lásd `cpanel-cron.txt`.
7. **Verifikáció**: `curl https://example.hu/api/v1/health/details`.

## Részletes leírás

A teljes telepítési útmutató: [`docs/10-deployment.md`](../docs/10-deployment.md).
