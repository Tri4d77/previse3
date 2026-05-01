# 10 - CPanel deployment és környezet beállítások

## 1. Áttekintés

Az alkalmazás CPanel-es shared hosting-ra kerül telepítésre. Ez meghatároz bizonyos korlátokat, amiket a tervezésnél figyelembe kell venni.

## 2. CPanel korlátok és megoldások

| Korlát | Megoldás |
|--------|----------|
| Nincs root hozzáférés | Laravel standard konfigurációval működik |
| Nincs Redis | File-based cache és session; database queue driver |
| Nincs WebSocket | Polling (30s intervallum) az értesítésekhez |
| Nincs Supervisor | Cron-alapú queue worker (`php artisan queue:work --stop-when-empty`) |
| Korlátozott cron | Perc-szintű cron elérhető a legtöbb CPanel-en |
| Megosztott erőforrások | Eager loading, lapozás, cache használat |
| PHP verzió rögzített | PHP 8.4 beállítás a CPanel-ben |
| Nincs SSH (általában) | Git pull deployment vagy FTP/SFTP |

## 3. Szerver struktúra

### 3.1 Könyvtárstruktúra CPanel-en

```
/home/username/
├── public_html/                    ← Domain gyökér
│   ├── index.php                   ← Laravel entry point (módosított)
│   ├── .htaccess                   ← Apache rewrite szabályok
│   ├── api/                        ← Symlink vagy közvetett
│   └── storage/                    ← Symlink → ../previse-api/storage/app/public
│
├── previse-api/                    ← Laravel alkalmazás (NEM publikus!)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   │   ├── app/
│   │   │   ├── public/            ← Publikus fájlok (symlink-kel)
│   │   │   └── uploads/           ← Privát feltöltések
│   │   ├── framework/
│   │   │   ├── cache/
│   │   │   ├── sessions/
│   │   │   └── views/
│   │   └── logs/
│   ├── vendor/
│   ├── .env                        ← Környezeti változók
│   ├── artisan
│   └── composer.json
│
├── previse-web/                    ← Vue.js SPA (build kimenet)
│   └── dist/                       ← `npm run build` kimenet
│       ├── index.html
│       ├── assets/
│       │   ├── js/
│       │   └── css/
│       └── favicon.ico
│
└── logs/                           ← Egyéni naplók
```

### 3.2 public_html konfiguráció

A `public_html/index.php` a Laravel `public/index.php` módosított változata, ami a szülő könyvtárból tölti be az alkalmazást:

```php
// public_html/index.php
define('LARAVEL_START', microtime(true));

require __DIR__.'/../previse-api/vendor/autoload.php';

$app = require_once __DIR__.'/../previse-api/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
```

### 3.3 .htaccess (public_html)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # HTTPS kényszerítés
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # www eltávolítás
    RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
    RewriteRule ^(.*)$ https://%1/$1 [R=301,L]
    
    # Vue.js SPA kiszolgálás (nem-API kérések)
    RewriteCond %{REQUEST_URI} !^/api
    RewriteCond %{REQUEST_URI} !^/sanctum
    RewriteCond %{REQUEST_URI} !^/storage
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /index.html [L]
    
    # API kérések Laravelhez
    RewriteCond %{REQUEST_URI} ^/api [OR]
    RewriteCond %{REQUEST_URI} ^/sanctum
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ /index.php [L]
    
    # Storage symlink
    RewriteRule ^storage/(.*)$ /storage/$1 [L]
</IfModule>

# PHP beállítások
<IfModule mod_php.c>
    php_value upload_max_filesize 64M
    php_value post_max_size 64M
    php_value memory_limit 256M
    php_value max_execution_time 120
    php_value max_input_vars 3000
