# 08 - Kiegészítő modulok specifikációja

## 1. Szerződés és alvállalkozó kezelés modul

### 1.1 Áttekintés

A szerződések és alvállalkozók modulja a karbantartási, felújítási és szolgáltatási szerződések nyilvántartását biztosítja. Segít a szerződések lejáratának figyelésében és az alvállalkozó cégek adatainak kezelésében.

### 1.2 Alvállalkozó (Contractor) adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Cégnév | Igen | Alvállalkozó cég neve |
| Adószám | Nem | Adószám |
| Cím | Nem | Székhely cím |
| Kapcsolattartó neve | Nem | Kapcsolattartó személy |
| Kapcsolattartó telefon | Nem | Telefon |
| Kapcsolattartó email | Nem | Email |
| Szakterület | Nem | Specializáció (pl. villamosság, klíma, lift) |
| Megjegyzések | Nem | Belső feljegyzések |
| Aktív | Igen | Aktív-e a partnerkapcsolat |

### 1.3 Szerződés (Contract) adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Megnevezés | Igen | Szerződés neve |
| Szerződés szám | Nem | Belső vagy hivatalos szerződésszám |
| Alvállalkozó | Igen | Melyik céghez tartozik |
| Típus | Nem | Típus (karbantartás, felújítás, szolgáltatás, bérlet, stb.) |
| Leírás | Nem | Szerződés tartalmának leírása |
| Kezdés dátum | Igen | Érvényesség kezdete |
| Lejárat dátum | Nem | Érvényesség vége (határozatlan idejű is lehet) |
| Összeg | Nem | Szerződés értéke |
| Pénznem | Nem | Alapértelmezett: HUF |
| Automatikus megújítás | Nem | Automatikusan megújul-e lejáratkor |
| Figyelmeztetés | Nem | Lejárat előtt hány nappal figyelmeztessen (alapértelmezett: 30) |
| Státusz | Igen | Tervezet, Aktív, Lejárt, Megszűnt |

### 1.4 Szerződés-helyszín kapcsolat

Egy szerződés több helyszínre is vonatkozhat, és egy helyszínhez több szerződés is tartozhat.

**Példa:**
- "Lift karbantartási szerződés" → A épület, B épület
- "A épület" → Lift karbantartás, Klíma karbantartás, Takarítás

### 1.5 Szerződés-eszköz kapcsolat

Hasonlóan, szerződések eszközökhöz is köthetők.

**Példa:**
- "Lift karbantartási szerződés" → A-LIFT-01, A-LIFT-02, B-LIFT-01

### 1.6 Kapcsolódó dokumentumok

A szerződésekhez kapcsolódó dokumentumok a Dokumentumtár modulból érhetők el:
- Aláírt szerződés (PDF)
- Mellékletek, árajánlatok
- Módosítások

### 1.7 Lejárati figyelmeztetés

A rendszer automatikusan figyelmeztet a lejáró szerződésekre:

| Esemény | Mikor | Kinek |
|---------|-------|-------|
| Lejárat közeledik | warning_days nappal előtte | Admin |
| Szerződés lejárt | Lejárat napján | Admin |
| Heti összefoglaló | Hétfőnként | Admin (ha van lejáró) |

### 1.8 Üzleti szabályok

- Szerződés törlése nem lehetséges, csak "Megszűnt" státuszra állítható
- Lejárt szerződés automatikusan "Lejárt" státuszra kerül (scheduler)
- Auto-megújulásos szerződésnél a rendszer figyelmeztet, de nem módosítja automatikusan a dátumot
- Alvállalkozó törlése csak akkor lehetséges, ha nincs aktív szerződése

---

## 2. Dashboard és riportok modul

### 2.1 Áttekintés

A dashboard a rendszer fő áttekintő felülete. Widgetekből áll, amelyek a legfontosabb KPI-kat és statisztikákat mutatják. A riportok részletesebb adatelemzést tesznek lehetővé, exportálási lehetőséggel.

### 2.2 Fő dashboard

A bejelentkezés utáni kezdőoldal. Tartalmazza:

**Felső sáv - Gyors összefoglaló:**
- Nyitott bejelentések száma
- Folyamatban lévő feladatok száma
- SLA-t túllépett bejelentések száma (piros kiemelés)
- Esedékes karbantartások száma
- Lejáró szerződések száma

**Bal oszlop - Diagramok:**
- Bejelentések száma az elmúlt 30 napban (vonal diagram)
- Bejelentések megoszlása kategória szerint (torta diagram)
- Bejelentések megoszlása prioritás szerint (oszlop diagram)

