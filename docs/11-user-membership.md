# 11 - Felhasználó- és tagságkezelés (Membership modell)

## 1. Áttekintés

Ez a dokumentum a Previse v2 rendszer **felhasználó- és tagságkezelési** részét írja le részletesen. Az eredeti adatmodellen (user → egy szervezet) jelentős módosítás történik: a felhasználó (User) és a tagság (Membership) külön entitás lesz, hogy egy felhasználó több szervezetnél is tag lehessen.

### 1.1 Alapelvek

1. **Egy személy = egy User rekord** (globálisan egyedi email alapján)
2. **Szervezeti tagság = Membership rekord** - egy user több szervezetben tag lehet, mindegyiknél más szerepkörrel
3. **A jelszó, 2FA, profilkép, telefon, név a userhez tartozik** - mindenhol ugyanaz
4. **A szerepkör, csoporttagság, aktív/inaktív állapot a membership-hez tartozik** - szervezet-specifikus
5. **A tevékenységek (bejelentések, feladatok, kommentek) a membership-re mutatnak**, NEM a userre - így egy szervezet csak a saját kontextusában látja az adatokat

---

## 2. Adatmodell

### 2.1 `users` tábla

| Mező | Típus | Kötelező | Leírás |
|------|-------|----------|--------|
| id | BIGINT PK | - | Egyedi azonosító |
| name | VARCHAR(255) | Igen | Teljes név |
| email | VARCHAR(255) | Igen | **Globálisan egyedi** email cím |
| password | VARCHAR(255) | Nem | Bcrypt hash; lehet NULL, ha még nincs beállítva (meghívott user) |
| avatar_path | VARCHAR(500) | Nem | Profilkép elérési út |
| phone | VARCHAR(50) | Nem | Telefonszám |
| email_verified_at | TIMESTAMP | Nem | Mikor aktiválta a fiókját |
| is_active | BOOLEAN | Igen | **Globális aktivitás**. Ha false, sehol nem tud belépni |
| two_factor_secret | TEXT | Nem | 2FA titkos kulcs (titkosítva) |
| two_factor_recovery_codes | TEXT | Nem | 2FA tartalék kódok (titkosított JSON) |
| two_factor_confirmed_at | TIMESTAMP | Nem | 2FA aktiválás időpontja |
| pending_email | VARCHAR(255) | Nem | Email-változtatás: az új email, amire vár megerősítés |
| email_change_token | VARCHAR(100) | Nem | Email-változtatáshoz token |
| email_change_sent_at | TIMESTAMP | Nem | Mikor kezdeményezte az email-változtatást |
| last_login_at | TIMESTAMP | Nem | Utolsó sikeres bejelentkezés |
| last_login_ip | VARCHAR(45) | Nem | Utolsó bejelentkezés IP |
| remember_token | VARCHAR(100) | Nem | Laravel "Emlékezz rám" |
| created_at | TIMESTAMP | - | Létrehozás |
| updated_at | TIMESTAMP | - | Módosítás |
| deleted_at | TIMESTAMP | Nem | Globális user soft delete (csak szuper-admin használja, GDPR célokra) |

**Fontos:** az `organization_id` és `role_id` mezők **kikerülnek** a users táblából!

### 2.1b `personal_access_tokens` tábla (bővítés)

A Laravel Sanctum tábláját egy új mezővel bővítjük:

| Mező | Típus | Leírás |
|------|-------|--------|
| current_membership_id | BIGINT FK NULL | Az adott tokennel melyik tagsággal dolgozik a user |
| context_organization_id | BIGINT FK NULL | Csak szuper-admin impersonation esetén: melyik szervezetben dolgozik (akkor current_membership_id=NULL) |

**Logika:**
- Normál user: `current_membership_id` ki van töltve, `context_organization_id` NULL
- Szuper-admin Platform-on: `current_membership_id = Platform admin membership`, `context_organization_id` NULL
- Szuper-admin impersonation: `current_membership_id = NULL`, `context_organization_id = {target org id}`

### 2.2 `memberships` tábla (ÚJ)

Egy felhasználó adott szervezetben való tagságát írja le.

| Mező | Típus | Kötelező | Leírás |
|------|-------|----------|--------|
| id | BIGINT PK | - | Egyedi azonosító |
| user_id | BIGINT FK | Igen | Melyik user tagsága |
| organization_id | BIGINT FK | Igen | Melyik szervezetben |
| role_id | BIGINT FK | Igen | Milyen szerepkörrel (az adott szervezet szerepkörei közül) |
| is_active | BOOLEAN | Igen | Aktív-e ez a tagság. Ha false, a user ezt a szervezetet nem érheti el |
| invitation_token | VARCHAR(100) | Nem | Meghívó token (amíg el nem fogadta) |
| invitation_sent_at | TIMESTAMP | Nem | Mikor küldték a meghívót |
| joined_at | TIMESTAMP | Nem | Mikor fogadta el a tagságot |
| last_active_at | TIMESTAMP | Nem | Mikor dolgozott utoljára ebben a szervezetben |
| created_at | TIMESTAMP | - | Létrehozás |
| updated_at | TIMESTAMP | - | Módosítás |
| deleted_at | TIMESTAMP | Nem | Soft delete (a szervezetből eltávolítva) |

**Unique constraint:** (user_id, organization_id) WHERE deleted_at IS NULL
(egy user egy szervezetben egyszerre csak egy aktív tagsággal rendelkezhet)

### 2.3 `user_settings` tábla (módosítás)

A user-szintű preferenciák maradnak a userhez kötve (mert ezek nem szervezet-függőek):
- theme, color_scheme, locale, timezone
- items_per_page (globális default)
- notification_email, notification_push, notification_sound
- **default_organization_id** (ÚJ): opcionális FK organizations.id
  - Ha kitöltve és a user több tagsággal rendelkezik → bejelentkezéskor automatikusan abba a szervezetbe lép
  - Ha NULL és a user több tagsággal rendelkezik → szervezet-választó oldal jelenik meg minden bejelentkezéskor
  - Ha csak 1 aktív tagsága van → default_organization_id irreleváns, automatikusan oda lép

