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

```env
# Alkalmazás
APP_NAME=Previse
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://app.previse.hu
APP_TIMEZONE=Europe/Budapest
APP_LOCALE=hu

# Adatbázis
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=previse_db
DB_USERNAME=previse_user
DB_PASSWORD=secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Cache (file-based, Redis helyett)
CACHE_DRIVER=file
CACHE_PREFIX=previse

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Queue (database driver, Redis helyett)
QUEUE_CONNECTION=database

# Mail (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.provider.hu
MAIL_PORT=587
MAIL_USERNAME=noreply@previse.hu
MAIL_PASSWORD=mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@previse.hu
MAIL_FROM_NAME="Previse"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=app.previse.hu
SESSION_DOMAIN=.previse.hu
CORS_ALLOWED_ORIGINS=https://app.previse.hu

# Firebase (Push értesítések)
FIREBASE_CREDENTIALS=/home/username/previse-api/storage/app/firebase-credentials.json
FIREBASE_PROJECT_ID=previse-app

# Fájl feltöltés
FILESYSTEM_DISK=local
UPLOAD_MAX_SIZE=67108864

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DAYS=30

# Egyéb
BROADCAST_DRIVER=log
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

| Feladat | Időzítés | Leírás |
|---------|----------|--------|
| SLA ellenőrzés | 5 percenként | SLA határidők ellenőrzése, figyelmeztetések |
| Karbantartás generálás | Naponta 01:00 | Esedékes karbantartási feladatok létrehozása |
| Ismétlődő feladatok | Naponta 01:30 | Ismétlődő feladat-példányok generálása |
| Szerződés lejárat | Naponta 07:00 | Lejáró szerződések figyelmeztetése |
| Cache tisztítás | Hetente | Régi cache fájlok törlése |
| Log rotáció | Naponta | 30 napnál régebbi logok törlése |
| Activity log archiválás | Havonta | 365 napnál régebbi bejegyzések archiválása |

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
- Health check endpoint: `GET /api/v1/health` → 200 OK

## 10. Skálázás (jövőbeli)

Ha a shared hosting korlátai elérkeznek:

| Korlát | Megoldás |
|--------|----------|
| CPU/RAM elfogyott | VPS-re váltás (DigitalOcean, Hetzner) |
| Sok fájl | S3-kompatibilis object storage (MinIO, AWS S3) |
| Lassú keresés | Meilisearch telepítése |
| Valós idejű értesítés | Laravel Reverb vagy Pusher (WebSocket) |
| Sok queue job | Redis + Horizon (Supervisor-ral) |
| Nagy forgalom | Load balancer + több szerver |