**Jobb oszlop - Listák:**
- Legutóbbi bejelentések (utolsó 5)
- Esedékes feladatok (legközelebbi 5)
- Aktivitás stream (utolsó 10 bejegyzés)

**Alsó sáv:**
- SLA teljesítmény (%) - az elmúlt 30 nap
- Átlagos megoldási idő
- Aktív projektek haladása (progress bar-ok)

### 2.3 Helyszín-specifikus dashboard

Egy adott helyszín kiválasztásakor elérhető dedikált nézet:

- Helyszín alapadatok
- Nyitott bejelentések, feladatok, hibajegyek az adott helyszínen
- Eszközök állapot-összesítő (hány működik, hány hibás)
- Karbantartási naptár (esedékes munkák)
- Az adott helyszín legutóbbi aktivitásai

### 2.4 KPI-k (Key Performance Indicators)

| KPI | Számítás | Célérték |
|-----|----------|----------|
| Átlagos válaszidő | Bejelentés létrehozása → felelős kijelölése | SLA-tól függ |
| Átlagos megoldási idő | Bejelentés létrehozása → megoldás | SLA-tól függ |
| SLA teljesítés (%) | Időben megoldott / összes bejelentés | > 90% |
| Nyitott bejelentések száma | Nem lezárt bejelentések | Csökkenő trend |
| Bejelentések/nap | Napi beérkező bejelentések átlaga | Stabil/csökkenő |
| Feladat teljesítési arány | Időben elvégzett / összes feladat | > 85% |
| Karbantartás teljesítési arány | Elvégzett / esedékes karbantartások | > 95% |

### 2.5 Riportok

**Bejelentés riport:**
- Szűrhető: időszak, kategória, prioritás, helyszín, felelős, státusz
- Tartalmazza: hivatkozási szám, cím, kategória, prioritás, státusz, bejelentő, felelős, létrehozás, megoldás dátuma, SLA teljesítés
- Exportálás: Excel (XLSX), PDF

**Feladat riport:**
- Szűrhető: időszak, státusz, felelős, helyszín, projekt
- Tartalmazza: feladat neve, felelős(ök), határidő, státusz, becsült/tényleges órák
- Exportálás: Excel, PDF

**Karbantartási riport:**
- Szűrhető: időszak, helyszín, eszköz, felelős
- Tartalmazza: munka megnevezése, helyszín, eszköz, elvégző, időtartam, költség
- Összesítő: teljes ráfordított idő, teljes költség
- Exportálás: Excel, PDF

**Eszköz riport:**
- Szűrhető: típus, helyszín, állapot, garancia státusz
- Tartalmazza: eszköz neve, típus, helyszín, állapot, garancia, utolsó karbantartás
- Exportálás: Excel, PDF

### 2.6 Exportálás implementáció

- **Excel**: Laravel Excel package (Maatwebsite/Excel)
- **PDF**: DomPDF vagy SnappyPDF package
- A generált fájl letöltési linkként érkezik az API válaszban
- Nagy riportoknál queue-ba kerülhet a generálás, és értesítés érkezik elkészültekor

### 2.7 Üzleti szabályok

- Dashboard adatok cache-elhetők (5 perc TTL)
- Felhasználó csak a saját jogosultságainak megfelelő adatokat látja
- Exportálás maximális sorszáma: 10.000 (teljesítmény miatt)
- Karbantartó szerepkörű felhasználó csak a saját helyszíneinek dashboard-ját látja

---

## 3. Keresés modul

### 3.1 Globális keresés

A fejléc sávban elhelyezett keresőmező, ami az összes modulban keres egyszerre.

**Keresett entitások:**
- Bejelentések (cím, leírás, hivatkozási szám)
- Feladatok (cím, leírás)
- Projektek (cím, leírás)
- Hibajegyek (cím, leírás, hivatkozási szám)
- Javaslatok (tárgy, leírás)
- Eszközök (név, sorozatszám, leltári szám)
- Helyszínek (név, cím)
- Dokumentumok (cím, leírás)

**Működés:**
1. Felhasználó gépel a keresőbe (minimum 3 karakter)
2. Debounce: 300ms várakozás a gépelés után
3. API hívás: `GET /api/v1/search?q={query}&types=tickets,tasks,assets`
4. Eredmények csoportosítva jelennek meg (modulonként max 5 találat)
5. "Összes találat" linkek a modulon belüli teljes kereséshez

**Backend implementáció:**
- MySQL LIKE keresés a releváns mezőkön
- FULLTEXT index a title és description mezőkön (jobb teljesítmény)
- Későbbi fejlesztés: Meilisearch integráció (gyorsabb, typo-toleráns)

### 3.2 Modulon belüli szűrők