**A `default_page` kikerül**: mindenki a dashboard-ra kerül bejelentkezés után, nem konfigurálható.

### 2.4 `groups` és `group_membership` (átnevezés `group_user` helyett)

A csoportok szervezethez kötődnek (mint eddig). A tagság viszont a **membership**-re mutat, nem a user-re:

**`group_membership` pivot (új név):**
| Mező | Leírás |
|------|--------|
| group_id | FK groups |
| membership_id | FK memberships |

Ez azért fontos, mert egy user két szervezetben különböző csoportokban lehet.

### 2.5 Engedélyek (permissions + role_permission)

Változatlan. Az engedélyek globálisak (tickets.create, users.read, stb.), a role-hoz pedig role_permission pivot-on keresztül kötődnek. Minden szervezetnek saját role-jai vannak saját engedély-mátrixszal.

---

## 3. Entitások és szerepek

### 3.1 Felhasználó (User) - személyes szint

A User **egy személyt** képvisel, szervezet-függetlenül.

**A user szintjén történik:**
- Globális adatok: név, email, jelszó, profilkép, telefon
- Biztonsági elemek: 2FA, jelszó-visszaállítás
- Globális beállítások: téma, nyelv, időzóna, értesítési preferenciák
- **Default szervezet** (ha több tagsága van): melyikbe lépjen be automatikusan
- Email-változtatás
- Szervezet elhagyása (saját kezdeményezésre)
- Globális deaktiválás (csak szuper-admin, biztonsági okokból)
- Globális törlés (GDPR, csak szuper-admin)

**A user NEM rendelkezik közvetlenül:**
- Szerepkörrel
- Szervezettel
- Engedéllyel

Ezek a tagsági (membership) szinten vannak.

### 3.2 Tagság (Membership) - szervezeti szint

A Membership a userhez és egy szervezethez kötött kapcsolatot jelöli, az adott szervezeten belüli szerepkörrel.

**A tagság szintjén történik:**
- Szerepkör (admin, diszpécser, felhasználó, stb.)
- Szerepkörön keresztüli engedélyek (tickets.read, users.edit, stb.)
- Csoporttagság (csapat, részleg)
- Szervezet-specifikus aktiválás/deaktiválás
- Meghívó elfogadása / szervezetből kilépés
- Tevékenységi napló (bejelentések, feladatok, kommentek - ezek a membership_id-re mutatnak)

**A tagság NEM tartalmazza:**
- A user személyes adatait (név, email, jelszó) - ezek a userhez kötődnek

### 3.3 Szerepkörök (Roles)

Minden **szervezetnek külön szerepkörei vannak**, saját engedély-mátrixszal. Az 5 alap rendszer-szerepkör minden szervezetnél automatikusan létrejön (seederrel):

- Adminisztrátor
- Diszpécser
- Felhasználó
- Rögzítő
- Karbantartó

Plusz a Platform szervezethez tartozó **szuper-admin** szerepkör, amely az egész rendszer felett rendelkezik.

Ezen felül szervezeten belül egyéni szerepkörök hozhatók létre a szervezet admin által (Fázis 1 későbbi részében).

---

## 4. Felhasználó állapotai

### 4.1 User állapotok

| Állapot | `is_active` | `email_verified_at` | `password` | `deleted_at` | Bejelentkezhet? |
|---------|-------------|---------------------|------------|--------------|-----------------|
| **Pending** (csak létrehozva, még nem aktiválta) | false | NULL | NULL vagy random | NULL | NEM |
| **Aktív** | true | valós időpont | beállítva | NULL | IGEN (ha van aktív tagsága) |
| **Globálisan deaktivált** | false | valós időpont | beállítva | NULL | NEM |
| **GDPR törölve** | - | - | - | valós időpont | NEM |

### 4.2 Membership állapotok

| Állapot | `is_active` | `joined_at` | `invitation_token` | `deleted_at` | Leírás |
|---------|-------------|-------------|-------------------|--------------|--------|
| **Meghívó pending** | false | NULL | beállítva | NULL | Meghívót küldtünk, várjuk az elfogadást |
| **Aktív** | true | valós időpont | NULL | NULL | Teljesen aktív tag |
| **Deaktivált** | false | valós időpont | NULL | NULL | Tag, de szervezet admin deaktiválta |
| **Törölt** | - | - | - | valós időpont | Szervezet admin eltávolította |
| **Lejárt meghívó** | false | NULL | beállítva (de `invitation_sent_at` > 7 nap) | NULL | A meghívót nem fogadta el 7 napon belül |

### 4.3 Fiók "aktivitása" a bejelentkezéskor

A bejelentkezés akkor engedélyezett, ha:
1. `user.is_active = true` ÉS
2. `user.email_verified_at IS NOT NULL` ÉS
3. `user.password IS NOT NULL` ÉS
4. A usernek van **legalább 1 aktív és nem törölt** membership-je

Ha bármelyik feltétel nem teljesül, megfelelő hibaüzenet jelenik meg.

---

## 5. Folyamatok

### 5.1 Meghívás folyamata

**Szereplő:** Szervezet adminisztrátor (vagy felhasználó-kezelés joggal rendelkező tag)

```
Admin kitölti a meghívó űrlapot (név, email, szerepkör)
       │
       ▼
  Backend: létezik már ilyen email a users táblában?
       │
   ┌───┴───┐
   │       │
  IGEN    NEM
   │       │
   │       ▼
   │    Új user létrehozása
   │    - name, email kitöltve
   │    - password = NULL (még nem állította be)
   │    - email_verified_at = NULL
   │    - is_active = false (amíg aktiválja)
   │
   ▼
Új membership létrehozása
- user_id, organization_id, role_id
- is_active = false
- invitation_token = random(64)
- invitation_sent_at = now()
       │
       ▼
Email küldés a meghívóval
(link: /invitation/{token})
```

