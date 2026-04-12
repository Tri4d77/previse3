# 06 - Üzleti modulok specifikációja

## 1. Bejelentések (Tickets) modul

### 1.1 Áttekintés

A bejelentés modul a rendszer központi eleme. Ide érkeznek az üzemeltetési bejelentések, meghibásodások, karbantartási igények. A bejelentések kategorizálhatók, priorizálhatók, helyszínhez és eszközhöz köthetők. SLA határidők figyelése és eszkaláció automatikusan működik.

### 1.2 Entitások

- **Ticket** - A bejelentés maga
- **TicketCategory** - Bejelentés kategóriák (pl. Villamos, Gépészet, Lift, Takarítás)
- **TicketStatus** - Státuszok (konfigurálható workflow)
- **TicketReaction** - Reakciók/intézkedések típusai
- **Comment** - Hozzászólások (polimorf)
- **Timeline** - Változás-napló (polimorf)
- **Attachment** - Csatolmányok (polimorf)
- **Follower** - Követők (polimorf)
- **SlaConfig** - SLA szabályok

### 1.3 Bejelentés életciklusa

```
┌─────┐     ┌────────────┐     ┌──────────┐     ┌────────┐
│ Új  │────►│ Folyamatban│────►│ Megoldva │────►│ Lezárt │
└──┬──┘     └─────┬──────┘     └────┬─────┘     └────────┘
   │              │                  │
   │              ▼                  │
   │        ┌───────────┐           │
   │        │ Várakozik  │──────────┘
   │        └───────────┘
   │
   ▼
┌────────┐
│ Törölt │
└────────┘
```

**Státuszok (alapértelmezett, szervezetenként testre szabható):**

| Státusz | Slug | Leírás | Szín |
|---------|------|--------|------|
| Új | new | Újonnan beérkezett bejelentés | Kék |
| Folyamatban | in_progress | Felelős dolgozik rajta | Narancs |
| Várakozik | waiting | Külső válaszra/alkatrészre vár | Sárga |
| Megoldva | resolved | Megoldás megtörtént, ellenőrzésre vár | Zöld |
| Lezárt | closed | Végleges lezárás | Szürke |
| Törölt | cancelled | Visszavont/érvénytelen bejelentés | Piros |

### 1.4 Prioritások

| Prioritás | Szín | Leírás |
|-----------|------|--------|
| Alacsony (low) | Zöld | Nem sürgős, tervezett munkák |
| Közepes (medium) | Kék | Normál bejelentések |
| Magas (high) | Narancs | Sürgős, de nem kritikus |
| Kritikus (critical) | Piros | Azonnali beavatkozás szükséges |

### 1.5 SLA kezelés

**Konfiguráció:**
- SLA szabályok kategória és/vagy prioritás alapján definiálhatók
- Válaszidő (response_hours): mennyi időn belül kell felelőst kijelölni
- Megoldási idő (resolution_hours): mennyi időn belül kell megoldani
- Figyelmeztetési küszöb (warning_percent): a határidő hány %-ánál küld figyelmeztetést
- Eszkalációs felhasználó: kihez eszkalál a rendszer

**Működés:**
1. Bejelentés létrehozásakor a rendszer kiszámítja az SLA határidőt a kategória és prioritás alapján
2. A `warning_percent` elérésekor email és push értesítés a felelősnek és az eszkalációs személynek
3. Határidő lejártakor automatikus eszkaláció (értesítés + opcionális felelős-átirányítás)

**Munkaórák:** Kezdetben 24/7, később konfigurálható munkaidő (pl. H-P 8:00-17:00)

### 1.6 Reakciók/Intézkedések

Az adminisztrátornak lehetősége van előre definiált reakció/intézkedés típusokat létrehozni, amiket a bejelentésekhez lehet rendelni. Például:
- Helyszíni szemle szükséges
- Alkatrész rendelés
- Alvállalkozó értesítve
- Garanciális javítás kezdeményezve

Egy bejelentéshez több reakció is rendelhető, mindegyikhez megjegyzés fűzhető.

### 1.7 Üzleti szabályok

- Bejelentést bárki létrehozhat (megfelelő jogosultsággal)
- Felelős csak a szervezet tagja lehet
- Státusz csak előre definiált irányokba változtatható (workflow)
- Törlés csak admin jogosultsággal
- Lezárt bejelentés újranyitható (Lezárt → Folyamatban)
- A bejelentő automatikusan követő lesz
- A felelős is automatikusan követő lesz

---

## 2. Feladatok (Tasks) modul

### 2.1 Áttekintés

A feladatok modul a munkaszervezést támogatja. A feladatok önállóan vagy projekthez rendelve létezhetnek. Támogatja az ismétlődő feladatokat is (pl. heti takarítás, havi karbantartás).

### 2.2 Feladat típusok

- **Egyedi feladat**: Egyszeri, konkrét feladat
- **Ismétlődő feladat**: Automatikusan újragenerálódó feladat
- **Projekt feladat**: Projekthez rendelt feladat

