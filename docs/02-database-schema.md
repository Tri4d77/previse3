# 02 - Adatbázis-séma

> ⚠️ **Frissítve az M1–M2.5 fázisokban.** A core user/organization réteg a [11-user-membership.md](11-user-membership.md) dokumentum szerinti **User–Membership–Organization** modellre épül. Az alábbi részek frissítve:
> - `organizations` tábla: `status`, `terminated_at`, `parent_id`, `type` oszlopok
> - `users` tábla: a korábbi `organization_id` / `role_id` / `group_id` mezők **megszűntek**, átkerültek a `memberships` táblába
> - Új táblák: `memberships`, `invitations`
> - `personal_access_tokens` kiegészítve `current_membership_id` és `context_organization_id` oszlopokkal (super-admin impersonation)
> - A `user_groups` pivot átnevezve `group_membership`-re (membership_id alapon)
> - `user_settings`: `default_page` helyett `default_organization_id` + `lockscreen_timeout_minutes`

## 1. Áttekintés

Az adatbázis MySQL 8.x / MariaDB 10.6+ motorra épül, Laravel Eloquent ORM-mel. A séma modulonként van szervezve, minden tábla Laravel migration fájlként kerül implementálásra.

### Konvenciók
- Táblanevek: angol, többes szám, snake_case (Laravel konvenció)
- Elsődleges kulcs: `id` (unsigned BIGINT, auto-increment)
- Foreign key-ek: `{table_singular}_id` formátum
- Timestamps: `created_at`, `updated_at` (Laravel automatikus)
- Soft delete: `deleted_at` (ahol szükséges)
- Multi-tenant: `organization_id` a fő entitásokon

---

## 2. Core táblák

### 2.1 organizations

Szervezetek hierarchikus struktúrában (Platform → Subscriber → Client), multi-tenant elkülönítés alapja.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| parent_id | BIGINT FK NULL | Szülő szervezet (hierarchia: Platform gyökér; Subscriber szülője Platform; Client szülője Subscriber) |
| type | ENUM('platform','subscriber','client') | Szervezet típus |
| name | VARCHAR(255) | Szervezet neve |
| slug | VARCHAR(100) UNIQUE | URL-barát azonosító |
| address | TEXT NULL | Cím |
| city | VARCHAR(100) NULL | Város |
| zip_code | VARCHAR(20) NULL | Irányítószám |
| phone | VARCHAR(50) NULL | Telefon |
| email | VARCHAR(255) NULL | Kapcsolattartó email |
| tax_number | VARCHAR(50) NULL | Adószám |
| logo_path | VARCHAR(500) NULL | Logo fájl elérési útja |
| settings | JSON NULL | Szervezet-specifikus beállítások |
| status | ENUM('active','inactive','terminated') DEFAULT 'active' | Szervezet státusza |
| is_active | BOOLEAN DEFAULT true | Aktív-e (redundáns, a status-szal szinkronban tartva) |
| terminated_at | TIMESTAMP NULL | Megszűnés időpontja (csak terminated státusznál) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

**Indexek**: parent_id, type, is_active, status

**Megjegyzés**: a `status` és `is_active` mezőket a `Organization::setStatus()` metódus szinkronban tartja (csak `active` esetén `is_active = true`).

### 2.2 users

Globális felhasználók (személyek). Szervezeti tagság a külön `memberships` táblában.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| name | VARCHAR(255) | Teljes név |
| email | VARCHAR(255) UNIQUE | Email cím (globálisan egyedi) |
| password | VARCHAR(255) NULL | Jelszó hash (NULL ha még nem fogadta el a meghívót) |
| avatar_path | VARCHAR(500) NULL | Profilkép elérési útja |
| phone | VARCHAR(50) NULL | Telefonszám |
| is_super_admin | BOOLEAN DEFAULT false | Szuper-admin jelölő (Platform adminja) |
| is_active | BOOLEAN DEFAULT true | Globális aktivitás (false = teljes kizárás) |
| email_verified_at | TIMESTAMP NULL | Email megerősítés |
| last_login_at | TIMESTAMP NULL | Utolsó bejelentkezés |
| remember_token | VARCHAR(100) NULL | Emlékeztető token |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

> **A szerepkör, csoport, szervezeti kötés és tagság-aktivitás a `memberships` táblában van.** Egy user több szervezetben is tag lehet, membershipenként külön role-lal. Lásd [11-user-membership.md](11-user-membership.md).

