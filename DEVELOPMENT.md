# Fejlesztői környezet — gyors útmutató

A Previse v2 fejlesztői környezete három futó komponensből áll:
- **Backend stack** Docker-ben (PHP 8.4-fpm + Nginx + MySQL 8 + Mailpit + phpMyAdmin)
- **Queue worker** (a Docker `app` konténerben háttérben)
- **Frontend dev szerver** (Vite, lokálisan)

## Indítás

### 1. Docker stack
A projekt gyökerében (`F:\projects\previse_new`):

```bash
docker compose up -d
```

Ez elindítja az összes konténert a háttérben. Az első indításkor `~30 mp`, később `~5 mp`.

**Ellenőrzés:**
```bash
docker compose ps
```
Mind az 5 konténer (`previse-app`, `previse-nginx`, `previse-mysql`, `previse-mailpit`, `previse-phpmyadmin`) `Up` állapotú legyen, a `mailpit` és `mysql` `(healthy)` jelölést kap.

### 2. Queue worker (háttérben)
A Mailable-k `ShouldQueue`-t implementálnak (M3), ezért külön worker kell az email-ek tényleges kiküldéséhez:

```bash
docker exec -d previse-app php artisan queue:work --tries=3 --sleep=1
```

A `-d` (`detach`) miatt háttérben fut, nem blokkolja a terminált. Ha leáll a Docker stack és újraindítod, ezt is újra el kell indítani.

**Hibakeresés**: ha valami queue-os feladat elakadt, megnézheted:
```bash
docker exec previse-app php artisan queue:failed
docker exec previse-app php artisan queue:retry all
```

### 3. Frontend dev szerver (Vite)
Új terminálban a `previse-web` mappából:

```bash
cd F:\projects\previse_new\previse-web
npm run dev
```

Vagy egy lépésben Git Bash-ből:
```bash
cd /f/projects/previse_new/previse-web && nohup npm run dev > /tmp/vite.log 2>&1 &
```

Vite `http://localhost:5173/`-en elérhető pár másodperc alatt.

## Használat

| URL | Leírás |
|-----|--------|
| http://localhost:5173 | **A Vue alkalmazás** (fejlesztői Vite szerver, hot-reload) |
| http://localhost:8000 | Backend API (Laravel/Nginx) — közvetlenül ritkán kell |
| http://localhost:8000/api/v1/health/details | Health check JSON-ben |
| http://localhost:8025 | **Mailpit webUI** (kimenő e-mailek) |
| http://localhost:8080 | phpMyAdmin (`previse-mysql`, root/root, vagy previse/previse) |

### Belépési adatok (seederből)

- **Email**: `admin@previse.hu`
- **Jelszó**: `Admin123!`

Ez a Platform szuper-adminisztrátor. Belépés után a fejlécben tudsz váltani szervezet-kontextusba (impersonation) az XY Karbantartó Kft.-be.

## Leállítás

```bash
docker compose down
```

A Vite dev szervert `Ctrl+C`-vel állítsd le, vagy ha háttérben fut Git Bash-ben:
```bash
pkill -f "vite"
```

A Docker volume (MySQL adatok) megmarad, így a következő indításkor a felhasználók, szervezetek, adatok ott vannak.

## Friss telepítés / DB reset

Ha bármi miatt teljesen tiszta DB-vel kell kezdened:

```bash
# DB újraépítés és seed (Platform + admin user létrehozása)
docker exec previse-app php artisan migrate:fresh --seed

# Demo előfizető szervezet (XY Karbantartó Kft.)
docker exec previse-app php artisan db:seed --class=DemoSubscriberSeeder
```

## Tesztek futtatása

```bash
# Teljes Pest tesztsor (jelenleg 117 teszt)
docker exec previse-app php artisan test

# Csak egy fájl
docker exec previse-app php artisan test --filter=AccountDeletionTest

# Frontend TypeScript ellenőrzés
cd F:\projects\previse_new\previse-web
npx vue-tsc --noEmit
```

## Hasznos artisan parancsok

```bash
# Scheduled feladatok listája (cron-ban mit futtatna)
docker exec previse-app php artisan schedule:list

# Lejárt fiókok anonimizálása (M7 — kézi futtatás)
docker exec previse-app php artisan users:finalize-deletions --dry-run

# Régi auth események törlése (M8 — kézi futtatás)
docker exec previse-app php artisan auth:prune-events --dry-run

# Cache törlés (config/.env változtatás után)
docker exec previse-app php artisan optimize:clear
```

## Gyakori hibák

**„API 401 / Unauthenticated" minden kérésnél** → Vite proxy nem ér el a backendhez. Ellenőrizd, hogy a `previse-nginx` konténer fut-e (`docker compose ps`), és hogy a 8000-es port szabad-e.

**Email nem érkezik Mailpit-be** → A queue worker leállt. Indítsd újra (lásd 2. lépés).

**„Database connection refused"** → A `previse-mysql` még boot-ol. Várj `~10 mp`-et, vagy nézd meg `docker compose logs mysql -f`.

**Tailwind / vue-tsc lassú** → Első indítás után gyorsabb. Ha rendszeresen újrakezded, lehet, hogy `node_modules`-t kell újra telepíteni.
