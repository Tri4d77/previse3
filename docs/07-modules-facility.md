# 07 - Épület-üzemeltetési modulok specifikációja

> ⚠️ **Frissítve a Locations modul tervezése után.** A szakaszok 1.1–1.10 az
> ML1–ML3 fázisok finalizált scope-jét tartalmazzák. Az implementáció 3 lépésben:
> ML1 (alap CRUD + lista/kártya nézet), ML2 (hierarchia + kontaktok + címkék),
> ML3 (import/export + alaprajzok + térkép). Lásd `FUTURE_FEATURES.md` a
> későbbi v2-iterációkba átkerült funkciókért (interaktív alaprajz-hotspotok,
> szerepkör-mátrix admin UI).

## 1. Helyszínek modul

### 1.1 Áttekintés

A helyszínek modul az épületek, szintek és helyiségek **rugalmas hierarchikus**
nyilvántartását biztosítja. Ez az alap, amihez a bejelentések, feladatok,
hibajegyek, eszközök és karbantartási munkák köthetők. A karbantartó cégek
számára kulcsfontosságú, hogy helyszínenként lássák a feladataikat és az
előzményeket.

A helyszínek mindig az **előfizető (subscriber) szervezet** tulajdona —
az alvállalkozók (clients) nem tartanak külön helyszín-listát, csak a saját
membership-jükön keresztül férnek hozzá az előfizető helyszíneihez,
amennyiben jogosultságuk van rá.

### 1.2 Hierarchia (rugalmas)

```
Subscriber szervezet
└── Helyszín (Location) — Épület                    [KÖTELEZŐ]
    ├── Szint (Floor) — Emelet                      [opcionális]
    │   └── Helyiség (Room) — Iroda, raktár, stb.   [opcionális]
    └── Helyiség (Room) — szint nélkül              [opcionális, közvetlenül a Locationhöz]
```

**Az előfizető szabadsága**: dönti el, milyen részletességgel rögzít.
- **Csak Helyszín**: pl. egy garázs, kis kocka-épület
- **Helyszín + Szintek**: pl. többszintes irodaház szintenkénti megnevezéssel
- **Helyszín + Helyiségek (szint nélkül)**: pl. egyszintes raktár szobánként
- **Teljes hierarchia**: Helyszín + Szintek + Helyiségek

**Részletezhető szelektíven**: egy 3 szintes épület 1. szintje lehet
helyiségekkel részletezve, a 2-3. szint pedig csak szintként megjelenik.

### Bejelentések, feladatok, eszközök kapcsolódása

- `location_id` **kötelező** — mindig kell tudni, hol történt
- `floor_id`, `room_id` **opcionális**

Ezzel egy ticket bárhol nyitható: csak épülethez, épület+szinthez,
épület+szint+helyiséghez vagy épület+helyiséghez (szint nélkül).

### 1.3 Helyszín (Épület) adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| `code` | Nem | Egyedi épület-azonosító (org-szinten unique). Importnál a sheet-ek közti hivatkozáshoz. |
| `name` | Igen | Épület neve (pl. "Westend Irodaház B torony") |
| `address`, `city`, `zip_code` | Nem | Postai cím részei |
| `latitude`, `longitude` | Nem | GPS koordináták (térképes megjelenítéshez) |
| `type_id` | Nem | FK → `location_types` (org-specifikus, szabadon szerkeszthető katalógus) |
| `description` | Nem | Részletes leírás |
| `image_path` | Nem | Épület-fotó (1 db, max 5 MB JPEG/PNG) |
| `is_active` | Igen | TINYINT: `1` (default) = aktív, `0` = archív, `2` = megszűnt |

**Külön táblákban**:
- **`location_contacts`** — több külső kontakt (név, szerep-felirat, telefon, email)
  - Pl. „Műszaki vezető", „Portás", „Karbantartó 1", „Karbantartó 2"
- **`location_responsibles`** — saját szervezet membership-jei (sok-sok)
  - Pl. „Kovács János diszpécser felelős ezért az épületért"
- **`location_tag`** pivot — címkék (org-szintű katalógus, színes badge-ek)