**M5 (2FA) és M6 (email-change), M7 (fiók-törlés) fázisokban ide kerül még:**
- `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` (M5)
- `pending_email`, `email_change_token`, `email_change_sent_at` (M6)
- `scheduled_deletion_at` (M7)

### 2.3 memberships

Felhasználói tagságok szervezetekben. Egy user–szervezet páros csak egyszer szerepelhet (unique).

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| user_id | BIGINT FK | Felhasználó |
| organization_id | BIGINT FK | Szervezet |
| role_id | BIGINT FK | Szerepkör (adott szervezet saját role-jai közül) |
| is_default | BOOLEAN DEFAULT false | Alapértelmezett tagság (bejelentkezés után ezt használja) |
| is_active | BOOLEAN DEFAULT true | Tagság-szintű aktivitás (org admin deaktiválhatja) |
| invitation_token | VARCHAR(100) NULL | Függőben lévő meghívó (NULL ha már elfogadta) |
| invitation_sent_at | TIMESTAMP NULL | Meghívó kiküldés ideje |
| joined_at | TIMESTAMP NULL | Tagság elfogadásának ideje |
| last_accessed_at | TIMESTAMP NULL | Utolsó aktív szervezet-használat |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete (kilépés szervezetből) |

**Unique**: (user_id, organization_id) – az aktív (nem soft-deleted) sorokra

**Indexek**: user_id, organization_id, role_id, is_default, is_active

### 2.4 invitations

Kiküldött meghívók — a `memberships.invitation_token` pár, külön táblában csak a kibővített audithoz (új user meghívás információi).

*Megjegyzés*: az aktuális implementáció a `memberships` tábla `invitation_token` + `invitation_sent_at` mezőit használja, így külön `invitations` tábla nem feltétlenül szükséges. Ha később auditálási igény felmerül (ki, mikor, milyen email-lel hívott meg, ha ugyanaz a user többször meghívást kapott), akkor lesz dedikált tábla.

### 2.5 user_settings

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| user_id | BIGINT FK UNIQUE | Felhasználó |
| theme | VARCHAR(50) DEFAULT 'light' | Téma (light/dark) |
| color_scheme | VARCHAR(50) DEFAULT 'teal' | Színséma (alapértelmezett: teal) |
| locale | VARCHAR(10) DEFAULT 'hu' | Nyelv (hu/en) |
| timezone | VARCHAR(50) DEFAULT 'Europe/Budapest' | Időzóna |
| items_per_page | INT DEFAULT 25 | Lista elemszám |
| default_organization_id | BIGINT FK NULL | Alapértelmezett szervezet bejelentkezés után (több tagság esetén) |
| lockscreen_timeout_minutes | SMALLINT DEFAULT 30 | Lockscreen inaktivitási időkorlát (perc) |
| notification_email | BOOLEAN DEFAULT true | Email értesítés |
| notification_push | BOOLEAN DEFAULT true | Push értesítés |
| notification_sound | BOOLEAN DEFAULT true | Hangjelzés |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 2.6 roles

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(100) | Szerepkör neve (pl. admin, diszpécser) |
| slug | VARCHAR(100) | Gépi név |
| description | TEXT NULL | Leírás |
| is_system | BOOLEAN DEFAULT false | Rendszer szerepkör (nem törölhető) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

**Unique**: (organization_id, slug)

### 2.7 permissions

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| module | VARCHAR(100) | Modul neve (tickets, tasks, stb.) |
| action | VARCHAR(100) | Művelet (create, read, update, delete, assign, stb.) |
| description | TEXT NULL | Leírás |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

**Unique**: (module, action)

### 2.8 role_permission (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| role_id | BIGINT FK | Szerepkör |
| permission_id | BIGINT FK | Engedély |

**PK**: (role_id, permission_id)

### 2.9 groups

Felhasználói csoportok (pl. műszaki csapat, vezetőség).

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Csoport neve |
| description | TEXT NULL | Leírás |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 2.10 group_membership (pivot)

A csoport–tagság kapcsolat **membership alapú**: ugyanaz a user különböző szervezetekben különböző csoportba tartozhat.

| Mező | Típus | Leírás |
|------|-------|--------|
| group_id | BIGINT FK | Csoport |
| membership_id | BIGINT FK | Tagság (memberships.id) |

**PK**: (group_id, membership_id)

