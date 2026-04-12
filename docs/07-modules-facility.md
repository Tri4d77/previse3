# 07 - Épület-üzemeltetési modulok specifikációja

## 1. Helyszínek modul

### 1.1 Áttekintés

A helyszínek modul az épületek, szintek és helyiségek hierarchikus nyilvántartását biztosítja. Ez az alap, amihez a bejelentések, feladatok, hibajegyek, eszközök és karbantartási munkák köthetők. A karbantartó cégek számára kulcsfontosságú, hogy helyszínenként lássák a feladataikat és az előzményeket.

### 1.2 Hierarchia

```
Szervezet
└── Helyszín (Location) - Épület
    └── Szint (Floor) - Emelet
        └── Helyiség (Room) - Iroda, raktár, stb.
```

### 1.3 Helyszín (Épület) adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Név | Igen | Épület neve (pl. "Westend Irodaház B torony") |
| Cím | Nem | Teljes postai cím |
| Város | Nem | Város |
| Irányítószám | Nem | Irányítószám |
| GPS koordináták | Nem | Szélesség és hosszúság (térképes megjelenítéshez) |
| Típus | Nem | Épület típus (iroda, bevásárlóközpont, lakóház, ipari, stb.) |
| Leírás | Nem | Részletes leírás |
| Kapcsolattartó neve | Nem | Helyi kontakt személy |
| Kapcsolattartó telefon | Nem | Telefon |
| Kapcsolattartó email | Nem | Email |
| Aktív | Igen | Aktív-e (inaktív helyszínek nem jelennek meg a szűrőkben) |

### 1.4 Szint adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Név | Igen | Szint neve (pl. "Földszint", "1. emelet", "B2 pince") |
| Szint szám | Igen | Numerikus érték rendezéshez (pl. -2, -1, 0, 1, 2) |
| Leírás | Nem | Megjegyzés |

### 1.5 Helyiség adatok

| Mező | Kötelező | Leírás |
|------|----------|--------|
| Név | Igen | Helyiség neve (pl. "Nyitott iroda", "Szerver szoba") |
| Szám | Nem | Helyiségszám (pl. "B.2.15") |
| Típus | Nem | Típus (iroda, raktár, folyosó, mosdó, műszaki, konyha) |
| Alapterület | Nem | m2 |
| Leírás | Nem | Megjegyzés |

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

- Helyszín törlése csak akkor lehetséges, ha nincs hozzá kötött aktív bejelentés, feladat vagy eszköz
- Szint törlése csak üres szint esetén (nincs helyiség benne)
- Helyiség törlése csak akkor, ha nincs hozzá kötött eszköz
- Inaktív helyszínek nem jelennek meg a legördülő szűrőkben, de a korábbi adatok megmaradnak

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