**Üzenet a régi usernek** (aki már regisztrált): 
> "Meghívtak a **XY Karbantartó Kft.** szervezetbe **Diszpécser** szerepkörrel. A meghívás elfogadásához kattints a linkre."

**Üzenet az új usernek:**
> "Meghívtak a **XY Karbantartó Kft.** szervezetbe **Diszpécser** szerepkörrel. Állítsd be a jelszavad és lépj be."

### 5.2 Meghívó elfogadása

**Szereplő:** A meghívott user

A `/invitation/{token}` linkre kattint:

**Új user esetén:**
```
Beírja: jelszó + jelszó megerősítés
       │
       ▼
  user.password = hash
  user.email_verified_at = now()
  user.is_active = true
  membership.invitation_token = null
  membership.is_active = true
  membership.joined_at = now()
       │
       ▼
  Automatikus bejelentkezés
```

**Már meglévő user esetén:**
```
Jelentkezzen be? Vagy egyszerűsítve:
  - Ha már be van jelentkezve: csak "Elfogadom" gomb
  - Ha nincs: először jelentkezzen be, majd "Elfogadom"
       │
       ▼
  membership.invitation_token = null
  membership.is_active = true
  membership.joined_at = now()
       │
       ▼
  Ha volt 1 aktív tagsága, most 2 van → frissül a szervezet-váltó
```

### 5.3 Bejelentkezés folyamata

```
POST /auth/login (email + jelszó)
       │
       ▼
  User keresése email alapján
       │
   ┌───┴───┐
   │       │
 nincs   van
   │       │
   ▼       ▼
 401     Jelszó ellenőrzés
         │
   ┌─────┴─────┐
   │           │
  rossz       jó
   │           │
   ▼           ▼
  401     Ellenőrzések:
         - user.is_active = true?
         - user.email_verified_at kitöltve?
         - van aktív membership?
                │
          ┌─────┴─────┐
          │           │
      valamelyik    mind OK
      HIBÁS
          │           │
          ▼           ▼
        403        2FA engedélyezve?
        (megfelelő
         üzenet)      │
                  ┌───┴───┐
                  │       │
                 IGEN    NEM
                  │       │
                  ▼       ▼
             2FA kód  Hány aktív
             kérés    membershipje van?
                          │
                  ┌───────┴────────┐
                  │                │
                  1               >1
                  │                │
                  ▼                ▼
               Auto belép    Van beállított
               abba          default_organization_id?
                                  │
                             ┌────┴────┐
                             │         │
                            IGEN      NEM
                             │         │
                             ▼         ▼
                        Auto belép  Szervezet-
                        default-ba  választó oldal
                             │         │
                             └────┬────┘
                                  ▼
                          Token generálás
                          current_membership_id-vel
```

### 5.4 Szervezet-választás a bejelentkezéskor

Ha a user több aktív tagsággal rendelkezik, a belépés után egy **szervezet-választó oldal** jelenik meg:

```
┌─────────────────────────────────────┐
│ Üdvözlünk, Kovács János!            │
│                                     │
│ Válaszd ki, melyik szervezet        │
│ ügyeit szeretnéd intézni:           │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ 🏢 XY Karbantartó Kft.          │ │
│ │    Diszpécser                   │ │
│ │    Utoljára: 2 órája            │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ 🏢 ABC Plaza                     │ │
│ │    Adminisztrátor               │ │
│ │    Utoljára: tegnap             │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ 🏢 DEF Irodaház                  │ │
│ │    Felhasználó                  │ │
│ │    Utoljára: 3 napja            │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

- A listában a membership-ek vannak, `last_active_at` szerint csökkenő rendezésben
- Kattintásra az adott membership kontextusában lép be

### 5.5 Szervezet-váltás bejelentkezett állapotban

A header-ben (szervezet neve mellett) egy **váltó dropdown**:

```
┌──────────────────────┐
│ XY Karbantartó ▾     │  ← kattintva
└──────────────────────┘
  │
  ▼
┌──────────────────────┐
│ XY Karbantartó ✓     │
│ Diszpécser           │
├──────────────────────┤
│ Váltás másik         │
│ szervezetre:         │
│                      │
│ • ABC Plaza          │
│   Admin              │
│                      │
│ • DEF Irodaház       │
│   Felhasználó        │
└──────────────────────┘
```

Szervezet-váltásnál:
- A backend új tokent generál, `current_membership_id` az új membership-re
- A régi token invalidálódik
- A frontend állapota frissül, a user adatai (név, email, 2FA) változatlanok, csak a szervezet-specifikus részek frissülnek

### 5.6 Bejelentkezés hibaüzenetei

| Helyzet | Üzenet | HTTP |
|---------|--------|------|
| Nincs ilyen email vagy rossz jelszó | "Hibás email cím vagy jelszó." | 422 |
| user.is_active = false | "A fiókod inaktív. Fordulj a szuper-adminisztrátorhoz." | 422 |
| user.email_verified_at = NULL | "A fiókod még nem lett aktiválva. Fogadd el a meghívót az email-ben kapott linken." | 422 |
| Nincs aktív membership | "**Nincs aktív szervezeti tagságod. Fordulj a szervezeti adminisztrátorhoz!**" | 422 |
| Túl sok próbálkozás (rate limit) | "Túl sok bejelentkezési kísérlet. Próbáld újra :seconds másodperc múlva." | 429 |

### 5.7 Tagság deaktiválása (admin oldalról)

**Ki:** Szervezet admin, `users.deactivate` engedéllyel

**Művelet:**
```
membership.is_active = false
(deleted_at marad NULL)
```

**Hatás:**
- Ha a user jelenleg ebben a szervezetben dolgozik, kap egy értesítést a következő API hívásnál: "Ez a tagság már nem aktív. Másik szervezetet választasz, vagy kijelentkezel?"
- Ha a user más szervezetben dolgozik, semmi sem változik számára azonnal
- Ha a deaktivált tagsága volt az egyetlen aktív, a következő bejelentkezéskor kapja a "Nincs aktív szervezeti tagságod" üzenetet

**Visszaaktiválás:** ugyanott, `is_active = true`. Nem kell új meghívó.

### 5.8 Tagság törlése (admin oldalról)

**Ki:** Szervezet admin, `users.edit` engedéllyel

**Művelet:**
```
membership.deleted_at = now()
membership.is_active = false
- Az adott szervezetre korlátozott tokenek (current_membership_id = ez)
  törlése
