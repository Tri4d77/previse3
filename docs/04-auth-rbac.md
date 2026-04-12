# 04 - Autentikáció és jogosultságkezelés

## 1. Autentikáció

### 1.1 Technológia

**Laravel Sanctum** két módban:
- **SPA mód (web)**: Cookie-alapú session autentikáció CSRF védelemmel
- **Token mód (mobil)**: Bearer token autentikáció

### 1.2 Regisztráció

A regisztráció **meghívó-alapú** (nem nyilvános).

**Folyamat:**
1. Admin létrehozza a felhasználót a rendszerben (név, email, szerepkör)
2. Rendszer generál egy egyedi meghívó tokent
3. Email küldés a felhasználónak a meghívó linkkel
4. Felhasználó a linken beállítja a jelszavát
5. Fiók aktiválódik

**Email domain korlátozás:**
- Szervezetenként konfigurálható engedélyezett domain lista
- Regisztráció csak az engedélyezett domain-ekről lehetséges
- Admin felületen kezelhető az `allowed_domains` tábla

### 1.3 Bejelentkezés

**POST /api/v1/auth/login**

```json
Request:
{
  "email": "user@company.hu",
  "password": "********"
}

Response (200):
{
  "data": {
    "user": {
      "id": 1,
      "name": "Kovács János",
      "email": "user@company.hu",
      "role": "dispatcher",
      "organization": { "id": 1, "name": "ABC Kft." }
    },
    "token": "1|abc123..." // csak mobil módban
  }
}
```

**SPA mód**: A Vue.js app először lekéri a CSRF cookie-t (`GET /sanctum/csrf-cookie`), majd a login kérés session cookie-t állít be.

**Mobil mód**: A Flutter app bearer tokent kap, amit SecureStorage-ben tárol.

### 1.4 Jelszó-visszaállítás

**Folyamat:**
1. Felhasználó megadja az email-jét
2. Rendszer token-t generál és emailt küld
3. Felhasználó a linkre kattintva megadja az új jelszavát
4. Token érvényessége: 60 perc

**Endpoint-ok:**
- `POST /api/v1/auth/forgot-password` - Token generálás és email küldés
- `POST /api/v1/auth/reset-password` - Új jelszó beállítása

### 1.5 Session kezelés

- **Idle timeout**: Konfigurálható inaktivitási időkorlát (alapértelmezett: 30 perc)
- **Lockscreen**: Frontend implementáció - inaktivitás után a felhasználónak újra meg kell adnia a jelszavát
- **Egyidejű session-ök**: Engedélyezve (több eszközről bejelentkezés)
- **Token revokáció**: Kijelentkezéskor a token törlődik

### 1.6 Kijelentkezés

- **SPA**: `POST /api/v1/auth/logout` - Session törlés
- **Mobil**: `POST /api/v1/auth/logout` - Token törlés
- **Mindenhonnan**: `POST /api/v1/auth/logout-all` - Minden token/session törlés

---

## 2. Jogosultságkezelés (RBAC)

### 2.1 Architektúra

A rendszer **szerepkör-alapú hozzáférés-vezérlést (RBAC)** alkalmaz:

```
Felhasználó ──► Szerepkör ──► Engedélyek (modul + művelet)
```

- Minden felhasználónak pontosan egy szerepköre van
- Minden szerepkörhöz engedélyek rendelhetők
- Az engedélyek modul + művelet kombinációk

### 2.2 Alapértelmezett szerepkörök

| Szerepkör | Slug | Leírás | Rendszer |
|-----------|------|--------|----------|
| Adminisztrátor | admin | Teljes hozzáférés mindenhez | Igen |
| Diszpécser | dispatcher | Bejelentések kezelése, feladatok kiosztása, riportok | Igen |
| Felhasználó | user | Saját bejelentések, feladatok, alapfunkciók | Igen |
| Rögzítő | recorder | Adatrögzítés, bejelentés létrehozás | Igen |
| Karbantartó | maintainer | Karbantartási feladatok, eszközkezelés | Igen |

A rendszer szerepkörök nem törölhetők, de az engedélyeik módosíthatók. Egyéni szerepkörök szabadon létrehozhatók.

### 2.3 Modulok és műveletek

Minden modul az alábbi alap-műveletekkel rendelkezik:

| Művelet | Slug | Leírás |
|---------|------|--------|
| Megtekintés | read | Lista és részletek megtekintése |
| Létrehozás | create | Új elem létrehozása |
| Szerkesztés | update | Meglévő elem módosítása |
| Törlés | delete | Elem törlése |

Egyes moduloknak speciális műveletei is vannak:

| Modul | Speciális műveletek |
|-------|---------------------|
| tickets | assign (felelős kijelölés), escalate (eszkaláció), close (lezárás), manage_categories, manage_statuses |
| tasks | assign, complete, manage_recurring |
| projects | manage_teams, manage_milestones, change_status |
| issues | assign, resolve, close |
| suggestions | review (elbírálás), vote (szavazás) |
| documents | upload, download, manage_folders |
| locations | manage_floors, manage_rooms |
| assets | manage_types, change_status, generate_qr |
| maintenance | manage_schedules, log_work |
| contracts | manage_contractors |
| users | create, edit, deactivate, manage_roles |
| settings | manage_organization, manage_categories, manage_sla |
| reports | view_dashboard, export |
| messages | send |