Minden modul lista nézeténél részletes szűrők érhetők el:

**Közös szűrők:**
- Szabad szöveges keresés (search)
- Dátum tartomány (date_from, date_to)
- Rendezés (sort + order)
- Lapozás (page, per_page)

**Modul-specifikus szűrők:**
Részletezve az egyes modul specifikációkban és az API dokumentációban.

### 3.3 Mentett szűrők

A felhasználók elmenthetik a gyakran használt szűrő-kombinációikat:

- Szűrő neve (pl. "A épület nyitott bejelentések")
- Modul (melyik modulhoz tartozik)
- Szűrő paraméterek (JSON)
- Alapértelmezett jelölés (az adott modulban ez legyen az indulóállapot)

**Megjelenés:**
- Modulon belüli szűrősáv felett legördülő menüben
- Gyors váltás mentett szűrők között

### 3.4 Üzleti szabályok

- Keresés eredményei jogosultság-szűrtek (a felhasználó csak azt látja, amihez joga van)
- Mentett szűrők felhasználóhoz kötöttek, nem megoszthatók
- Alapértelmezett szűrő modulonként egy lehet

---

## 4. Aktivitás stream modul

### 4.1 Áttekintés

Az aktivitás stream egy egységes tevékenység-napló, amely minden modulból gyűjti a változásokat. Segít a felhasználóknak nyomon követni, mi történt a rendszerben.

### 4.2 Naplózott események

| Modul | Események |
|-------|----------|
| Tickets | Létrehozás, státuszváltás, felelős kijelölés, hozzászólás, csatolmány, lezárás |
| Tasks | Létrehozás, kiosztás, készre jelölés, elhalasztás |
| Projects | Létrehozás, státuszváltás, tag hozzáadás/eltávolítás, mérföldkő teljesítés |
| Issues | Létrehozás, státuszváltás, felelős kijelölés, megoldás |
| Suggestions | Létrehozás, elbírálás, szavazat |
| Assets | Létrehozás, állapotváltozás |
| Maintenance | Karbantartás elvégzése, napló bejegyzés |
| Documents | Feltöltés, új verzió |
| Users | Regisztráció, aktiválás/deaktiválás |

### 4.3 Activity log rekord

Minden bejegyzés tartalmazza:
- Ki végezte a műveletet (user)
- Mit csinált (action)
- Min végezte (subject_type + subject_id)
- Mikor (created_at)
- Szöveges leírás (description)
- Extra adatok (properties JSON)

### 4.4 Megjelenés

**Dashboard widget:**
- Utolsó 10 aktivitás
- Kompakt nézet (ikon + szöveg + idő)

**Teljes aktivitás oldal:**
- Időrendi lista (legújabb elől)
- Szűrhető:
  - Modul szerint (tickets, tasks, stb.)
  - Felhasználó szerint
  - Helyszín szerint
  - Dátum tartomány
- Infinite scroll vagy lapozás

### 4.5 Üzleti szabályok

- Activity log bejegyzések nem törölhetők és nem módosíthatók
- A felhasználó csak a jogosultságainak megfelelő aktivitásokat látja
- Activity log retention: 365 nap (régebbi bejegyzések archiválhatók)

---

## 5. Belső üzenetek modul

### 5.1 Áttekintés

Egyszerű belső üzenetküldő rendszer a felhasználók között. Az üzenetek entitáshoz is köthetők (pl. bejelentéssel kapcsolatos kérdés).

### 5.2 Üzenet adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Címzett(ek) | Igen | Egy vagy több felhasználó |
| Tárgy | Igen | Üzenet tárgya |
| Szöveg | Igen | Üzenet tartalma |
| Kapcsolódó entitás | Nem | Entitás típus + ID (pl. Ticket #1234) |

### 5.3 Funkciók

- Üzenet küldése egy vagy több címzettnek
- Üzenetek listája (beérkezett + küldött)
- Olvasatlan üzenetek jelölése és számlálója
- Üzenet olvasottnak jelölése
- Entitáshoz kötött üzenet: a kapcsolódó entitásra kattintva megnyílik a részletnézet

### 5.4 Értesítések

- Új üzenet érkezésekor push értesítés
- Olvasatlan üzenetek száma a fejléc csengő ikon mellett

### 5.5 Üzleti szabályok

- Üzenetek nem törölhetők (audit trail célból)
- Üzenet csak a szervezeten belüli felhasználóknak küldhető
- Nincs válasz/szál funkció (ez nem chat, csak értesítés jellegű üzenetküldés)
- Ha szál/válasz funkció szükséges, az a hozzászólás rendszeren (Comments) keresztül valósítandó meg az egyes entitásokon belül