### 2.3 Feladat életciklusa

```
┌─────┐     ┌────────────┐     ┌──────┐
│ Új  │────►│ Folyamatban│────►│ Kész │
└──┬──┘     └─────┬──────┘     └──────┘
   │              │
   │              ▼
   │        ┌───────────┐
   └───────►│ Elhalasztva│
            └───────────┘
```

### 2.4 Ismétlődő feladatok

**Gyakoriság opciók:**

| Típus | Leírás | Példa |
|-------|--------|-------|
| daily | Naponta | Minden nap |
| weekly | Hetente | Minden hétfőn |
| monthly | Havonta | Minden hónap 1-jén |
| custom | Egyéni | Minden 14 napban |

**Működés:**
1. Sablon feladat létrehozása az ismétlődés beállításával
2. Laravel Scheduler (cron) ellenőrzi naponta az esedékes ismétlődéseket
3. Ha a `next_due_date` elérkezik, új feladat-példány generálódik a sablonból
4. Az új feladat megkapja a sablon összes tulajdonságát (leírás, felelős, helyszín)
5. A `next_due_date` frissítődik a következő esedékességre

**CPanel cron konfiguráció:**
```
* * * * * cd /home/user/previse-api && php artisan schedule:run >> /dev/null 2>&1
```

### 2.5 Feladat-felhasználó kapcsolat

- Egy feladathoz több felelős rendelhető
- Minden felelős kap értesítést a kiosztásról
- A feladat akkor számít késznek, ha bármelyik felelős készre jelöli

### 2.6 Munkaidő követés

- Becsült munkaidő (estimated_hours): a feladat létrehozásakor megadható
- Tényleges munkaidő (actual_hours): a feladat lezárásakor rögzíthető
- Ez az adat a riportokban felhasználható

### 2.7 Üzleti szabályok

- Feladat létrehozója automatikusan értesítést kap a státuszváltozásokról
- Lejárt határidejű feladatok külön szűrővel megjeleníthetők
- Csillagozott feladatok az oldalsávban gyorsan elérhetők
- Ismétlődő feladat sablon törlése nem törli a már generált feladat-példányokat

---

## 3. Projektek modul

### 3.1 Áttekintés

A projektek modul nagyobb volumenű munkák szervezését támogatja. Egy projekt csapatokból, feladatokból és mérföldkövekből áll. Segít a haladás nyomon követésében és az erőforrás-tervezésben.

### 3.2 Projekt életciklusa

```
┌─────┐     ┌────────┐     ┌────────────┐     ┌────────┐
│ Új  │────►│ Aktív  │────►│ Lezárt     │     │ Törölt │
└─────┘     └───┬────┘     └────────────┘     └────────┘
                │                                  ▲
                ▼                                  │
          ┌─────────────┐                          │
          │ Felfüggesztve│─────────────────────────┘
          └─────────────┘
```

### 3.3 Csapatok

- Egy projektnek több csapata lehet
- Minden csapatnak van neve
- Csapattagok különböző szerepkörökkel (tag, vezető)
- Felhasználó több csapatnak is tagja lehet

### 3.4 Mérföldkövek

- A projekt nagyobb állomásainak jelölése
- Határidő megadható
- Teljesítés jelölhető
- A projekt feladatai mérföldkőhöz rendelhetők

### 3.5 Haladás-követés

A projekt haladás (progress) százalékban:
- Manuálisan megadható a projekt szerkesztésekor
- Vagy automatikusan számított a feladatok arányából (kész / összes * 100)

### 3.6 Üzleti szabályok

- Projekt feladatai a Feladatok modulban is megjelennek
- Projekt lezárásakor a nyitott feladatok figyelmeztetést kapnak
- Csak projekt tulajdonos vagy admin zárhatja le a projektet
- Felfüggesztett projekt feladatai nem generálnak értesítéseket

---

## 4. Hibajegyek (Issues) modul

### 4.1 Áttekintés

A hibajegyek modul a műszaki hibák nyilvántartására szolgál. A bejelentésekhez hasonló, de specifikusan a technikai problémák követésére optimalizált, súlyossági szintekkel és megoldás-dokumentálással.

### 4.2 Hibajegy életciklusa

```
┌─────┐     ┌────────────┐     ┌─────────┐     ┌────────────┐     ┌──────────┐     ┌────────┐
│ Új  │────►│ Vizsgálat  │────►│ Javítás │────►│ Ellenőrzés │────►│ Megoldva │────►│ Lezárt │
└─────┘     └────────────┘     └─────────┘     └────────────┘     └──────────┘     └────────┘
```

### 4.3 Súlyossági szintek

| Súlyosság | Szín | Leírás |
|-----------|------|--------|
| Kisebb (minor) | Zöld | Minimális hatás, nem akadályozza a működést |
| Közepes (major) | Narancs | Jelentős hatás, de van kerülő megoldás |
| Kritikus (critical) | Piros | Fontos funkció nem működik |
| Blokkoló (blocker) | Sötétpiros | Teljes leállás, azonnali beavatkozás |