### 2.11 personal_access_tokens (Sanctum — kibővítve)

Laravel Sanctum tábla, kiegészítve a multi-membership és super-admin impersonation támogatásához.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| tokenable_type | VARCHAR(255) | Sanctum alap |
| tokenable_id | BIGINT | Sanctum alap |
| name | VARCHAR(255) | Token neve (eszköz/kliens) |
| token | VARCHAR(64) UNIQUE | Hash token |
| abilities | TEXT NULL | Sanctum képességek |
| current_membership_id | BIGINT FK NULL | **Melyik tagság kontextusában aktív a token** (aktuális szervezet) |
| context_organization_id | BIGINT FK NULL | **Super-admin impersonation**: ha a token a Platform-admin azon sessionjéhez tartozik, amelyikben egy másik szervezetbe „belépett" – itt tároljuk a cél-szervezet id-ját |
| last_used_at | TIMESTAMP NULL | Utolsó használat |
| expires_at | TIMESTAMP NULL | Lejárat |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

**Megjegyzés**: Ha `context_organization_id` kitöltve, a tokennel a backend a cél-szervezet kontextusát alkalmazza (mint impersonation). Ez **külön token**, nem módosítja az eredeti tokent. Kilépéskor az impersonation-tokent revokáljuk.

**M4-ben** (session- és eszközkezelés) ide kerül még: `ip_address`, `user_agent`.

### 2.12 fcm_tokens

Push értesítésekhez szükséges Firebase token-ek.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| user_id | BIGINT FK | Felhasználó |
| token | VARCHAR(500) | FCM token |
| device_type | ENUM('web','android','ios') | Eszköz típus |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

---

## 3. Bejelentés (Ticket) táblák

### 3.1 tickets

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| reference_number | VARCHAR(50) UNIQUE | Hivatkozási szám (PV-2024-00001) |
| title | VARCHAR(255) | Cím |
| description | TEXT | Leírás |
| category_id | BIGINT FK | Kategória |
| status_id | BIGINT FK | Státusz |
| priority | ENUM('low','medium','high','critical') DEFAULT 'medium' | Prioritás |
| reporter_id | BIGINT FK | Bejelentő felhasználó |
| assignee_id | BIGINT FK NULL | Felelős felhasználó |
| location_id | BIGINT FK NULL | Helyszín (épület) |
| floor_id | BIGINT FK NULL | Szint |
| room_id | BIGINT FK NULL | Helyiség |
| asset_id | BIGINT FK NULL | Eszköz |
| sla_deadline | TIMESTAMP NULL | SLA határidő |
| sla_warning_sent | BOOLEAN DEFAULT false | SLA figyelmeztetés elküldve |
| resolved_at | TIMESTAMP NULL | Megoldás ideje |
| closed_at | TIMESTAMP NULL | Lezárás ideje |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

**Indexek**: organization_id, category_id, status_id, priority, reporter_id, assignee_id, location_id, asset_id, created_at