### 2.4 Jogosultsági mátrix (alapértelmezett)

| Modul | Admin | Diszpécser | Felhasználó | Rögzítő | Karbantartó |
|-------|-------|------------|-------------|---------|-------------|
| **Tickets** |
| read | X | X | Saját + kiosztott | Saját | Kiosztott |
| create | X | X | X | X | X |
| update | X | X | Saját | Saját | Kiosztott |
| delete | X | - | - | - | - |
| assign | X | X | - | - | - |
| escalate | X | X | - | - | - |
| close | X | X | Saját | - | - |
| manage_categories | X | - | - | - | - |
| manage_statuses | X | - | - | - | - |
| **Tasks** |
| read | X | X | Saját + kiosztott | - | Kiosztott |
| create | X | X | X | - | - |
| update | X | X | Saját | - | Kiosztott |
| delete | X | - | - | - | - |
| assign | X | X | - | - | - |
| complete | X | X | Kiosztott | - | Kiosztott |
| manage_recurring | X | X | - | - | - |
| **Projects** |
| read | X | X | Ha tag | - | Ha tag |
| create | X | X | - | - | - |
| update | X | Saját | - | - | - |
| delete | X | - | - | - | - |
| manage_teams | X | Saját | - | - | - |
| **Issues** |
| read | X | X | Saját + kiosztott | - | Kiosztott |
| create | X | X | X | X | X |
| update | X | X | Saját | - | Kiosztott |
| assign | X | X | - | - | - |
| resolve | X | X | - | - | Kiosztott |
| **Suggestions** |
| read | X | X | X | X | X |
| create | X | X | X | X | X |
| review | X | - | - | - | - |
| vote | X | X | X | X | X |
| **Documents** |
| read | X | X | Jogosult mappák | - | Jogosult mappák |
| upload | X | X | Jogosult mappák | - | - |
| download | X | X | Jogosult mappák | - | Jogosult mappák |
| manage_folders | X | - | - | - | - |
| **Locations** |
| read | X | X | Kiosztott | - | Kiosztott |
| create | X | - | - | - | - |
| update | X | X | - | - | - |
| manage_floors | X | X | - | - | - |
| manage_rooms | X | X | - | - | - |
| **Assets** |
| read | X | X | Kiosztott helyszín | - | X |
| create | X | X | - | - | - |
| update | X | X | - | - | Kiosztott |
| change_status | X | X | - | - | X |
| generate_qr | X | X | - | - | - |
| **Maintenance** |
| read | X | X | - | - | X |
| manage_schedules | X | X | - | - | - |
| log_work | X | X | - | - | X |
| **Contracts** |
| read | X | X | - | - | - |
| create | X | - | - | - | - |
| update | X | - | - | - | - |
| manage_contractors | X | - | - | - | - |
| **Users** |
| read | X | X (saját szervezet) | - | - | - |
| create | X | - | - | - | - |
| edit | X | - | - | - | - |
| deactivate | X | - | - | - | - |
| manage_roles | X | - | - | - | - |
| **Settings** |
| manage_organization | X | - | - | - | - |
| manage_categories | X | - | - | - | - |
| manage_sla | X | - | - | - | - |
| **Reports** |
| view_dashboard | X | X | Korlátozott | - | Korlátozott |
| export | X | X | - | - | - |
| **Messages** |
| send | X | X | X | X | X |

### 2.5 Laravel implementáció

**Policy példa (TicketPolicy.php):**

```php
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.read');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermission('tickets.read')) {
            // Admin/Diszpécser: összes ticket a szervezetben
            if ($user->role->slug === 'admin' || $user->role->slug === 'dispatcher') {
                return $ticket->organization_id === $user->organization_id;
            }
            // Felhasználó: saját + kiosztott
            return $ticket->reporter_id === $user->id
                || $ticket->assignee_id === $user->id;
        }
        return false;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.assign')
            && $ticket->organization_id === $user->organization_id;
    }
}
```

**Middleware (CheckPermission.php):**

```php
// Route definíció
Route::get('/tickets', [TicketController::class, 'index'])
    ->middleware('permission:tickets.read');
```

**Global Scope (multi-tenant):**

```php
// Automatikus szervezet-szűrés
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('organization_id', auth()->user()->organization_id);
    }
}
```

### 2.6 Admin felület a jogosultságkezeléshez

Az admin felületen a jogosultsági mátrix vizuálisan szerkeszthető:
- Szerepkörök listája és szerkesztése
- Engedélyek ki-/bekapcsolása szerepkörönként (checkbox mátrix)
- Új egyéni szerepkör létrehozása
- Felhasználók hozzárendelése szerepkörökhöz

---

## 3. Multi-tenant elkülönítés

### 3.1 Megközelítés

**Egyetlen adatbázis, szervezet-alapú szeparáció:**
- Minden fő entitáson `organization_id` mező
- Global Scope automatikusan szűri a lekérdezéseket
- Middleware ellenőrzi, hogy a kért erőforrás a felhasználó szervezetéhez tartozik

### 3.2 Adatélet-ciklus

- Felhasználók csak a saját szervezetük adatait látják
- Admin csak a saját szervezetét kezelheti
- Szuper-admin (jövőbeli): több szervezet kezelése

### 3.3 Szervezet regisztráció

Kezdetben manuálisan, az adatbázisban hozzuk létre a szervezeteket. Később önkiszolgáló regisztráció is lehetséges.
