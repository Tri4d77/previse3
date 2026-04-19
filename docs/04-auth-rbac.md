# 04 - Autentikáció és jogosultságkezelés

> ⚠️ **Frissítve az M1–M2.5 fázisokban.** Az autentikáció és RBAC a **User–Membership–Organization** modellre épül (lásd [11-user-membership.md](11-user-membership.md)):
> - Egy user több szervezetben is tag lehet, membershipenként külön szerepkörrel
> - A login után több tagság esetén szervezet-választó lépés
> - Super-admin (Platform adminja) külön impersonation-flow-val léphet más szervezetekbe
> - A szerepkörök és jogosultságok **szervezetspecifikusak** (minden orgnak saját role-készlete)
> - A tervezett, de még nem implementált auth-funkciók (2FA, email-change, fiók-törlés, session-kezelés, audit log) a következő fázisokban készülnek — lásd M3–M8.

## 1. Autentikáció

### 1.1 Technológia

**Laravel Sanctum** két módban:
- **SPA mód (web)**: Cookie-alapú session autentikáció CSRF védelemmel
- **Token mód (mobil)**: Bearer token autentikáció

### 1.2 Meghívó-alapú tagság létrehozás

A regisztráció **meghívó-alapú** (nem nyilvános). A meghívó **mindig egy membership-hez** tartozik, nem közvetlenül userhez.

**Folyamat (két ág):**

**A) Új user meghívása** (az email cím még nem létezik a rendszerben):
1. Admin kitölti a meghívó űrlapot (email, role_id)
2. Rendszer létrehoz egy `users` rekordot (password = NULL, email_verified_at = NULL) és egy `memberships` rekordot `invitation_token`-nel
3. Email küldés a felhasználónak a meghívó linkkel
4. Felhasználó a linken megadja a **nevét + jelszavát**, a fiók aktiválódik és a tagság elfogadásra kerül

**B) Meglévő user új szervezetbe hívása** (az email már létezik):
1. Admin kitölti a meghívó űrlapot (email, role_id)
2. Rendszer **csak egy új `memberships` rekordot** hoz létre (a user marad)
3. Email küldés a felhasználónak, a link az új tagság elfogadására mutat
4. Felhasználó bejelentkezett állapotban (vagy login után) egy kattintással elfogadja — nem kell újra jelszót adnia

**Email domain korlátozás (opcionális, szervezetenként):**
- Szervezetenként konfigurálható engedélyezett domain lista
- Meghívás csak az engedélyezett domain-ekről megadott email-re lehetséges
- Admin felületen kezelhető (későbbi fázis)

### 1.3 Bejelentkezés

A login a credential (email + jelszó) ellenőrzésen kívül kezeli a **tagság-választás** lépését is. Részletes válaszformátumok: [03-api-endpoints.md §2 Auth végpontok](03-api-endpoints.md).

**Röviden:**
- 1 aktív tagság (vagy van `default_organization_id` a user_settings-ben): közvetlen login tokennel.
- Több aktív tagság, nincs default: a kliens ideiglenes `selection_token`-t kap, és a `/api/auth/select-organization` végponton kiválasztja a tagságot.
- Nincs aktív tagság: hibaüzenet.

**SPA mód**: a Vue.js app először lekéri a CSRF cookie-t (`GET /sanctum/csrf-cookie`), majd a login kérés session cookie-t + tokent állít be.

**Mobil mód**: a Flutter app bearer tokent kap, amit SecureStorage-ben tárol.

**Token tartalma:**
- `current_membership_id`: melyik tagság kontextusában aktív
- `context_organization_id`: super-admin impersonation esetén a cél-szervezet id-ja

### 1.3.1 Szervezet-váltás és super-admin impersonation

- **Switch** (saját tagságok között): `POST /api/auth/switch-organization` — új tokent ad vissza az új tagság kontextusához; a régi token revokálódik.
- **Enter** (super-admin belép idegen szervezetbe): `POST /api/auth/enter-organization` — külön impersonation-tokent generál `context_organization_id`-val, az eredeti token érintetlen marad.
- **Exit** (impersonation elhagyása): `POST /api/auth/exit-organization` — az impersonation-token revokálódik.

A super-admin impersonation audit célokra mindig naplózható (ki, mikor, melyik szervezetbe lépett be).

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

- **Idle timeout**: Konfigurálható inaktivitási időkorlát felhasználónként (`user_settings.lockscreen_timeout_minutes`, alapértelmezett 30 perc; tesztkörnyezetben 3 perc)
- **Lockscreen**: Frontend implementáció — inaktivitás után a felhasználónak újra meg kell adnia a jelszavát, a sessionje közben életben marad
- **Warning modal**: a lockscreen előtt 30 másodperccel figyelmeztető modal, countdown-nal
- **Egyidejű session-ök**: Engedélyezve (több eszközről bejelentkezés); aktív sessionök listázása + egyedi revoke az **M4** fázisban
- **Token revokáció**: Kijelentkezéskor a token törlődik; super-admin impersonation-token kilépéskor szintén revokálódik

### 1.6 Kijelentkezés

- **SPA**: `POST /api/v1/auth/logout` - Session törlés
- **Mobil**: `POST /api/v1/auth/logout` - Token törlés
- **Mindenhonnan**: `POST /api/v1/auth/logout-all` - Minden token/session törlés

---

## 2. Jogosultságkezelés (RBAC)

### 2.1 Architektúra

A rendszer **szerepkör-alapú hozzáférés-vezérlést (RBAC)** alkalmaz, **membership-kontextusban**:

```
User ──► Membership ──► Role ──► Permissions (modul + művelet)
          (user + org + role)
```

- Egy user több szervezetben is tag lehet (több `memberships` rekord)
- Minden tagsághoz **pontosan egy** szerepkör tartozik
- A szerepkörök és a role-permission mátrix **szervezetenként** vannak definiálva — minden új szervezet seederből kap default szerepköröket (admin, dispatcher, user, recorder, maintainer)
- Az engedélyek globális katalógus (`permissions` tábla, modul + művelet), de a role-hoz rendelés szervezetspecifikus
- Az aktuális tagság a tokenből (`current_membership_id`) vagy super-admin impersonation esetén a `context_organization_id`-ból derül ki

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

- Felhasználók csak a saját tagságuk szervezetének adatait látják (a token `current_membership_id`-ja alapján)
- Szervezet-admin (subscriber vagy client szintű) csak a saját szervezetét kezelheti
- **Subscriber-admin** extra joga: client-leszármazott szervezeteket hozhat létre és kezelhet a saját subscriber-szervezete alatt
- **Super-admin** (Platform szervezet admin tagja, `users.is_super_admin = true`): minden szervezetet lát; impersonation flow-val léphet be bármely szervezetbe (lásd 1.3.1)

### 3.3 Szervezet hierarchia

Három szintű hierarchia `organizations.parent_id` + `organizations.type` alapján:
- **Platform** (`type=platform`, `parent_id=NULL`): egy darab, a rendszer gyökere, itt laknak a super-adminok
- **Subscriber** (`type=subscriber`, `parent_id=Platform.id`): előfizetők, a termék valódi vásárlói
- **Client** (`type=client`, `parent_id=Subscriber.id`): a subscriberek ügyfelei (pl. épületek, amiket üzemeltetnek)

A Platform szervezetet a rendszer alapítása során seederből hozzuk létre. Subscribereket a super-admin hoz létre; clienteket a super-admin vagy a subscriber saját adminja.