- A user bejelentkezési adatai (jelszó, 2FA) NEM változnak
```

**Hatás:**
- A user azonnal kiesik ebből a szervezetből (ha ott dolgozott)
- Más szervezeteiben változatlanul aktív
- Ha nem volt más aktív tagsága, a következő bejelentkezéskor kapja a "Nincs aktív szervezeti tagságod" üzenetet

**A user adatai (név, email) változatlanok maradnak a users táblában!** Nincs jelszó-törlés, nincs account-invalidálás.

### 5.9 Tagság visszaállítása

**Ki:** Szervezet admin

**Művelet:**
```
membership.deleted_at = NULL
membership.is_active = false
membership.invitation_token = random(64)
membership.invitation_sent_at = now()
```

**Flow:**
1. Admin a felhasználók listájában "Törölt tagok megjelenítése" toggle-t bekapcsolja
2. A törölt tagok szürkítve / áthúzva jelennek meg
3. Action menuből "Visszaállítás" választható
4. Egy modal jelenik meg a meglévő adatokkal (név, email olvasható, szerepkör módosítható)
5. "Visszahívás" gomb: a fenti művelet lefut, új meghívó email megy ki
6. A user egyszerűen elfogadja, nem kell új jelszót beállítania (már van neki)

### 5.10 Email-cím változtatás folyamata

**Ki:** A user saját magának, a Profil oldalon

**Lépések:**

```
1. User beírja az új emailt + jelenlegi jelszót
       │
       ▼
2. Backend ellenőrzi:
   - Aktuális jelszó helyes-e?
   - Az új email nem foglalt-e (users.email)?
   - Az új email nincs-e már a pending_email-ben másnál?
       │
       ▼
3. Ha OK:
   user.pending_email = új_email
   user.email_change_token = random(64)
   user.email_change_sent_at = now()
       │
       ▼
4. Két email megy ki:
   a) Az ÚJ emailre: "Erősítsd meg az új email címed"
      (link: /confirm-email-change/{token})
   b) A RÉGI emailre TÁJÉKOZTATÓ (nincs link):
      "Az email címed módosítása folyamatban van. Ha nem te
      kezdeményezted, fordulj a szervezeti adminisztrátorhoz,
      aki segít visszavonni a módosítást."
       │
       ▼
5. Ha a user az ÚJ emailen a linket követi:
   user.email = user.pending_email
   user.pending_email = NULL
   user.email_change_token = NULL
   Értesítés mindkét emailre: sikeres változtatás
       │
       ▼
6. Ha 24 óra alatt az új email nem erősít:
   A token lejár, automatikus tisztítás.
   user.pending_email = NULL
   user.email_change_token = NULL
```

**A régi emailen NINCS visszavonási link**, csak tájékoztatás. Ez azért van, hogy:
- Ne lehessen a régi emailen illetéktelen visszavonást csinálni (pl. ha valaki hozzáfér a régi emailhez)
- A helyes visszavonási folyamat: a user szervezeti adminisztrátorához fordul, aki ellenőrzi a user személyazonosságát és a szuper-admin segítségével visszaállítja az emailt

**Korlátozások:**
- Szervezet admin NEM változtathatja a user email címét közvetlenül
- Csak a user saját maga kezdeményezi
- A szuper-admin kivételesen vissza tudja állítani / beállítani (vésztartalék) - később implementáljuk

### 5.11 Jelszó-visszaállítás

**Nem változik** a jelenlegi flow:

1. User megadja az emailjét a "Forgot password" oldalon
2. Rendszer generál egy tokent és email-t küld
3. User a linkre kattintva új jelszót állít be
4. Az új jelszó a userhez tartozik, minden szervezetben érvényes

### 5.12 2FA

**A userhez kötődik** (nem membership-hez):

- Bekapcsolás: Profil oldal, QR kód, authenticator app beolvas, első kód megadása → aktív
- 8 db tartalék kód generálódik (egyszer látható)
- Bejelentkezéskor: jelszó után 2FA kód kérés (egyetlen lépés, szervezet-független)
- Kikapcsolás: Profil oldal, jelszó megerősítés, majd kikapcsolás

**Biztonsági megjegyzés:** mivel egy user több szervezetben is lehet, a 2FA globális - a user dönti el, hogy használja-e, és nem a szervezet admin.

### 5.13 Globális user deaktiválás (későbbre)

**Ki:** Szuper-admin (platform szintű)

**Mikor használjuk:**
- Gyanús tevékenység
- Biztonsági incidens
- A user kéri a teljes deaktiválást

**Művelet:**
```
user.is_active = false
- Minden token törölve
- Bejelentkezés teljesen tiltva, minden szervezetből
- A membership-ek NEM törlődnek, csak hozzáférhetetlenek
```

**Visszaállítás:** user.is_active = true, a membership-ek érvényesek maradnak.

Ez **Fázis 1 későbbi része vagy Fázis 2**.

### 5.14 GDPR - user törlése

**Ki:** Szuper-admin (vagy user saját maga - későbbi funkció)

**Művelet (soft delete):**
```
user.deleted_at = now()
user.email = "deleted_{id}@anonym.local" (anonimizálás)
user.password = NULL
user.phone = NULL
user.avatar_path = NULL
user.two_factor_secret = NULL
user.two_factor_recovery_codes = NULL
user.pending_email = NULL
user.email_change_token = NULL
user_settings: alapértékekre visszaállítva
- Minden membership soft deletelése
- Minden token törlése
- Minden email_verified_at, invitation_token NULL-ra
```

**A név (user.name) NEM változik!** Szándékosan:
- A user korábbi tevékenységei (bejelentések, kommentek, hozzászólások) megmaradnak, és a szerző neveként továbbra is a valódi név látszik
- Ez szükséges a történeti adatok értelmezhetősége miatt

**Anonimizált adatok a törlés után:** email, jelszó, telefon, avatar, 2FA, beállítások, email-változtatás adatok.

**A kapcsolódó tevékenységek** (bejelentések, kommentek) megmaradnak, és a szerző neveként továbbra is a **név** látszik, de más személyes adat nem érhető el.

Ez egy **ritka, visszavonhatatlan művelet**, későbbi fázisban implementáljuk.

### 5.15 Szervezet elhagyása (user saját kezdeményezésre)

**Ki:** A user saját magának, a Profil oldalon (Tagságaim szekció)

Ez egy alaposan megfontolt lépés, ezért többlépcsős megerősítési folyamat szükséges.

**Lépések:**

```
1. User a "Tagságaim" szekcióban rákattint
   egy szervezet mellett az "Elhagyás" gombra
       │
       ▼