### 4.4 Kapcsolódás bejelentésekhez

- Hibajegyhez rendelhető egy vagy több kapcsolódó bejelentés (ticket_id)
- A hibajegy részletnézetében megjelennek a kapcsolódó bejelentések
- A bejelentés részletnézetében megjelennek a kapcsolódó hibajegyek

### 4.5 Megoldás dokumentálása

A hibajegy megoldásakor a `resolution` mezőben dokumentálható:
- Mi volt a probléma gyökere
- Milyen javítást végeztek
- Milyen megelőző lépéseket javasolnak

### 4.6 Üzleti szabályok

- Hibajegyet bárki létrehozhat (megfelelő jogosultsággal)
- Megoldás rögzítése nélkül nem zárható le
- Blokkoló hibajegy automatikusan kritikus prioritású bejelentést generálhat (opcionális)

---

## 5. Javaslatok (Suggestions) modul

### 5.1 Áttekintés

A javaslatok modul lehetővé teszi, hogy a felhasználók fejlesztési javaslatokat, ötleteket küldjenek be. Az adminisztrátor elbírálhatja ezeket, a felhasználók szavazhatnak rájuk.

### 5.2 Javaslat életciklusa

```
┌─────┐     ┌────────────┐     ┌──────────────┐     ┌──────────────┐
│ Új  │────►│ Elbírálás  │──┬─►│ Elfogadva    │────►│ Megvalósítva │
└─────┘     └────────────┘  │  └──────────────┘     └──────────────┘
                            │
                            └─►┌──────────────┐
                               │ Elutasítva   │
                               └──────────────┘
```

### 5.3 Szavazás

- Minden felhasználó egyszer szavazhat egy javaslatra
- A szavazat visszavonható
- A szavazatok száma denormalizáltan tárolódik a `votes_count` mezőben
- A lista rendezehető szavazatok száma szerint

### 5.4 Elbírálási workflow

1. Javaslat beérkezik (Új státusz)
2. Admin átnézi, megjegyzést fűz hozzá
3. Admin dönt: Elfogadva vagy Elutasítva (review_note kötelező)
4. Elfogadott javaslat esetén projekt vagy feladat létrehozható belőle
5. Megvalósítás után a javaslat státusza "Megvalósítva"-ra módosul

### 5.5 Üzleti szabályok

- Javaslat szerzője nem szavazhat a saját javaslatára
- Elutasított javaslat nem módosítható
- Javaslat törlése nincs, csak az admin állíthatja "Elutasítva" státuszra
- Kommentelés minden státuszban lehetséges

---

## 6. Dokumentumtár modul

### 6.1 Áttekintés

A dokumentumtár a szervezet dokumentumainak központi tárhelye. Mappa-struktúrával, verziókezeléssel és jogosultságkezeléssel rendelkezik. Helyszínekhez és projektekhez is rendelhető.

### 6.2 Mappa-struktúra

```
Dokumentumtár (gyökér)
├── Globális dokumentumok/
│   ├── Szabályzatok/
│   ├── Sablonok/
│   └── Útmutatók/
├── Helyszínek/
│   ├── A épület/
│   │   ├── Műszaki dokumentáció/
│   │   ├── Szerződések/
│   │   └── Jegyzőkönyvek/
│   └── B épület/
│       └── ...
└── Projektek/
    ├── Felújítás 2024/
    └── ...
```

- Mappák fa-struktúrában szerveződnek (parent_id)
- Mappák helyszínhez vagy projekthez köthetők
- Gyökér mappák szervezetszinten léteznek

### 6.3 Verziókezelés

- Minden dokumentumnak van verziószáma (1, 2, 3, ...)
- Új verzió feltöltésekor az előző verziók megmaradnak
- Bármely korábbi verzió letölthető
- A `current_version` mező mindig az aktuális verziót mutatja
- Verzió feltöltésekor changelog megadható

### 6.4 Dokumentum típusok

Adminisztrálható dokumentum típusok:
- Szerződés
- Műszaki dokumentáció
- Jegyzőkönyv
- Garancialevél
- Engedély
- Terv/rajz
- Szabályzat
- Egyéb

### 6.5 Címkézés

- Dokumentumok címkékkel láthatók el (document_tags)
- Több címke is rendelhető egy dokumentumhoz
- Keresés és szűrés címke alapján

### 6.6 Üzleti szabályok

- Mappa törlése csak üres mappa esetén lehetséges
- Dokumentum törlése soft delete (visszaállítható)
- Dokumentum letöltés naplózódik az activity_log-ba
- Helyszínhez kötött mappa automatikusan szűr a helyszín jogosultság alapján
- Maximum fájlméret: a fájltípustól függően (lásd 05-modules-core.md)