### 1.4 Szint adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| `location_id` | Igen | FK → `locations` |
| `name` | Igen | Szint neve (pl. „Földszint", „1. emelet", „B2 pince") |
| `level` | Igen | Numerikus érték rendezéshez (pl. -2, -1, 0, 1, 2) |
| `description` | Nem | Megjegyzés |
| `floor_plan_path` | Nem | Alaprajz fájl (PNG/JPEG/PDF/SVG, max 20 MB). DWG-t a felhasználó előbb PDF-re/PNG-re menti. |

### 1.5 Helyiség adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| `location_id` | Igen | FK → `locations` (közvetlen, mindig kell) |
| `floor_id` | Nem | FK → `floors`, **NULL ha szint nélküli helyiség** |
| `name` | Igen | Helyiség neve (pl. „Nyitott iroda", „Szerver szoba") |
| `number` | Nem | Helyiségszám (pl. „B.2.15") |
| `type` | Nem | Szabad-szöveg típus, autocomplete-tel (iroda, raktár, folyosó, mosdó, műszaki, konyha) |
| `area_sqm` | Nem | Alapterület (m2) |
| `description` | Nem | Megjegyzés |
| `room_plan_path` | Nem | Helyiség-rajz fájl (PNG/JPEG/PDF/SVG, max 20 MB) |

### 1.5b Kontaktok (`location_contacts`)

| Mező | Leírás |
|------|--------|
| `location_id` | FK |
| `name` | Kontakt neve |
| `role_label` | Szerep felirat — szabad-szöveg, autocomplete-tel a már használt értékekből (pl. „Műszaki vezető", „Portás") |
| `phone`, `email`, `note` | Adatok |
| `sort_order` | Megjelenítési sorrend |

### 1.5c Felelősök (`location_responsibles`)

Pivot: `location_id`, `membership_id` — a saját szervezet kollégái közül több is felelős lehet egy helyszínért.

### 1.5d Címkék (`location_tags`)

Org-szintű katalógus. CRUD admin felületen kezelhető.

| Mező | Leírás |
|------|--------|
| `id`, `organization_id` | |
| `name` | Címke felirata (pl. „Premium ügyfél", „Belváros") |
| `color` | HEX színkód (pl. `#ef4444`) |
| `sort_order` | |

A helyszín kártyán a címkék színes csíkokkal jelennek meg (kártya-keret tetején).

### 1.5e Helyszín-típusok (`location_types`)

Org-specifikus katalógus. Default seederből: Iroda, Bevásárlóközpont, Lakóház, Ipari, Oktatási, Egészségügyi, Raktár, Egyéb.

### 1.6 Helyszín összefoglaló nézet

A helyszín részletnézete egy összefoglaló dashboard, amely egy helyen mutatja az épülettel kapcsolatos összes információt:

**Fejléc:**
- Épület neve, címe, típusa
- Kapcsolattartó adatok
- Szintek és helyiségek száma
- Eszközök száma

**Statisztikák (widgetek):**
- Nyitott bejelentések száma
- Folyamatban lévő feladatok száma
- Nyitott hibajegyek száma
- Esedékes karbantartások száma

**Listák (tab-okban):**
1. **Bejelentések** - Az épülethez tartozó bejelentések (szűrhető, lapozható)
2. **Feladatok** - Az épülethez tartozó feladatok
3. **Hibajegyek** - Az épülethez tartozó hibajegyek
4. **Eszközök** - Az épületben lévő eszközök (szintenként csoportosítva)
5. **Karbantartások** - Karbantartási napló és ütemterv
6. **Dokumentumok** - Épülethez kapcsolt dokumentumok
7. **Szerződések** - Épületre vonatkozó szerződések
8. **Szintek/Helyiségek** - Hierarchia kezelése

### 1.7 Karbantartó cégek nézete

A karbantartó cégek felhasználói elsősorban a helyszín-alapú nézetet használják:
- A bejelentkezés után a saját helyszíneik listáját látják
- Helyszínre kattintva az összefoglaló nézet jelenik meg
- Gyorsan elérhetők a kiosztott feladatok és bejelentések
- Szűrhetik az adott helyszín előzményeit (korábbi munkák, bejelentések)

### 1.8 Üzleti szabályok

- Helyszín törlése csak akkor lehetséges, ha nincs hozzá kötött aktív bejelentés, feladat vagy eszköz (soft-delete is lehetséges)
- Szint törlése csak üres szint esetén (nincs helyiség benne)
- Helyiség törlése csak akkor, ha nincs hozzá kötött eszköz
- **`is_active` állapotok**:
  - `1` (aktív): minden funkcióban megjelenik
  - `0` (archív): csak az „Archív megjelenítése" pipa-bekapcsolásával látszik, módosítható, de új ticketet/eszközt nem fogad
  - `2` (megszűnt): hasonló az archívhoz, de „lezárt" jelölés (egyszerűen nem visszaállítható)

### 1.9 Lista-nézetek

A helyszín-listán két nézet váltogatható:

- **Táblázatos** (default): név, cím, város, típus, címkék, statisztikák (szintek, helyiségek, eszközök száma), státusz
- **Kártyás**: nagyobb épület-fotó + név, cím, típus, címkék színes csíkkal a kártya felső szegélyén

A felhasználó beállíthatja a kedvelt nézetet (`user_settings.locations_view`), és a felületen is gyorsan váltható.

### 1.10 Import / Export

**Excel-sablon letöltése** a Locations oldalon. A sablon 3 sheet-et tartalmaz:

1. **`Locations`** — `code`, `name`, `address`, `city`, `zip_code`, `latitude`, `longitude`, `type`, `description`
2. **`Floors`** — `location_code` (referencia!), `name`, `level`, `description`
3. **`Rooms`** — `location_code` (referencia!), `floor_name` (opcionális, ha üres = szint nélküli helyiség), `name`, `number`, `type`, `area_sqm`

A `code` mező az **egyedi kapcsolódási kulcs** a sheet-ek között — a sablon első sorában kommenttel jelezzük: „Ez az egyedi azonosító, amit a Floors és Rooms sheet-ek hivatkoznak. Ne hagyd üresen!"

**Import flow**:
1. User feltölti a fájlt
2. Backend validálja sorról sorra (kötelező mezők, formátumok, FK-referenciák)
3. **Globális ütközéskezelési szabály választása** (ha van már létező épület ugyanazon `code` vagy `name` alapján):
   - Felülírom a meglévőt
   - Kihagyom (nem importálom)
   - Új példányt hozok létre (átnevezve)
4. Az ütközéseket egy táblázatban listázzuk, ahol soronként **felülírható a globális szabály**
5. Validációs eredmény: zöld sorok = OK, piros = hibás (hibaüzenettel)
6. User dönthet: „Csak a zöldeket importálom" vagy „Mégse"

**Export**:
- **Teljes lista** (összes épület + szint + helyiség 3 sheet-en) — főmenü gombbal
- **Szűrt export**: a szűrt lista, **mélység-választással** („csak épület", „épület+szint", „teljes")

### 1.11 Felület-sajátosságok

- **Térképes megjelenítés**: ha van `latitude/longitude`, **Leaflet + OpenStreetMap** a Locations oldal egy térkép-tabján vagy a részletnézetben.
- **Alaprajzok**: feltölthetők szintenként és helyiségenként. Megjelenítés natív (PNG/JPEG/SVG) vagy `vue-pdf-embed`-del (PDF). Zoom és pannelás `panzoom` library-vel. Az **interaktív alaprajzi hotspot-rendszer** (kattintható területek a szintrajzon, melyek a helyiségekre ugranak) **későbbi fázis (FUTURE_FEATURES.md)**.
- **Kontakt-kártya tooltip**: bárhol megjelenik egy kontakt neve, **kattintásra** (NEM hover) kis pop-overben:
  - Név, szerep
  - Telefon (másolás-gomb)
  - Email (másolás-gomb)
  - Megjegyzés
  - „Hívás" / „Email küldése" link (`tel:` / `mailto:`)
- A szervezet részére egy globális kontakt-elérhetőséget is biztosítunk, hogy a felhasználók látják a kollégák telefonszámát/emailjét — **lásd a Users modul későbbi bővítését**.

### 1.12 Permissions

```
locations.read                  — Helyszín-lista és részletek
locations.create                — Új helyszín
locations.update                — Helyszín módosítása
locations.delete                — Helyszín törlése
locations.manage_floors         — Szintek kezelése
locations.manage_rooms          — Helyiségek kezelése
locations.manage_contacts       — Kontaktok kezelése
locations.manage_responsibles   — Felelősök hozzárendelése
locations.manage_tags           — Címke-katalógus szerkesztése
locations.manage_types          — Típus-katalógus szerkesztése
locations.manage_floor_plans    — Alaprajzok feltöltése
locations.import                — Excel/CSV import
locations.export                — Excel/CSV export
```

**Default szerepkör-mátrix** (a `PermissionSeeder`-ben):

| Permission | admin | dispatcher | user | recorder | maintainer |
|------------|-------|------------|------|----------|------------|
| `read` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `create` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `update` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `delete` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `manage_floors`, `manage_rooms`, `manage_floor_plans` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `manage_contacts`, `manage_responsibles` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `manage_tags`, `manage_types` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `import` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `export` | ✅ | ✅ | ❌ | ❌ | ❌ |

A szervezeti admin **felüldefiniálhatja** ezt a saját szervezetére a Roles admin UI-n keresztül (jelenleg placeholder, **M12 fázisban épül ki** — `FUTURE_FEATURES.md`).

### 1.13 Implementálási fázisok

A modul méretére tekintettel **3 lépésben** készül:

- **ML1 — Alap CRUD + lista nézet**
  - Migrations + modellek + permission seeder
  - LocationsController + endpointok (CRUD + status)
  - LocationTypes management (org-szintű katalógus)
  - Lista nézet (táblázat) + Kártya nézet
  - Helyszín-fotó feltöltés (image_path)
  - HU/EN i18n
  - Pest tesztek
- **ML2 — Hierarchia + kontaktok + címkék**
  - Floors + Rooms CRUD (a rugalmas hierarchia szerint)
  - Location contacts + responsibles
  - Tags + LocationTags
  - ContactCard tooltip komponens (bárhol használható)
  - Helyszín-részletes oldal (tabokkal)
- **ML3 — Import/export + alaprajzok + térkép**
  - Excel sablon-letöltés (3 sheet-tel)
  - Import-validáció UI (globális szabály + per-row override)
  - Export szűrt + teljes (mélység-választással)
  - Alaprajz feltöltés (szint + helyiség)
  - Zoom megjelenítés (panzoom + vue-pdf-embed)
  - Leaflet térkép

---

## 2. Eszközök modul

### 2.1 Áttekintés

Az eszközök modul az épületekben lévő berendezések, gépek, műszaki eszközök nyilvántartását biztosítja. Minden eszközhöz rögzíthető a pontos helyszíne, állapota, garancia és szerviz-információi, valamint QR kóddal azonosítható.

### 2.2 Eszköz adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Név | Igen | Eszköz megnevezése (pl. "B-LIFT-03") |
| Típus | Igen | Eszköz típus (asset_types táblából) |
| Helyszín | Nem | Épület |
| Szint | Nem | Emelet |
| Helyiség | Nem | Helyiség |
| Gyártó | Nem | Gyártó neve |
| Modell | Nem | Modell megjelölés |
| Sorozatszám | Nem | Egyedi sorozatszám |
| Leltári szám | Nem | Belső leltári azonosító |
| Állapot | Igen | Működik, Hibás, Karbantartás alatt, Selejtezve |
| Üzembe helyezés | Nem | Üzembe helyezés dátuma |
| Garancia lejárata | Nem | Garancia vége |
| QR kód | Nem | Egyedi QR azonosító (automatikusan generálható) |
| Megjegyzések | Nem | Szabad szöveges jegyzet |
| Egyéni adatok | Nem | Típus-specifikus JSON adatok |

### 2.3 Eszköz típusok

Az eszköz típusok adminisztrálhatók. Minden típushoz opcionálisan extra mezők definiálhatók JSON séma formában.

**Példa típusok:**

| Típus | Ikon | Típus-specifikus mezők |
|-------|------|------------------------|
| Lift | elevator | Kapacitás (fő), emeletszám, gyártási év |
| Mozgólépcső | escalator | Hossz, sebesség |
| Klíma | ac_unit | Teljesítmény (kW), hűtőközeg típus |
| Tűzjelző | fire | Érzékelő típus, zóna |
| Vízszivattyú | water | Teljesítmény, nyomás |
| Generátor | power | Teljesítmény (kVA), üzemanyag típus |
| Kapu/sorompó | door | Típus (tolókapu, forgóajtó, stb.) |
| Kamera | camera | Felbontás, típus |

### 2.4 Állapotkezelés

**Állapotok:**

| Állapot | Szín | Leírás |
|---------|------|--------|
| Működik (operational) | Zöld | Normálisan üzemel |
| Hibás (faulty) | Piros | Nem működik, javításra vár |
| Karbantartás alatt (maintenance) | Narancs | Tervezett vagy eseti karbantartás |
| Selejtezve (decommissioned) | Szürke | Kivonva az üzemeltetésből |

**Állapotváltozás szabályok:**
```
Működik ──► Hibás
Működik ──► Karbantartás alatt
Működik ──► Selejtezve
Hibás ──► Karbantartás alatt
Hibás ──► Selejtezve
Karbantartás alatt ──► Működik
Karbantartás alatt ──► Hibás
Karbantartás alatt ──► Selejtezve
```

Minden állapotváltozás naplózódik az `asset_status_changes` táblába (ki, mikor, miről mire, megjegyzés).

### 2.5 QR kód rendszer

**QR kód generálás:**
1. Admin vagy diszpécser kéri a QR kód generálását az eszköz adatlapján
2. Rendszer generál egy egyedi azonosítót (pl. `PV-AST-00015`)
3. QR kód kép generálása (PNG, nyomtatható)
4. QR kód tartalmazza az alkalmazás URL-jét az eszköz azonosítóval: `https://app.previse.hu/assets/qr/PV-AST-00015`

**QR kód beolvasás (mobil app):**
1. Felhasználó megnyitja a QR szkenner funkciót a mobil appban
2. Beolvassa az eszközre ragasztott QR kódot
3. Az app lekéri az eszköz adatait az API-tól
4. Megjelenik az eszköz adatlapja:
   - Alapadatok (név, típus, helyszín, állapot)
   - Utolsó státuszváltozások
   - Aktív bejelentések/hibajegyek az eszközre
   - Karbantartási előzmények
5. Gyors műveletek:
   - **"Új bejelentés"** gomb: automatikusan kitölti az eszköz és helyszín mezőket
   - **"Hiba bejelentés"** gomb: hibajegy létrehozása az eszközhöz
   - **"Állapot módosítás"** gomb: gyors állapotváltás

**QR kód beolvasás (web):**
- A web URL-re navigálva bejelentkezés után az eszköz adatlap jelenik meg
- Nem bejelentkezett felhasználó a login oldalra irányítódik, utána automatikusan az eszközre

### 2.6 Eszköz részletnézet

Az eszköz adatlap tartalmazza:

**Fejléc:**
- Eszköz neve, típusa, ikon
- Állapot badge
- QR kód (nyomtatható)

**Adatok tab:**
- Összes alapadat
- Típus-specifikus adatok
- Garancia és szerviz info

**Előzmények tab:**
- Bejelentések az eszközre
- Hibajegyek az eszközre
- Állapotváltozás-történet

**Karbantartás tab:**
- Ütemezett karbantartások
- Karbantartási napló

### 2.7 Üzleti szabályok

- Eszköz törlése soft delete (az előzmények megmaradnak)
- Selejtezett eszköz nem rendelhető új bejelentéshez
- Lejárt garanciájú eszközök listázhatók (szűrővel)
- QR kód egyszer generálva nem változik (az eszköz törlése sem törli a QR kódot)
- Eszköz helyszíne módosítható (pl. áthelyezés másik épületbe) - a változás naplózódik

---

## 3. Karbantartás modul

### 3.1 Áttekintés

A karbantartás modul a tervezett és eseti karbantartási munkák kezelését biztosítja. Ütemtervek definiálhatók eszközönként vagy helyszínenként, és a rendszer automatikusan generálja a karbantartási feladatokat. Az elvégzett munkák naplózhatók.

### 3.2 Karbantartási ütemterv

**Ütemterv adatok:**

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Megnevezés | Igen | Karbantartás neve (pl. "Lift fékhenger ellenőrzés") |
| Leírás | Nem | Részletes teendők leírása |
| Eszköz | Nem* | Melyik eszközre vonatkozik |
| Helyszín | Nem* | Melyik helyszínre vonatkozik |
| Gyakoriság | Igen | Milyen gyakran kell elvégezni |
| Következő esedékesség | Igen | Mikor esedékes legközelebb |
| Felelős | Nem | Ki a felelős az elvégzésért |
| Aktív | Igen | Aktív-e az ütemterv |

*Eszköz VAGY helyszín megadása kötelező (legalább az egyik)

**Gyakoriság opciók:**

| Gyakoriság | Leírás | Intervallum |
|------------|--------|-------------|
| daily | Naponta | 1 nap |
| weekly | Hetente | 7 nap |
| monthly | Havonta | ~30 nap |
| quarterly | Negyedévente | ~90 nap |
| semi_annual | Félévente | ~180 nap |
| annual | Évente | ~365 nap |
| custom | Egyéni | interval_days naponta |

### 3.3 Automatikus feladat-generálás

A Laravel Scheduler naponta ellenőrzi az esedékes karbantartásokat:

1. Lekéri az összes aktív ütemtervet ahol `next_due_date <= ma`
2. Minden esedékes ütemtervhez:
   a. Létrehoz egy új feladatot (Tasks modul) a sablon adataival
   b. A feladat automatikusan a felelős felhasználóhoz rendelődik
   c. A feladat helyszíne és eszköze az ütemtervből kerül kitöltésre
   d. Email és push értesítés a felelősnek
3. Frissíti a `next_due_date` mezőt a következő esedékességre
4. Rögzíti a `last_performed_at` időpontot

### 3.4 Karbantartási napló

Az elvégzett karbantartási munkák naplózása:

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Megnevezés | Igen | Munka megnevezése |
| Leírás | Nem | Elvégzett munkák részletezése |
| Ütemterv | Nem | Kapcsolódó ütemterv (NULL = ad-hoc munka) |
| Eszköz | Nem | Melyik eszközön |
| Helyszín | Nem | Melyik helyszínen |
| Elvégezte | Igen | Ki végezte (felhasználó) |
| Időpont | Igen | Mikor történt |
| Időtartam | Nem | Mennyi ideig tartott (perc) |
| Költség | Nem | Munka költsége (Ft) |
| Felhasznált alkatrészek | Nem | Szöveges lista |
| Csatolmányok | Nem | Fotók, dokumentumok a munkáról |

### 3.5 Naplózás folyamat

1. Karbantartó megnyitja az esedékes karbantartási feladatot
2. Elvégzi a munkát
3. Rögzíti a napló bejegyzést (leírás, időtartam, költség, alkatrészek)
4. Opcionálisan fotókat csatol
5. Készre jelöli a feladatot
6. A rendszer:
   - Menti a napló bejegyzést
   - Ha az eszköz "Karbantartás alatt" állapotú volt, visszaállítja "Működik"-re
   - Frissíti az ütemterv `last_performed_at` mezőjét

### 3.6 Riportok

A karbantartás modulból az alábbi riportok generálhatók:

**Helyszín-alapú riport:**
- Adott helyszínen adott időszakban elvégzett karbantartások
- Összes ráfordított idő és költség
- Felhasznált alkatrészek listája

**Eszköz-alapú riport:**
- Adott eszköz karbantartási előzményei
- Költségek összesítése
- Következő esedékes karbantartás

**Összefoglaló riport:**
- Karbantartási feladatok teljesítési aránya (elvégzett / esedékes)
- Lejárt karbantartások listája
- Költségek helyszínenként/eszközönként

### 3.7 Figyelmeztetések

A rendszer automatikusan figyelmeztet:

| Esemény | Mikor | Kinek |
|---------|-------|-------|
| Karbantartás esedékes | next_due_date elérésekor | Felelős + diszpécser |
| Karbantartás lejárt | next_due_date + 1 nap | Felelős + admin |
| Sok lejárt karbantartás | Heti összefoglaló | Admin |

### 3.8 Üzleti szabályok

- Ütemterv deaktiválása nem törli a már generált feladatokat
- Ad-hoc karbantartás (ütemterv nélküli) is naplózható
- Karbantartási napló nem törölhető, csak módosítható
- Költség és időtartam nem kötelező, de a riportokhoz ajánlott
- Az eszköz állapota automatikusan frissülhet karbantartás után (konfigurálható)