</IfModule>
```

## 4. Környezeti konfiguráció (.env)

> A teljes, kommentelt sablon: [`previse-api/.env.production.example`](../previse-api/.env.production.example).
> Másold át a szerveren `.env`-be, és töltsd ki a sárga jelölésű mezőket (DB, MAIL, APP_URL, SESSION_DOMAIN, APP_KEY).

A legfontosabb különbségek a fejlesztői .env-hez képest:

| Kulcs | Dev érték | Éles érték |
|-------|-----------|------------|
| APP_ENV | local | production |
| APP_DEBUG | true | **false** |
| APP_URL | http://localhost:8000 | https://example.hu |
| FRONTEND_URL | http://localhost:5173 | https://example.hu |
| DB_HOST | mysql (Docker) | 127.0.0.1 (CPanel localhost) |
| MAIL_MAILER | smtp (mailpit) | smtp (éles SMTP) |
| MAIL_HOST | mailpit | smtp.provider.hu |
| SESSION_DRIVER | file | database |
| SESSION_ENCRYPT | false | **true** |
| SESSION_DOMAIN | (üres) | .example.hu |
| SESSION_SECURE_COOKIE | (üres) | **true** |
| LOG_LEVEL | debug | warning |
| LOG_CHANNEL | stack | daily |

**Auth modul M3-M11 fázisok új beállításai:**
```env
AUTH_INVITATION_EXPIRES_DAYS=7         # meghívó érvényesség (M3)
AUTH_EMAIL_CHANGE_EXPIRES_MINUTES=60   # email-változtatás token (M6)
AUTH_ACCOUNT_DELETION_GRACE_DAYS=30    # fiók-törlés grace (M7)
AUTH_EVENTS_RETENTION_DAYS=90          # auth napló retention (M8)
```

## 5. Cron beállítások

### 5.1 Laravel Scheduler (kötelező)

CPanel → Cron Jobs → perc-szintű futtatás:

```
* * * * * cd /home/username/previse-api && php artisan schedule:run >> /dev/null 2>&1
```

### 5.2 Queue worker (cron-alapú)

Mivel nincs Supervisor, a queue worker-t is cron-nal indítjuk:

```
* * * * * cd /home/username/previse-api && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

Ez minden percben elindítja a queue worker-t, ami feldolgozza a sorban álló job-okat és legfeljebb 55 másodpercig fut.

### 5.3 Ütemezett feladatok (Laravel Scheduler-ben)

A scheduler definíciók a `previse-api/routes/console.php` fájlban vannak.

**Auth modul (M7-M8) — már implementálva:**

| Feladat | Parancs | Időzítés | Leírás |
|---------|---------|----------|--------|
| Fiók anonimizálás | `users:finalize-deletions` | Naponta 03:00 | Lejárt 30 napos grace-ű user fiókok anonimizálása (név megmarad, minden más reset) |
| Auth napló pruning | `auth:prune-events` | Naponta 03:30 | 90 napnál régebbi auth események törlése |
| Queue worker | `queue:work --stop-when-empty --max-time=50` | Percenként | Email queue feldolgozás (Supervisor helyett) |

**Üzleti modulok (M12+ jövőbeli):**

| Feladat | Időzítés | Leírás |
|---------|----------|--------|
| SLA ellenőrzés | 5 percenként | SLA határidők ellenőrzése, figyelmeztetések |
| Karbantartás generálás | Naponta 01:00 | Esedékes karbantartási feladatok létrehozása |
| Ismétlődő feladatok | Naponta 01:30 | Ismétlődő feladat-példányok generálása |
| Szerződés lejárat | Naponta 07:00 | Lejáró szerződések figyelmeztetése |
| Activity log archiválás | Havonta | 365 napnál régebbi bejegyzések archiválása |

### 5.4 Manuális futtatás

Szükség esetén bármelyik scheduled feladat futtatható kézzel:

```bash
# Lejárt grace-ű fiókok anonimizálása (preview módban)
php artisan users:finalize-deletions --dry-run

# Tényleges futtatás
php artisan users:finalize-deletions

# Auth napló törlés egyedi retention-nel
php artisan auth:prune-events --days=180
```

## 6. Deployment folyamat

### 6.1 Első telepítés

1. **Adatbázis létrehozása** CPanel-ben (MySQL Databases)
2. **Laravel alkalmazás feltöltése** (FTP/SFTP vagy Git):
   ```bash
   # Lokálisan
   composer install --no-dev --optimize-autoloader
   # Feltöltés FTP-vel a /home/username/previse-api/ mappába
   ```