### 3.2 ticket_categories

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Kategória neve |
| color | VARCHAR(7) NULL | Szín kód (#hex) |
| icon | VARCHAR(50) NULL | Ikon neve |
| sort_order | INT DEFAULT 0 | Sorrend |
| is_active | BOOLEAN DEFAULT true | Aktív-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 3.3 ticket_statuses

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Státusz neve |
| slug | VARCHAR(100) | Gépi név |
| color | VARCHAR(7) NULL | Szín kód |
| is_default | BOOLEAN DEFAULT false | Alapértelmezett (új bejelentéseknél) |
| is_closed | BOOLEAN DEFAULT false | Lezárt státusznak számít-e |
| sort_order | INT DEFAULT 0 | Sorrend a workflow-ban |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 3.4 ticket_reactions

Intézkedések/reakciók típusai bejelentésekhez.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Reakció neve |
| sort_order | INT DEFAULT 0 | Sorrend |
| is_active | BOOLEAN DEFAULT true | Aktív-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 3.5 ticket_reaction (pivot - ticket és reakció kapcsolat)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| ticket_id | BIGINT FK | Bejelentés |
| reaction_id | BIGINT FK | Reakció típus |
| user_id | BIGINT FK | Hozzárendelő |
| note | TEXT NULL | Megjegyzés |
| created_at | TIMESTAMP | Létrehozás |

### 3.6 comments (polimorf - több entitáshoz használható)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| commentable_type | VARCHAR(255) | Entitás típus (App\Models\Ticket, stb.) |
| commentable_id | BIGINT | Entitás ID |
| user_id | BIGINT FK | Szerző |
| body | TEXT | Komment szöveg |
| is_internal | BOOLEAN DEFAULT false | Belső jegyzet-e (csak belső felhasználók látják) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

**Indexek**: (commentable_type, commentable_id), user_id

### 3.7 timelines (polimorf - audit trail)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| timelineable_type | VARCHAR(255) | Entitás típus |
| timelineable_id | BIGINT | Entitás ID |
| user_id | BIGINT FK NULL | Végrehajtó (NULL = rendszer) |
| action | VARCHAR(100) | Művelet (created, status_changed, assigned, stb.) |
| description | TEXT | Leírás |
| old_value | JSON NULL | Régi érték |
| new_value | JSON NULL | Új érték |
| created_at | TIMESTAMP | Időpont |

**Indexek**: (timelineable_type, timelineable_id), user_id, created_at

### 3.8 attachments (polimorf)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| attachable_type | VARCHAR(255) | Entitás típus |
| attachable_id | BIGINT | Entitás ID |
| user_id | BIGINT FK | Feltöltő |
| original_name | VARCHAR(255) | Eredeti fájlnév |
| stored_name | VARCHAR(255) | Tárolt fájlnév |
| path | VARCHAR(500) | Elérési út |
| mime_type | VARCHAR(100) | MIME típus |
| size | BIGINT | Méret (bytes) |
| thumbnail_path | VARCHAR(500) NULL | Miniatűr (képeknél) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 3.9 followers (polimorf)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| followable_type | VARCHAR(255) | Entitás típus |
| followable_id | BIGINT | Entitás ID |
| user_id | BIGINT FK | Követő felhasználó |
| created_at | TIMESTAMP | Követés kezdete |

**Unique**: (followable_type, followable_id, user_id)

### 3.10 read_status (polimorf - olvasottság-követés)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| readable_type | VARCHAR(255) | Entitás típus |
| readable_id | BIGINT | Entitás ID |
| user_id | BIGINT FK | Felhasználó |
| read_at | TIMESTAMP | Olvasás ideje |

**Unique**: (readable_type, readable_id, user_id)

### 3.11 sla_configs

SLA szabályok konfigurációja.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| category_id | BIGINT FK NULL | Kategória (NULL = minden) |
| priority | ENUM('low','medium','high','critical') NULL | Prioritás (NULL = minden) |
| response_hours | INT NULL | Válaszidő (óra) |
| resolution_hours | INT NULL | Megoldási idő (óra) |
| warning_percent | INT DEFAULT 80 | Figyelmeztetés küldése ennyi %-nál |
| escalation_user_id | BIGINT FK NULL | Eszkalációs felhasználó |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

---

## 4. Feladat (Task) táblák

### 4.1 tasks

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| title | VARCHAR(255) | Feladat neve |
| description | TEXT NULL | Leírás |
| status | ENUM('new','in_progress','completed','postponed','cancelled') DEFAULT 'new' | Státusz |
| priority | ENUM('low','medium','high','critical') DEFAULT 'medium' | Prioritás |
| creator_id | BIGINT FK | Létrehozó |
| project_id | BIGINT FK NULL | Projekt (opcionális) |
| location_id | BIGINT FK NULL | Helyszín |
| asset_id | BIGINT FK NULL | Eszköz |
| due_date | DATE NULL | Határidő |
| started_at | TIMESTAMP NULL | Kezdés ideje |
| completed_at | TIMESTAMP NULL | Befejezés ideje |
| is_starred | BOOLEAN DEFAULT false | Csillagozott |
| estimated_hours | DECIMAL(8,2) NULL | Becsült munkaidő |
| actual_hours | DECIMAL(8,2) NULL | Tényleges munkaidő |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

### 4.2 task_user (pivot - feladat kiosztás)

| Mező | Típus | Leírás |
|------|-------|--------|
| task_id | BIGINT FK | Feladat |
| user_id | BIGINT FK | Felelős felhasználó |
| assigned_at | TIMESTAMP | Kiosztás ideje |

**PK**: (task_id, user_id)

### 4.3 recurring_tasks

Ismétlődő feladat ütemezés.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| task_id | BIGINT FK | Alap feladat (sablon) |
| frequency | ENUM('daily','weekly','monthly','custom') | Gyakoriság |
| interval_value | INT DEFAULT 1 | Intervallum szám |
| day_of_week | TINYINT NULL | Hét napja (1=hétfő, 7=vasárnap) |
| day_of_month | TINYINT NULL | Hónap napja (1-31) |
| next_due_date | DATE | Következő esedékesség |
| last_generated_at | TIMESTAMP NULL | Utolsó generálás |
| is_active | BOOLEAN DEFAULT true | Aktív-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

---

## 5. Projekt (Project) táblák

### 5.1 projects

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| title | VARCHAR(255) | Projekt neve |
| description | TEXT NULL | Leírás |
| status | ENUM('new','active','suspended','completed','cancelled') DEFAULT 'new' | Státusz |
| owner_id | BIGINT FK | Projekt tulajdonos |
| location_id | BIGINT FK NULL | Helyszín |
| start_date | DATE NULL | Tervezett kezdés |
| end_date | DATE NULL | Tervezett befejezés |
| actual_start | DATE NULL | Tényleges kezdés |
| actual_end | DATE NULL | Tényleges befejezés |
| progress | TINYINT DEFAULT 0 | Haladás százalék (0-100) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

### 5.2 project_categories

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Kategória |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 5.3 project_category (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| project_id | BIGINT FK | Projekt |
| project_category_id | BIGINT FK | Kategória |

### 5.4 project_labels

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(100) | Címke neve |
| color | VARCHAR(7) NULL | Szín |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 5.5 project_label (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| project_id | BIGINT FK | Projekt |
| project_label_id | BIGINT FK | Címke |

### 5.6 project_teams

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| project_id | BIGINT FK | Projekt |
| name | VARCHAR(255) | Csapat neve |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 5.7 project_team_members (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| project_team_id | BIGINT FK | Csapat |
| user_id | BIGINT FK | Tag |
| role | VARCHAR(50) DEFAULT 'member' | Szerep a csapatban |

**PK**: (project_team_id, user_id)

### 5.8 milestones

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| project_id | BIGINT FK | Projekt |
| title | VARCHAR(255) | Mérföldkő neve |
| description | TEXT NULL | Leírás |
| due_date | DATE NULL | Határidő |
| completed_at | TIMESTAMP NULL | Teljesítés dátuma |
| sort_order | INT DEFAULT 0 | Sorrend |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

---

## 6. Hibajegy (Issue) táblák

### 6.1 issues

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| reference_number | VARCHAR(50) UNIQUE | Hivatkozási szám |
| title | VARCHAR(255) | Hiba megnevezés |
| description | TEXT | Leírás |
| severity | ENUM('minor','major','critical','blocker') DEFAULT 'major' | Súlyosság |
| status | ENUM('new','investigating','fixing','testing','resolved','closed') DEFAULT 'new' | Státusz |
| reporter_id | BIGINT FK | Bejelentő |
| assignee_id | BIGINT FK NULL | Felelős |
| location_id | BIGINT FK NULL | Helyszín |
| asset_id | BIGINT FK NULL | Eszköz |
| ticket_id | BIGINT FK NULL | Kapcsolódó bejelentés |
| resolution | TEXT NULL | Megoldás leírása |
| resolved_at | TIMESTAMP NULL | Megoldás ideje |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

---

## 7. Javaslat (Suggestion) táblák

### 7.1 suggestions

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| title | VARCHAR(255) | Javaslat tárgya |
| description | TEXT | Leírás |
| status | ENUM('new','under_review','accepted','rejected','implemented') DEFAULT 'new' | Státusz |
| author_id | BIGINT FK | Beküldő |
| reviewed_by | BIGINT FK NULL | Elbíráló |
| reviewed_at | TIMESTAMP NULL | Elbírálás ideje |
| review_note | TEXT NULL | Elbírálás megjegyzés |
| votes_count | INT DEFAULT 0 | Szavazatok száma (denormalizált) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 7.2 suggestion_votes

| Mező | Típus | Leírás |
|------|-------|--------|
| suggestion_id | BIGINT FK | Javaslat |
| user_id | BIGINT FK | Szavazó |
| created_at | TIMESTAMP | Szavazás ideje |

**PK**: (suggestion_id, user_id)

---

## 8. Dokumentumtár (Document) táblák

### 8.1 document_folders

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| parent_id | BIGINT FK NULL | Szülő mappa (NULL = gyökér) |
| name | VARCHAR(255) | Mappa neve |
| location_id | BIGINT FK NULL | Helyszínhez kötés |
| project_id | BIGINT FK NULL | Projekthez kötés |
| created_by | BIGINT FK | Létrehozó |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 8.2 documents

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| folder_id | BIGINT FK NULL | Mappa |
| title | VARCHAR(255) | Dokumentum neve |
| description | TEXT NULL | Leírás |
| type | VARCHAR(100) NULL | Típus (szerződés, műszaki, jegyzőkönyv, garancia, stb.) |
| current_version | INT DEFAULT 1 | Aktuális verzió szám |
| uploaded_by | BIGINT FK | Feltöltő |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

### 8.3 document_versions

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| document_id | BIGINT FK | Dokumentum |
| version_number | INT | Verzió szám |
| file_path | VARCHAR(500) | Fájl elérési út |
| file_name | VARCHAR(255) | Eredeti fájlnév |
| mime_type | VARCHAR(100) | MIME típus |
| size | BIGINT | Méret (bytes) |
| uploaded_by | BIGINT FK | Feltöltő |
| changelog | TEXT NULL | Változások leírása |
| created_at | TIMESTAMP | Feltöltés ideje |

### 8.4 document_tags

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(100) | Címke |
| created_at | TIMESTAMP | Létrehozás |

### 8.5 document_tag (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| document_id | BIGINT FK | Dokumentum |
| document_tag_id | BIGINT FK | Címke |

---

## 9. Helyszín (Location) táblák

### 9.1 locations (Épületek)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Épület neve |
| address | TEXT NULL | Cím |
| city | VARCHAR(100) NULL | Város |
| zip_code | VARCHAR(20) NULL | Irányítószám |
| latitude | DECIMAL(10,8) NULL | GPS szélesség |
| longitude | DECIMAL(11,8) NULL | GPS hosszúság |
| type | VARCHAR(100) NULL | Épület típus (iroda, bevásárlóközpont, lakóház, stb.) |
| description | TEXT NULL | Leírás |
| contact_name | VARCHAR(255) NULL | Kapcsolattartó neve |
| contact_phone | VARCHAR(50) NULL | Kapcsolattartó telefon |
| contact_email | VARCHAR(255) NULL | Kapcsolattartó email |
| is_active | BOOLEAN DEFAULT true | Aktív-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 9.2 floors (Szintek)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| location_id | BIGINT FK | Épület |
| name | VARCHAR(100) | Szint neve (pl. Földszint, 1. emelet, Pince) |
| level | INT | Szint szám (rendezéshez) |
| description | TEXT NULL | Leírás |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 9.3 rooms (Helyiségek)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| floor_id | BIGINT FK | Szint |
| name | VARCHAR(255) | Helyiség neve |
| number | VARCHAR(50) NULL | Helyiségszám |
| type | VARCHAR(100) NULL | Típus (iroda, raktár, folyosó, mosdó, stb.) |
| area_sqm | DECIMAL(10,2) NULL | Alapterület (m2) |
| description | TEXT NULL | Leírás |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

---

## 10. Eszköz (Asset) táblák

### 10.1 assets

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Eszköz neve |
| asset_type_id | BIGINT FK | Eszköz típus |
| location_id | BIGINT FK NULL | Helyszín |
| floor_id | BIGINT FK NULL | Szint |
| room_id | BIGINT FK NULL | Helyiség |
| manufacturer | VARCHAR(255) NULL | Gyártó |
| model | VARCHAR(255) NULL | Modell |
| serial_number | VARCHAR(255) NULL | Sorozatszám |
| inventory_number | VARCHAR(100) NULL | Leltári szám |
| status | ENUM('operational','faulty','maintenance','decommissioned') DEFAULT 'operational' | Állapot |
| installation_date | DATE NULL | Üzembe helyezés |
| warranty_expiry | DATE NULL | Garancia lejárata |
| qr_code | VARCHAR(100) UNIQUE NULL | QR kód azonosító |
| notes | TEXT NULL | Megjegyzések |
| metadata | JSON NULL | Típus-specifikus adatok (pl. lift kapacitás, klíma teljesítmény) |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |
| deleted_at | TIMESTAMP NULL | Soft delete |

### 10.2 asset_types

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Típus neve (lift, mozgólépcső, klíma, tűzjelző, stb.) |
| icon | VARCHAR(50) NULL | Ikon |
| metadata_schema | JSON NULL | Típus-specifikus mezők sémája |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 10.3 asset_status_changes

Eszköz állapotváltozás történet.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| asset_id | BIGINT FK | Eszköz |
| old_status | VARCHAR(50) | Régi állapot |
| new_status | VARCHAR(50) | Új állapot |
| changed_by | BIGINT FK | Módosító |
| note | TEXT NULL | Megjegyzés |
| created_at | TIMESTAMP | Változás ideje |

---

## 11. Karbantartás (Maintenance) táblák

### 11.1 maintenance_schedules

Tervezett karbantartási ütemterv.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| asset_id | BIGINT FK NULL | Eszköz (NULL = helyszín szintű) |
| location_id | BIGINT FK NULL | Helyszín |
| title | VARCHAR(255) | Karbantartás neve |
| description | TEXT NULL | Leírás |
| frequency | ENUM('daily','weekly','monthly','quarterly','semi_annual','annual','custom') | Gyakoriság |
| interval_days | INT NULL | Egyéni intervallum (napok) |
| next_due_date | DATE | Következő esedékesség |
| last_performed_at | TIMESTAMP NULL | Utolsó elvégzés |
| assigned_to | BIGINT FK NULL | Felelős |
| is_active | BOOLEAN DEFAULT true | Aktív-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 11.2 maintenance_logs

Elvégzett karbantartási munkák naplója.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| schedule_id | BIGINT FK NULL | Ütemterv (NULL = ad-hoc karbantartás) |
| asset_id | BIGINT FK NULL | Eszköz |
| location_id | BIGINT FK NULL | Helyszín |
| title | VARCHAR(255) | Munka megnevezése |
| description | TEXT NULL | Elvégzett munkák leírása |
| performed_by | BIGINT FK | Elvégző |
| performed_at | TIMESTAMP | Elvégzés ideje |
| duration_minutes | INT NULL | Időtartam (perc) |
| cost | DECIMAL(12,2) NULL | Költség |
| parts_used | TEXT NULL | Felhasznált alkatrészek |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

---

## 12. Szerződés (Contract) táblák

### 12.1 contractors

Alvállalkozók/karbantartó cégek.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| name | VARCHAR(255) | Cég neve |
| tax_number | VARCHAR(50) NULL | Adószám |
| address | TEXT NULL | Cím |
| contact_name | VARCHAR(255) NULL | Kapcsolattartó |
| contact_phone | VARCHAR(50) NULL | Telefon |
| contact_email | VARCHAR(255) NULL | Email |
| specialization | VARCHAR(255) NULL | Szakterület |
| notes | TEXT NULL | Megjegyzések |
| is_active | BOOLEAN DEFAULT true | Aktív-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 12.2 contracts

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| contractor_id | BIGINT FK | Alvállalkozó |
| title | VARCHAR(255) | Szerződés megnevezése |
| contract_number | VARCHAR(100) NULL | Szerződés szám |
| type | VARCHAR(100) NULL | Típus (karbantartás, felújítás, szolgáltatás, stb.) |
| description | TEXT NULL | Leírás |
| start_date | DATE | Kezdés |
| end_date | DATE NULL | Lejárat |
| value | DECIMAL(14,2) NULL | Szerződés értéke |
| currency | VARCHAR(3) DEFAULT 'HUF' | Pénznem |
| auto_renew | BOOLEAN DEFAULT false | Automatikus megújítás |
| warning_days | INT DEFAULT 30 | Lejárat előtti figyelmeztetés (nap) |
| status | ENUM('draft','active','expired','terminated') DEFAULT 'draft' | Státusz |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 12.3 contract_location (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| contract_id | BIGINT FK | Szerződés |
| location_id | BIGINT FK | Helyszín |

### 12.4 contract_asset (pivot)

| Mező | Típus | Leírás |
|------|-------|--------|
| contract_id | BIGINT FK | Szerződés |
| asset_id | BIGINT FK | Eszköz |

---

## 13. Értesítés és üzenet táblák

### 13.1 notifications (Laravel notifications tábla)

| Mező | Típus | Leírás |
|------|-------|--------|
| id | CHAR(36) PK | UUID |
| type | VARCHAR(255) | Notification osztály neve |
| notifiable_type | VARCHAR(255) | Értesített entitás típus |
| notifiable_id | BIGINT | Értesített entitás ID |
| data | JSON | Értesítés adatok |
| read_at | TIMESTAMP NULL | Olvasás ideje |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 13.2 messages

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| sender_id | BIGINT FK | Küldő |
| subject | VARCHAR(255) | Tárgy |
| body | TEXT | Üzenet szöveg |
| messageable_type | VARCHAR(255) NULL | Kapcsolódó entitás típus |
| messageable_id | BIGINT NULL | Kapcsolódó entitás ID |
| created_at | TIMESTAMP | Küldés ideje |

### 13.3 message_recipients

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| message_id | BIGINT FK | Üzenet |
| user_id | BIGINT FK | Címzett |
| read_at | TIMESTAMP NULL | Olvasás ideje |

---

## 14. Egyéb táblák

### 14.1 news

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| title | VARCHAR(255) | Hír címe |
| body | TEXT | Tartalom |
| author_id | BIGINT FK | Szerző |
| published_at | TIMESTAMP NULL | Publikálás ideje |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 14.2 saved_filters

Mentett szűrők.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| user_id | BIGINT FK | Felhasználó |
| module | VARCHAR(100) | Modul (tickets, tasks, stb.) |
| name | VARCHAR(255) | Szűrő neve |
| filters | JSON | Szűrő paraméterek |
| is_default | BOOLEAN DEFAULT false | Alapértelmezett-e |
| created_at | TIMESTAMP | Létrehozás |
| updated_at | TIMESTAMP | Módosítás |

### 14.3 activity_log

Globális aktivitás napló.

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| user_id | BIGINT FK | Felhasználó |
| action | VARCHAR(100) | Művelet |
| subject_type | VARCHAR(255) | Entitás típus |
| subject_id | BIGINT | Entitás ID |
| description | TEXT | Leírás |
| properties | JSON NULL | Extra adatok |
| created_at | TIMESTAMP | Időpont |

**Indexek**: organization_id, user_id, (subject_type, subject_id), created_at

### 14.4 allowed_domains

Engedélyezett email domain-ek (regisztrációhoz).

| Mező | Típus | Leírás |
|------|-------|--------|
| id | BIGINT PK | Azonosító |
| organization_id | BIGINT FK | Szervezet |
| domain | VARCHAR(255) | Domain (pl. company.hu) |
| created_at | TIMESTAMP | Létrehozás |

---

## 15. Kapcsolati diagram (összefoglaló)

```
users ──► user_settings
  │
  └──► memberships ◄── organizations (hierarchia: parent_id → platform/subscriber/client)
            │
            ├──► roles ──► role_permission ◄── permissions
            │     (org-onként saját role-ok)
            │
            └──► groups ◄── group_membership
                  (org-onként saját csoportok)

personal_access_tokens ──► (current_membership_id, context_organization_id)

organizations
               │
               ├─► tickets ──┬─► ticket_categories
               │             ├─► ticket_statuses
               │             ├─► ticket_reactions
               │             ├─► comments (polimorf)
               │             ├─► timelines (polimorf)
               │             ├─► attachments (polimorf)
               │             ├─► followers (polimorf)
               │             ├─► read_status (polimorf)
               │             └─► sla_configs
               │
               ├─► tasks ────┬─► task_user
               │             └─► recurring_tasks
               │
               ├─► projects ─┬─► project_teams ──► project_team_members
               │             ├─► milestones
               │             ├─► project_categories
               │             └─► project_labels
               │
               ├─► issues
               │
               ├─► suggestions ──► suggestion_votes
               │
               ├─► documents ─┬─► document_versions
               │              ├─► document_folders
               │              └─► document_tags
               │
               ├─► locations ─► floors ─► rooms
               │
               ├─► assets ───┬─► asset_types
               │             └─► asset_status_changes
               │
               ├─► maintenance_schedules ──► maintenance_logs
               │
               ├─► contractors ──► contracts
               │
               ├─► news
               │
               └─► activity_log
```

---

## 16. Indexelési stratégia

Minden foreign key automatikusan indexelve van. Emellett:

- **Fulltext index**: tickets.title + tickets.description, documents.title, assets.name
- **Összetett indexek**: (organization_id, status), (organization_id, created_at), (organization_id, location_id)
- **Polimorf indexek**: (commentable_type, commentable_id), (timelineable_type, timelineable_id), stb.