2. Figyelmeztető dialógus jelenik meg:
   "Biztosan elhagyod a [XY Kft.] szervezetet?
    - Nem férsz hozzá többé a szervezet adataihoz
    - A megadott bejegyzéseid (komment, bejelentés)
      megmaradnak a neveddel
    - Újrafelvételhez az admin új meghívóját kell elfogadnod
    A művelet véglegesítéséhez email megerősítés szükséges."
       │
       ▼
3. User megerősíti + megadja a jelenlegi jelszavát
       │
       ▼
4. Backend ellenőrzi a jelszót
   Generál egy leave_token-t
   Email küldés a user email címére:
   "Kattints a linkre a [XY Kft.] szervezet végleges
    elhagyásához. Ha nem te kezdeményezted, ne tedd meg."
       │
       ▼
5. User a linkre kattint (24 órán belül)
       │
       ▼
6. membership.is_active = false
   membership.deleted_at = now()
   Ha a user éppen ebben a szervezetben dolgozott
   (current_membership_id = ez), a token invalidálódik
       │
       ▼
7. Értesítések:
   - User (sikeres törlés): "Sikeresen elhagytad a [XY Kft.]
     szervezetet."
   - Szervezet admin(ok): "[Kovács János] önként elhagyta a
     szervezetet [2026-04-18 15:30]-kor."
   - Szuper-admin: az activity_log táblába bejegyzés
     (későbbi fázisban implementáljuk a strukturált logot)
       │
       ▼
8. Ha ez volt a user utolsó aktív tagsága:
   user.is_active = false (automatikus)
   A user nem tud többé bejelentkezni,
   amíg egy admin nem hívja vissza.