3. **.env fájl konfigurálása** a szerveren
4. **Migrációk futtatása** (SSH vagy CPanel Terminal):
   ```bash
   cd /home/username/previse-api
   php artisan migrate --force
   php artisan db:seed --force
   ```
5. **Storage symlink**:
   ```bash
   ln -s /home/username/previse-api/storage/app/public /home/username/public_html/storage
   ```
6. **Vue.js build feltöltése** a `public_html/`-be:
   ```bash
   # Lokálisan
   npm run build
   # A dist/ tartalmát feltölteni a public_html/-be
   ```
7. **Kulcs generálás**:
   ```bash
   php artisan key:generate
   ```
8. **Cache optimalizáció**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
9. **Cron beállítása** CPanel-ben

### 6.2 Frissítés (deployment)

```bash
# 1. Karbantartás mód bekapcsolása
php artisan down --secret="maintenance-bypass-token"

# 2. Kód frissítése (Git pull vagy FTP feltöltés)
git pull origin main  # ha SSH elérhető
# vagy FTP-vel felülírni a fájlokat

# 3. Composer update (ha változott a composer.lock)
composer install --no-dev --optimize-autoloader

# 4. Migrációk
php artisan migrate --force

# 5. Cache frissítés
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Queue újraindítás
php artisan queue:restart

# 7. Karbantartás mód kikapcsolása
php artisan up
```

### 6.3 Vue.js frontend frissítés

```bash
# Lokálisan
cd previse-web
npm run build

# A dist/ tartalmát feltölteni a public_html/-be (FTP/SFTP)
# (index.html, assets/ mappa)
```

## 7. Biztonsági beállítások

### 7.1 SSL/TLS

- CPanel → SSL/TLS → Let's Encrypt certifikát
- Force HTTPS a .htaccess-ben

### 7.2 Fájl jogosultságok

```
/home/username/previse-api/           755
/home/username/previse-api/storage/   775 (írható)
/home/username/previse-api/bootstrap/cache/ 775
/home/username/previse-api/.env       600 (csak olvasható a tulajdonosnak)
/home/username/public_html/           755
```

### 7.3 .htaccess védelem

A `previse-api/` könyvtár nem érhető el közvetlenül a webről, mert a `public_html/`-en kívül van.

### 7.4 Adatbázis

- Erős jelszó a MySQL felhasználónak
- Csak localhost hozzáférés (nincs távoli MySQL)
- Rendszeres mentés (CPanel Backup vagy automatikus)

## 8. Mentés és visszaállítás

### 8.1 Automatikus mentés

CPanel Backup beállítás vagy egyéni script:

```bash
# Adatbázis mentés (cron, naponta 02:00)
0 2 * * * mysqldump -u previse_user -p'password' previse_db | gzip > /home/username/backups/db_$(date +\%Y\%m\%d).sql.gz

# Feltöltött fájlok mentése (hetente)
0 3 * * 0 tar -czf /home/username/backups/uploads_$(date +\%Y\%m\%d).tar.gz /home/username/previse-api/storage/app/uploads/

# Régi mentések törlése (30 napnál régebbi)
0 4 * * * find /home/username/backups/ -name "*.gz" -mtime +30 -delete
```

### 8.2 Visszaállítás

```bash
# Adatbázis visszaállítás
gunzip < /home/username/backups/db_20240315.sql.gz | mysql -u previse_user -p previse_db

# Fájlok visszaállítása
tar -xzf /home/username/backups/uploads_20240315.tar.gz -C /
```

## 9. Monitoring

### 9.1 Alkalmazás monitoring

- Laravel log fájlok: `storage/logs/laravel-YYYY-MM-DD.log`
- Log szint: `error` (production-ben)
- Napi log rotáció, 30 napos megőrzés

### 9.2 Teljesítmény monitoring

- CPanel → Metrics → CPU, Memory, I/O használat
- Laravel Telescope (csak fejlesztői környezetben)
- Slow query log engedélyezése MySQL-ben

### 9.3 Uptime monitoring

- Külső szolgáltatás ajánlott (UptimeRobot, Pingdom)
- Health check endpointok:
  - `GET /api/v1/health` → 200 OK alap státusszal (publikus, gyors)
  - `GET /api/v1/health/details` → részletes JSON: DB connection, mail/queue/cache/session driver, app env (publikus, de ne tegye közzé érzékeny adatot — csak driver neveket)

## 10. Auth modul (M3–M11) telepítési ellenőrzőlista

Az auth-réteg élesítéskor ezeket nézd át:

### 10.1 Email küldés (M3, M4, M5, M6, M7)
- [ ] `MAIL_MAILER=smtp` beállítva, host/port/credentials kitöltve
- [ ] `MAIL_FROM_ADDRESS` ugyanazon domain alól, mint az `APP_URL`
- [ ] **Tesztküldés**: `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('te@email.hu')->subject('Test'));`
- [ ] **Queue worker fut**: scheduler `queue:work` percenként, vagy supervisord-zett worker
- [ ] SPF / DKIM / DMARC DNS rekordok beállítva a `MAIL_FROM_ADDRESS` domainjéhez
- [ ] HU + EN email-sablonok mindkét locale-ban renderelnek (Mailable `->locale()` támogatott)

### 10.2 Frontend (M9 lokalizáció + cookie)
- [ ] `SANCTUM_STATEFUL_DOMAINS` a frontend domain-jét tartalmazza
- [ ] `SESSION_DOMAIN` vezető ponttal (pl. `.example.hu`), ha aldomainen is megy az SPA
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, HTTPS aktív
- [ ] Vue build: `npm run build` → `dist/` feltöltve a `previse-web/dist/` mappába
- [ ] Frontend `vite.config.ts` `base` opció átállítva, ha alkönyvtárból megy (pl. `/app/`)

### 10.3 Auth időzítések
- [ ] `AUTH_INVITATION_EXPIRES_DAYS=7` (alap)
- [ ] `AUTH_EMAIL_CHANGE_EXPIRES_MINUTES=60`
- [ ] `AUTH_ACCOUNT_DELETION_GRACE_DAYS=30`
- [ ] `AUTH_EVENTS_RETENTION_DAYS=90`

### 10.4 Scheduler (M7, M8)
- [ ] CPanel cron beállítva: `* * * * * cd /home/.../previse-api && php artisan schedule:run >/dev/null 2>&1`
- [ ] **Verifikáció**: `php artisan schedule:list` mutatja a 3 feladatot (`users:finalize-deletions` 03:00, `auth:prune-events` 03:30, `queue:work` percenként)
- [ ] Próba: `php artisan users:finalize-deletions --dry-run` 0 rekordot listáz friss telepítésen
- [ ] Próba: `php artisan auth:prune-events --dry-run` 0 rekordot listáz

### 10.5 Bevezető user-hozzáférés
- [ ] Migráció + seed lefuttatva (`php artisan migrate --seed`)
- [ ] Kapsz default super-admin login-t: `admin@previse.hu` / `Admin123!`
- [ ] **AZONNALI MŰVELET**: jelentkezz be, vidd a Profil → Biztonság fülre, módosítsd a jelszót
- [ ] Engedélyezd a 2FA-t a super-admin fiókon
- [ ] Mentsd el a recovery kódokat biztonságos helyre

### 10.6 Production-only optimization
Telepítés végén:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

(Fejlesztőkörnyezetben NE használd, mert akkor minden config/route módosítás után újra kell futtatni.)

Bármi config/.env változtatás után:
```bash
php artisan optimize:clear
php artisan config:cache
```

---

## 11. Skálázás (jövőbeli)

Ha a shared hosting korlátai elérkeznek:

| Korlát | Megoldás |
|--------|----------|
| CPU/RAM elfogyott | VPS-re váltás (DigitalOcean, Hetzner) |
| Sok fájl | S3-kompatibilis object storage (MinIO, AWS S3) |
| Lassú keresés | Meilisearch telepítése |
| Valós idejű értesítés | Laravel Reverb vagy Pusher (WebSocket) |
| Sok queue job | Redis + Horizon (Supervisor-ral) |
| Nagy forgalom | Load balancer + több szerver |