```

**Tiltott helyzetek:**
- Ha a user az adott szervezet **egyetlen admin** tagja: nem hagyhatja el. Előbb más admint kell kineveznie, vagy az adminnál felsőbb szint (szuper-admin) kezeli.
- Nem az aktuálisan kiválasztott szervezetben van: nincs akadály, elhagyhatja.

---

### 5.16 User automatikus deaktiválása 0 aktív tagság esetén

Amikor egy user összes aktív tagsága megszűnik:
- Adminok törölték minden szervezetből
- Szervezetek deaktiválták minden tagságát
- User saját maga elhagyta az utolsó szervezetet

A rendszer **automatikusan** beállítja: `user.is_active = false`

**Hatás:**
- A user **nem tud bejelentkezni** ("A fiókod inaktív. Fordulj a szuper-adminisztrátorhoz.")
- Nem kap értesítéseket
- Minden token törölve

**Visszaállítás:** amikor egy admin újra meghívja valamelyik szervezetbe, és a user elfogadja a meghívót, automatikusan `user.is_active = true` lesz.

---

## 6/A. Szuper-admin hozzáférés (impersonation)

### 6/A.1 Alapelv

A szuper-admin (Platform szervezet admin tagja) **automatikus hozzáféréssel** rendelkezik minden szervezethez a platform-on. Ezt nem külön tagság (membership) formájában, hanem **kontextus-váltás (impersonation)** technikával valósítjuk meg.

**Miért impersonation?**
- Nem duplikáljuk a tagságokat: a szuper-admin minden szervezetbe automatikusan "belép", nem kell külön meghívni
- A szervezet admin-ok **nem látják** a szuper-admint a saját felhasználói listájukon (mert nincs valódi membership-rekord)
- Tiszta audit trail: minden szuper-admin művelet megjelölve van külön

### 6/A.2 Szervezet-hierarchia (fa-struktúra)

A szervezetek `parent_id` mezőjével egy konzisztens fa-struktúra alakul ki:

- **Platform** (type=platform, parent_id=NULL): egyetlen rekord, a fa gyökere
- **Előfizetők** (type=subscriber, parent_id=Platform.id): a Platform közvetlen gyerekei
- **Ügyfél-szervezetek** (type=client, parent_id=Subscriber.id): az előfizető alatt

```
🏢 Previse Platform (parent_id=NULL)
├── 🏢 XY Karbantartó Kft. (parent_id=Platform)
│   ├── 🏬 ABC Plaza (parent_id=XY)
│   └── 🏬 DEF Irodaház (parent_id=XY)
├── 🏢 ZZ Facility Kft. (parent_id=Platform)
│   └── 🏬 GHI Lakópark (parent_id=ZZ)
```

### 6/A.3 Token kontextus

A `personal_access_tokens` tábla egy új opcionális oszlopot kap:

| Mező | Típus | Leírás |
|------|-------|--------|
| context_organization_id | BIGINT FK NULL | Ha kitöltve, a szuper-admin ebben a szervezetben impersonál |

**Normál felhasználónál:** NULL, a `current_membership_id` szerint működik a scope.

**Szuper-admin impersonation módban:** kitöltve, a middleware ennek a szervezetnek a kontextusában enged hozzáférést, szuper-admin jogokkal.

### 6/A.4 Impersonation szabályok

- **Csak szuper-admin** használhatja (a `user.isSuperAdmin()` ellenőrzés kell, különben 403)
- **Minden szervezetben admin-szintű jogosultság** aktív (akár van ott valódi szerepkör-bejegyzés, akár nincs)
- **A Platform-szintű funkciók** (pl. szervezetek kezelése, platform statisztikák) **impersonation módban is elérhetők**, mert a szuper-admin feladata jellemzően biztonsági / felhasználó-kezelési intézkedés, nem operatív munka
- **A szuper-admin nem jelenik meg** a megtekintett szervezet Users listájában (mert nincs valódi membership)
- **Minden szuper-admin művelet impersonation módban naplózódik** külön flag-gel

### 6/A.5 Váltás más szervezetre

**Backend endpoint:** `POST /api/v1/auth/enter-organization/{org_id}`

Folyamat:
1. Ellenőrzés: a user szuper-admin-e (`user.isSuperAdmin() == true`)
2. Ellenőrzés: a `{org_id}` létezik-e és aktív-e
3. Új token létrehozása, melyben:
   - `context_organization_id = {org_id}`
   - `current_membership_id = NULL` (nem használjuk)
4. A régi token invalidálása (biztonsági okból)
5. Válasz: az új token + a megtekintett szervezet adatai

**Vissza a Platform-ra:** `POST /api/v1/auth/exit-organization`

Folyamat:
1. Új token generálása, melyben:
   - `context_organization_id = NULL`
   - `current_membership_id = Platform membership id`
2. Régi token invalidálása

### 6/A.6 UI - szervezet-váltó szuper-admin számára

**Normál tag (1-3 szervezet):** egyszerű lista (mint eddig).

**Szuper-admin:** fa-struktúra kereséssel, expand/collapse lehetőséggel:

```
┌─────────────────────────────────────┐
│ 🔍 Keresés...                       │
├─────────────────────────────────────┤
│ 🏢 Previse Platform ✓ (aktív)       │
│ ├── 🏢 XY Karbantartó Kft.          │
│ │   ├── 🏬 ABC Plaza                │
│ │   └── 🏬 DEF Irodaház             │
│ └── 🏢 ZZ Facility Kft.             │
│     └── 🏬 GHI Lakópark             │
└─────────────────────────────────────┘
```

### 6/A.7 Vizuális indikátor impersonation módban

Amikor a szuper-admin egy nem-Platform szervezetben van, a header-be kerül egy figyelmeztető sáv:

```
┌──────────────────────────────────────────────────────────┐
│ 🔒 Szuper-admin mód: XY Karbantartó Kft. megtekintése    │
│    [Vissza a Platform-ra]                                 │
└──────────────────────────────────────────────────────────┘
```

- **Narancs/amber színű háttér** - nem hétköznapi állapot
- **"Vissza a Platform-ra" gomb** - gyors visszalépés
- Egy kattintásos kilépés a megtekintésből

### 6/A.8 Audit log

Minden szuper-admin művelet **impersonation módban** külön flag-gel naplózódik (későbbi `activity_log` tábla):

| Mező | Leírás |
|------|--------|
| user_id | Szuper-admin user |
| organization_id | Melyik szervezetben hajtotta végre |
| is_super_admin_impersonation | **TRUE** (megkülönböztető flag) |
| action | Mi történt (pl. "user.toggle_active") |
| subject_type, subject_id | Min |
| description | Szöveges leírás |
| properties | Extra JSON |
| created_at | Mikor |

Ez lehetővé teszi szervezetenként lekérdezni: "mit csinált a szuper-admin nálunk az elmúlt 30 napban?"

---

## 6. Jogosultsági logika

### 6.1 Scope-ok

Minden API endpoint a **current_membership** kontextusban működik (a token-ből jön). A lekérdezések automatikusan az adott szervezet adataira szűrnek:

```php
// A "saját szervezet" minden query-ben:
$membership = $request->user()->currentMembership();
$organization_id = $membership->organization_id;
```

### 6.2 Engedélyek

Az engedély-ellenőrzés a **role** szintjén történik:

```php
$request->user()->currentMembership()->role->hasPermission('tickets.create')
```

Egy user egy szervezetben egy adott szerepkört kap, ami engedélyeket hordoz.

### 6.3 Szuper-admin

A szuper-admin user (Platform szervezet tagja, admin szereppel) **minden szervezethez hozzáférhet** a current_membership-től függetlenül. Ezt külön logikával kezeljük.

---

## 7. Frontend hatások

### 7.1 Login oldal

Változatlan (email + jelszó). A válasz alapján:
- 2FA szükséges? → 2FA oldal
- Több aktív tagság? → Szervezet-választó oldal
- Egy tagság? → Dashboard (az adott szervezet kontextusában)

### 7.2 Szervezet-választó oldal (ÚJ)

A bejelentkezés után jelenik meg, ha a user több aktív tagsággal rendelkezik. Kártyás elrendezés, gyors választás.

### 7.3 Szervezet-váltó dropdown (header-ben)

A profil dropdown mellett egy másik dropdown, amely a user tagságait mutatja. Váltás:
- **Új token generálás** a kiválasztott membership-re
- A régi token invalidálódik (törölve lesz a personal_access_tokens-ből)
- Frontend state frissítés (permission-ek, organization info, stb.)
- A user nevén, adatain kívül minden szervezeti adat frissül

**Miért új token váltáskor?**
- **Biztonság**: a token-hez hard-kötve van egy `current_membership_id`. Egy tokennel nem lehet más szervezet adataihoz hozzáférni. Így garantáljuk, hogy egy XY-ban írt komment nem keveredhet egy ABC-s bejelentéssel.
- **Audit**: minden token egyértelműen azonosítja melyik szervezetben dolgozott
- **Egyszerűség**: a backend minden kérést egyformán kezel - a token scope-ja határozza meg a kontextust

### 7.4 Többeszközös / többböngészős hozzáférés

**Biztonsági elv:** minden eszköz / böngésző saját **független tokennel** rendelkezik, saját `current_membership_id`-vel.

**Példa:**
```
Chrome (laptop):   token A → current_membership_id = 5 (XY Kft., Diszpécser)
Firefox (asztali): token B → current_membership_id = 8 (ABC Plaza, Admin)
iPhone mobile app: token C → current_membership_id = 12 (DEF Iroda, Felhasználó)
```

**Következmények:**
- A user mind a három eszközön **egyszerre lehet bejelentkezve**, mindegyik más szervezeti kontextusban
- Semmilyen keveredés nincs: minden eszközön a saját tokenje szerinti kontextusban fut
- A Chrome-ban létrehozott komment mindig az XY Kft.-hez kapcsolódik (membership_id=5)
- A Firefox-ban létrehozott komment az ABC Plaza-hoz (membership_id=8)
- A két kontextus adatai soha nem keverednek

**"Kijelentkezés minden eszközről"** (Profil oldalon):
- Minden személyes access tokent töröl a personal_access_tokens táblából
- Minden eszközön / böngészőben azonnali kijelentkeztetés a következő API hívásnál
- A 2FA, jelszó stb. változatlan marad

**Gyanús aktivitás esetén:**
- A user a Profil → Biztonság szekcióban megnézheti a "Aktív munkamenetek" listát:
  - Eszköz neve (ahogy bejelentkezéskor megadták)
  - Utolsó aktivitás
  - IP cím
  - Aktuális szervezet (current_membership_id alapján)
- Egyesével is tudja invalidálni a gyanús tokent

### 7.5 "Users" admin felület

A mostani "users" admin felület valójában **membership-listát** mutat (az adott szervezetben). A cím lehet "Tagok" vagy "Szervezeti felhasználók".

Az invite modal:
- Email beírása → rendszer ellenőrzi, létezik-e ilyen user
- Ha létezik: "Ez az email már regisztrálva van. Meghívjuk a szervezetbe?" + név (read-only) és szerepkör (szerkeszthető)
- Ha nem: új user létrehozása + tagság

A Törölt tagok megjelenítése toggle-lel a törölt tagságok is láthatók (áthúzott / szürkített), visszaállíthatók.

### 7.6 Profil oldal

A profil oldalon látszik:

**Személyes adatok** (user-szintűek, minden szervezetben érvényesek):
- Név, email, telefon, avatar
- Jelszó módosítás
- 2FA beállítás

**Beállítások:**
- Téma (light / dark / system)
- Színséma (teal / blue / purple / ...)
- Nyelv (HU / EN)
- Időzóna
- Lista elemek száma (10 / 25 / 50 / 100)
- Lockscreen időkorlát (1 / 3 / 5 / 10 / 15 / 30 perc / soha)
- Alapértelmezett szervezet (ha több tagsága van): dropdown vagy "Mindig kérdezzen meg"
- Értesítési preferenciák (email / push / belső)

**Tagságaim szekció:**
- Lista a szervezetekről, amelyekben tag
- Minden tagságnál: szervezet neve, szerepkör, utolsó aktivitás
- "Váltás" gomb → az adott szervezetbe lép át
- "Elhagyás" gomb → szervezet elhagyása (5.15 folyamat szerint)

**Biztonság szekció:**
- Aktív munkamenetek listája (eszköz, IP, utolsó aktivitás, szervezet)
- Egyesével token invalidálás
- "Kijelentkezés minden eszközről" gomb

---

## 8. Biztonsági szempontok

### 8.1 Rate limiting

- Login: 5 próbálkozás / perc / email
- Jelszó-visszaállítás: 3 próbálkozás / óra / email
- Email-változtatás megerősítés: 5 próbálkozás / óra / user

### 8.2 Session / Token biztonság

- A token tartalmazza a `current_membership_id`-t
- Szervezet-váltásnál régi token invalidálva, új generálva
- Membership deaktiválásakor / törlésekor az adott membership-re scope-olt tokenek invalidálva

### 8.3 Email-változtatás biztonsága

- Mindig mindkét email (régi + új) kap értesítést
- A régi emailen van a "visszavonás" link
- 24 órás token lejárati idő
- Az új email is ellenőrizve: ne legyen már regisztrálva

### 8.4 2FA

- Secret és recovery codes titkosítva tárolva
- Recovery code-ok csak egyszer használhatók
- Authenticator app időzített (TOTP, 30 sec ablak)

---

## 9. Migráció a jelenlegi modellről

**Döntés:** az összes meglévő adatot kidobjuk, tiszta lappal kezdünk. Csak egy szuper-admin kerül létrehozásra a seederben.

**Migráció lépései:**
1. Új migráció: `memberships` tábla létrehozás
2. Új migráció: `users` táblából `organization_id`, `role_id` oszlopok eltávolítása
3. Új migráció: `users` táblához `pending_email`, `email_change_token`, `email_change_sent_at` oszlopok hozzáadása
4. Új migráció: `group_user` → `group_membership` pivot átnevezés
5. Seeder átírása: csak platform szervezet + szuper-admin
6. Minden Eloquent model átírása
7. Minden API controller, policy, middleware átírása
8. Minden teszt átírása
9. Frontend: szervezet-választó, szervezet-váltó, users admin felület újraírása

---

## 10. Hibakezelés és UX

### 10.1 Közös hibaüzenetek

| Helyzet | Üzenet |
|---------|--------|
| Nincs aktív tagság | "Nincs aktív szervezeti tagságod. Fordulj a szervezeti adminisztrátorhoz!" |
| Tagság deaktiválva közben dolgozott | "A tagságod deaktiválva lett. Válassz másik szervezetet vagy jelentkezz ki." |
| Globális user deaktiválva | "A fiókod inaktív. Fordulj a szuper-adminisztrátorhoz." |
| Email már regisztrálva | "Ez az email cím már foglalt." |
| Email-változtatás: az új email foglalt | "Az új email cím már foglalt egy másik felhasználónál." |
| Meghívó lejárt | "Ez a meghívó lejárt. Kérj újat az adminisztrátortól." |
| Meghívó már elfogadva | "Ez a meghívó már fel lett használva." |
| 2FA kód hibás | "Érvénytelen ellenőrző kód. Próbáld újra." |

### 10.2 Toast értesítések

- Sikeres műveletek: zöld toast 4 sec
- Hibák: piros toast 6 sec
- Figyelmeztetések: narancs toast 4 sec
- Infó: teal toast 4 sec

---

## 11. Tesztelendő funkciók

**Bejelentkezés:**
- [ ] Sikeres login egy aktív tagsággal → dashboard
- [ ] Sikeres login több tagsággal → szervezet-választó
- [ ] Sikeres login 2FA-val → 2FA kód kérés
- [ ] Rossz jelszó → hiba
- [ ] Inaktív user → megfelelő hiba
- [ ] Nincs aktív tagság → megfelelő hiba
- [ ] Nem verified email → megfelelő hiba
- [ ] Rate limiting → 429

**Meghívás:**
- [ ] Új user meghívása → új user + tagság létrehozva
- [ ] Már létező user meghívása → csak tagság létrehozva
- [ ] Meghívó elfogadása új userrel → jelszó beállítás
- [ ] Meghívó elfogadása meglévő userrel → azonnali belépés
- [ ] Lejárt meghívó → hibaüzenet
- [ ] Már elfogadott meghívó → hibaüzenet

**Tagság műveletek:**
- [ ] Tagság deaktiválás (admin által)
- [ ] Tagság visszaaktiválás (admin által)
- [ ] Tagság törlése (admin által)
- [ ] Tagság visszaállítása (admin által)
- [ ] Meghívó újraküldése
- [ ] Önmaga nem törölhet / deaktiválhat

**Szervezet-váltás:**
- [ ] Több tagság esetén váltás
- [ ] Régi token invalidálódik
- [ ] Új szervezet adatai jelennek meg

**Profil:**
- [ ] Alapadatok módosítása
- [ ] Jelszó módosítás (aktuális jelszó megerősítés)
- [ ] Email-változtatás kezdeményezés → mindkét email kap értesítést
- [ ] Email-változtatás megerősítés (új emailen)
- [ ] Email-változtatás visszavonás (régi emailen)
- [ ] 2FA bekapcsolás
- [ ] 2FA kikapcsolás
- [ ] 2FA recovery code használat

---

## 12. Válaszok a nyitott kérdésekre

A kérdések megbeszélése megtörtént, az alábbi döntésekkel:

1. **Szervezet-választó oldal**: a `user_settings.default_organization_id` beállítás dönti el. Ha van beállítva, oda lép be automatikusan. Ha NULL, akkor a szervezet-választó jön. A user a Profil oldalon tudja beállítani.

2. **Tagság elhagyása**: a user tudja maga is elhagyni a szervezetet (5.15 folyamat). Jelszó + email megerősítés kell, az admin(ok) és szuper-admin értesítést / logot kap.

3. **User is_active automatikus false-ra állítása**: ha 0 aktív tagság marad → user.is_active = false, nem tud belépni, amíg egy admin újra nem hívja.

4. **Szervezet-váltáskor új token**: igen, biztonsági okokból mindig új tokent generálunk és a régit invalidáljuk. Nincs keveredési kockázat.

5. **Meghívó elfogadás után**: NEM vált automatikusan. Marad a user az aktuális szervezetben, de a tagságai frissülnek, és a szervezet-váltó dropdown-ban látja az új tagságot.

---

## Melléklet: Szótár

| Fogalom | Definíció |
|---------|-----------|
| **User** | Egy személy, egyetlen rekord a rendszerben, globálisan egyedi email alapján. |
| **Membership (Tagság)** | User és egy szervezet közötti kapcsolat, szerepkörrel együtt. |
| **Current Membership** | A user aktuálisan kiválasztott tagsága, amelyben "dolgozik". |
| **Pending invitation** | Függőben lévő meghívó, amit a user még nem fogadott el. |
| **Szervezet-váltás** | A user egyik tagságáról a másikra lép, nem kell kijelentkeznie. |
| **Globális deaktiválás** | A user.is_active = false, sehol nem tud belépni. |
| **Lokális deaktiválás** | Membership.is_active = false, csak az adott szervezetben inaktív. |
| **Soft delete** | Logikai törlés (deleted_at kitöltve), a rekord megmarad, visszaállítható. |
| **GDPR törlés** | A user anonimizálása, a kapcsolódó adatok megmaradnak, de a szerző "Törölt felhasználó". |
