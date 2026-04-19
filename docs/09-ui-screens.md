# 09 - UI képernyők és navigáció

> ⚠️ **Frissítve az M1–M2.5 fázisokban.** Az auth-flow kibővült (szervezet-választó oldal, lockscreen + inaktivitási figyelmeztetés), a fejlécben szervezet-váltó jelent meg, az admin menüben pedig megjelent a szervezet-kezelés (super-admin + subscriber-admin). Lásd [11-user-membership.md](11-user-membership.md) a teljes flow-ért.

## 1. Általános elrendezés

### 1.1 Layout struktúra (Web)

```
┌──────────────────────────────────────────────────────────────┐
│                        FEJLÉC (Header)                       │
│  [☰ Menü]  [Logo]  [Keresés...]  [🔔 3] [✉ 2] [👤 Profil]  │
├────────────────┬─────────────────────────────────────────────┤
│                │                                             │
│   OLDALSÁV     │            FŐ TARTALOM                      │
│   (Sidebar)    │                                             │
│                │   ┌─────────────────────────────────────┐   │
│   Dashboard    │   │  Breadcrumb: Főoldal > Bejelentések │   │
│   Bejelentések │   ├─────────────────────────────────────┤   │
│   Feladatok    │   │                                     │   │
│   Projektek    │   │  Tartalom terület                   │   │
│   Hibajegyek   │   │  (lista / részletek / űrlap)       │   │
│   Javaslatok   │   │                                     │   │
│   Dokumentumok │   │                                     │   │
│   ─────────    │   │                                     │   │
│   Helyszínek   │   │                                     │   │
│   Eszközök     │   │                                     │   │
│   Karbantartás │   │                                     │   │
│   ─────────    │   │                                     │   │
│   Szerződések  │   │                                     │   │
│   Üzenetek     │   │                                     │   │
│   ─────────    │   │                                     │   │
│   Beállítások  │   └─────────────────────────────────────┘   │
│   (admin)      │                                             │
│                │                                             │
└────────────────┴─────────────────────────────────────────────┘
```

### 1.2 Reszponzív viselkedés

| Törésponttok | Leírás |
|-------------|--------|
| < 640px (sm) | Mobil: oldalsáv rejtett (hamburger menüvel nyitható), egyfüles tartalom |
| 640-1024px (md) | Tablet: összecsukott oldalsáv (csak ikonok), tartalom teljes szélességben |
| > 1024px (lg) | Desktop: kinyitott oldalsáv + tartalom |

### 1.3 Fejléc elemek

| Elem | Leírás |
|------|--------|
| Hamburger menü (☰) | Oldalsáv ki-/becsukása |
| Logo | Aktuális szervezet logó + alkalmazás név |
| Szervezet-váltó | Aktuális szervezet neve mellett legördülő (csak ha a user > 1 szervezetnek tagja vagy super-admin impersonation aktív). Lista: saját tagságok + super-admin esetén „Belépés másik szervezetbe…" |
| Impersonation-sáv | Ha super-admin impersonation aktív, feltűnő figyelmeztető sáv: „Belépve: XY Kft. — [Kilépés]" |
| Keresés mező | Globális keresés (Ctrl+K gyorsbillentyű) |
| Értesítés csengő (🔔) | Olvasatlan értesítések száma + legördülő lista |
| Üzenet ikon (✉) | Olvasatlan üzenetek száma + legördülő lista |
| Profil avatar | Legördülő: Profil, Beállítások, Biztonság (2FA, jelszó, sessionök — M4/M5), Kijelentkezés |

### 1.4 Oldalsáv

- Modulok ikonnal és címkével
- Elválasztó vonalak a csoportok között (Core, Üzleti, Épület, Kiegészítő)
- Aktív menüpont kiemelése
- Admin menüpontok csak admin jogosultságú felhasználóknak
- Összecsukható (csak ikon nézet)
- "Csillagozott feladatok" szekció (opcionális)

---

## 2. Autentikáció képernyők

### 2.1 Bejelentkezés (Login)

```
┌─────────────────────────────────────┐
│                                     │
│         [Logo + Applikáció név]     │
│                                     │
│  ┌─────────────────────────────┐    │
│  │  Email cím                  │    │
│  │  [________________________] │    │
│  │                             │    │
│  │  Jelszó                     │    │
│  │  [________________________] │    │
│  │                             │    │
│  │  [    Bejelentkezés    ]    │    │
│  │                             │    │
│  │  Elfelejtett jelszó?        │    │
│  └─────────────────────────────┘    │
│                                     │
└─────────────────────────────────────┘
```

### 2.2 Jelszó-visszaállítás

- Email megadása képernyő
- Visszaigazoló képernyő ("Email elküldve")
- Új jelszó beállítás képernyő (token alapú link)

### 2.3 Meghívó elfogadás

A meghívó token ellenőrzése után a képernyő **két ágra** bomlik:

- **Új user** (az email még nem létezik a rendszerben): név + jelszó + megerősítés megadása, majd közvetlen bejelentkezés az új szervezetbe.
- **Meglévő user** (az email már bejelentkezett fiókhoz tartozik): csak egy „Meghívó elfogadása" gomb, autentikáció után azonnal tagság jön létre; ha nincs bejelentkezve, login lépés az eredeti fiókkal.

### 2.4 Szervezet-választó (select-organization)

Login után, ha a usernek több aktív tagsága van és nincs `default_organization_id`, ide kerül. Lista-nézet:
- Minden tagság egy kártya: szervezet neve, típusa (Platform/Subscriber/Client badge), szerepkör, státusz-dot
- Kattintás → folytatja a belépést az adott tagság kontextusába
- Jelölőnégyzet: „Emlékezz erre, mint alapértelmezett"
- Super-admin esetén extra gomb: „Belépés másik szervezetbe…" (impersonation kereső)

### 2.5 Lockscreen + inaktivitási figyelmeztetés

- **Warning modal** (inaktivitás vége előtt 30 mp-cel): countdown + „Maradjak bent" gomb
- **Lockscreen**: teljes képernyő, user neve + kezdőbetűs avatar, csak jelszó megadás a feloldáshoz (nem new login)
- Minden API-hívás kiváltja a pause-t a timeren
- Sikeres aktiválás visszaigazolás

### 2.4 Lockscreen

- Felhasználó avatar + név
- Jelszó mező
- "Feloldás" gomb
- "Más fiókkal bejelentkezés" link

---

## 3. Dashboard képernyő

```
┌─────────────────────────────────────────────────────────────┐
│  Dashboard                                                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ Nyitott  │ │Feladatok │ │ SLA túl- │ │ Esedékes │       │
│  │bejelent. │ │ folyam.  │ │  lépett  │ │karbant.  │       │
│  │   45     │ │   12     │ │    3     │ │    2     │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
│                                                             │
│  ┌─────────────────────────┐ ┌─────────────────────────┐   │
│  │ Bejelentések (30 nap)   │ │ Kategória megoszlás     │   │
│  │                         │ │                         │   │
│  │  📈 Vonal diagram       │ │  🥧 Torta diagram       │   │
│  │                         │ │                         │   │
│  └─────────────────────────┘ └─────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────┐ ┌─────────────────────────┐   │
│  │ Legutóbbi bejelentések  │ │ Esedékes feladatok      │   │
│  │                         │ │                         │   │
│  │ • PV-2024-01234 Lift... │ │ • Heti takarítás - B ép │   │
│  │ • PV-2024-01233 Klíma.. │ │ • Lift ellenőrzés - A.. │   │
│  │ • PV-2024-01232 Csőtö.. │ │ • Tűzjelző teszt       │   │
│  │                         │ │                         │   │
│  └─────────────────────────┘ └─────────────────────────┘   │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Aktivitás stream                                     │   │
│  │ 10:30 Kovács J. lezárta: PV-2024-01230              │   │
│  │ 10:15 Nagy P. kommentet írt: PV-2024-01229          │   │
│  │ 09:45 Tóth K. feladatot teljesített: Lift vizsgálat │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 4. Lista nézetek (közös minta)

Minden modul lista nézete hasonló mintát követ:

```
┌─────────────────────────────────────────────────────────────┐
│  Bejelentések                              [+ Új bejelentés]│
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Mentett szűrők: [Alapértelmezett ▼] [A épület nyitott ▼]  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ Szűrők:                                             │    │
│  │ [Keresés...    ] [Kategória ▼] [Státusz ▼]         │    │
│  │ [Prioritás ▼] [Helyszín ▼] [Felelős ▼]            │    │
│  │ [Dátumtól 📅] [Dátumig 📅] [Szűrők törlése]       │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  Rendezés: [Létrehozás ▼] [Csökkenő ▼]  Összesen: 245    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ □ Hivatkozás   Cím          Kateg. Prior. Státusz  │    │
│  ├─────────────────────────────────────────────────────┤    │
│  │ □ PV-2024-1234 Lift meghi..  Lift  🔴Krit  Új     │    │
│  │ □ PV-2024-1233 Klíma nem..  Klíma 🟡Köz.  Folyam │    │
│  │ □ PV-2024-1232 Csőtörés..   Víz   🔴Krit  Folyam │    │
│  │ □ PV-2024-1231 Világítás..  Vill. 🟢Alac. Megold │    │
│  │ ...                                                │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  [◄ Előző]  1 2 3 ... 10  [Következő ►]  [25 ▼] elem/oldal│
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Közös elemek:**
- Fejléc az oldal címével és "Új" gombbal
- Mentett szűrők gyorselérés
- Összecsukható szűrő panel
- Rendezés és összesítő
- Adattábla (válogatható oszlopok, soronkénti kattintás a részletnézethez)
- Lapozás az alján

**Mobil nézet:**
- Kártyás elrendezés tábla helyett
- Szűrők legördülő panelben
- Swipe gesztusok (opcionális)

---

## 5. Részletnézetek (közös minta)

```
┌─────────────────────────────────────────────────────────────┐
│  ◄ Vissza   PV-2024-01234 - Lift meghibásodás - B épület   │
│                                               [Szerkesztés] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────┐ ┌─────────────────────────┐   │
│  │ Státusz: [Új ▼]         │ │ Felelős: Nincs          │   │
│  │ Prioritás: 🔴 Kritikus  │ │ [Felelős kijelölése ▼]  │   │
│  │ Kategória: Lift         │ │                         │   │
│  │ SLA: 2024-03-15 18:00   │ │ Követők: 👤👤 (2)      │   │
│  │                         │ │ [+ Követés]             │   │
│  └─────────────────────────┘ └─────────────────────────┘   │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Leírás                                               │   │
│  │ A 3. emeleti lift nem működik, hibajelzést mutat.    │   │
│  │ A kijelzőn "E05" hibakód jelenik meg.                │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Helyszín és eszköz                                   │   │
│  │ 📍 B épület > 3. emelet                              │   │
│  │ ⚙ B-LIFT-03 (Lift - ThyssenKrupp)                   │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  [Részletek] [Hozzászólások (3)] [Timeline (8)] [Fájlok (2)]│
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Hozzászólások                                        │   │
│  │                                                      │   │
│  │ 👤 Kovács J. - 2024.03.15 11:00                     │   │
│  │ Technikus kiküldve a helyszínre.                     │   │
│  │                                                      │   │
│  │ 👤 Nagy P. - 2024.03.15 12:30                       │   │
│  │ A vezérlő panel cserére szorul, alkatrész rendelve.  │   │
│  │                                                      │   │
│  │ [Hozzászólás írása...]                  [📎] [Küldés]│   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Közös elemek:**
- Vissza gomb + hivatkozási szám + cím
- Oldalsó panel: státusz, felelős, meta adatok
- Leírás szekció
- Helyszín és eszköz információ (kattintható)
- Tab-ok: Részletek, Hozzászólások, Timeline, Fájlok, Kapcsolódó elemek
- Hozzászólás író mező alul

---

## 6. Űrlap nézetek (közös minta)

```
┌─────────────────────────────────────────────────────────────┐
│  Új bejelentés                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Cím *                                                      │
│  [________________________________________________]         │
│                                                             │
│  Leírás *                                                   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  │  (Szövegszerkesztő)                                  │   │
│  │                                                      │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  Kategória *               Prioritás                        │
│  [Válasszon... ▼]          [Közepes ▼]                     │
│                                                             │
│  Helyszín                  Szint                            │
│  [Válasszon... ▼]          [Válasszon... ▼]                │
│                                                             │
│  Helyiség                  Eszköz                           │
│  [Válasszon... ▼]          [Válasszon... ▼]                │
│                                                             │
│  Csatolmányok                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  📎 Húzza ide a fájlokat vagy kattintson             │   │
│  │     a feltöltéshez                                   │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  [Mégse]                                       [Mentés]     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Közös elemek:**
- Validációs üzenetek a mezők alatt (piros szöveg)
- Kötelező mezők csillaggal (*) jelölve
- Kaszkád legördülők (Helyszín → Szint → Helyiség)
- Drag & drop fájl feltöltés
- Mégse és Mentés gombok

---

## 7. Admin képernyők

### 7.1 Admin navigáció (oldalsáv alsó része)

```
Beállítások
├── Szervezet beállítások
├── Felhasználók
├── Szerepkörök és jogosultságok
├── Bejelentés beállítások
│   ├── Kategóriák
│   ├── Státuszok
│   └── Reakciók
├── Helyszínek kezelése
├── Eszköz típusok
├── SLA szabályok
├── Engedélyezett domainek
└── Hírek
```

### 7.2 Jogosultsági mátrix szerkesztő

```
┌─────────────────────────────────────────────────────────────┐
│  Jogosultságok kezelése                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Szerepkör: [Diszpécser ▼]                                 │
│                                                             │
│  ┌────────────────┬────────┬────────┬────────┬────────┐    │
│  │ Modul/Művelet  │  Read  │ Create │ Update │ Delete │    │
│  ├────────────────┼────────┼────────┼────────┼────────┤    │
│  │ Bejelentések   │  [✓]   │  [✓]   │  [✓]   │  [ ]   │    │
│  │ Feladatok      │  [✓]   │  [✓]   │  [✓]   │  [ ]   │    │
│  │ Projektek      │  [✓]   │  [✓]   │  [✓]   │  [ ]   │    │
│  │ Hibajegyek     │  [✓]   │  [✓]   │  [✓]   │  [ ]   │    │
│  │ Javaslatok     │  [✓]   │  [✓]   │  [ ]   │  [ ]   │    │
│  │ Dokumentumok   │  [✓]   │  [✓]   │  [ ]   │  [ ]   │    │
│  │ Helyszínek     │  [✓]   │  [ ]   │  [✓]   │  [ ]   │    │
│  │ Eszközök       │  [✓]   │  [✓]   │  [✓]   │  [ ]   │    │
│  │ Karbantartás   │  [✓]   │  [ ]   │  [ ]   │  [ ]   │    │
│  │ Szerződések    │  [✓]   │  [ ]   │  [ ]   │  [ ]   │    │
│  │ Felhasználók   │  [✓]   │  [ ]   │  [ ]   │  [ ]   │    │
│  │ Riportok       │  [✓]   │   -    │   -    │   -    │    │
│  └────────────────┴────────┴────────┴────────┴────────┘    │
│                                                             │
│  Speciális engedélyek:                                      │
│  [✓] Felelős kijelölés (tickets.assign)                    │
│  [✓] Eszkaláció (tickets.escalate)                         │
│  [ ] SLA kezelés (settings.manage_sla)                     │
│  ...                                                        │
│                                                             │
│  [Mégse]                                       [Mentés]     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. Mobil app képernyők (Flutter)

### 8.1 Navigáció

**Bottom Navigation Bar (5 tab):**
1. Dashboard (kezdőoldal)
2. Bejelentések
3. QR szkenner (kiemelt gomb)
4. Feladatok
5. Profil/Menü

### 8.2 Dashboard (mobil)

```
┌─────────────────────────┐
│  Previse        🔔3 ✉2  │
├─────────────────────────┤
│                         │
│  Jó reggelt, János! 👋  │
│                         │
│  ┌─────────┐ ┌────────┐│
│  │Bejelent. │ │Feladat ││
│  │  45 nyit │ │ 12 foly││
│  └─────────┘ └────────┘│
│  ┌─────────┐ ┌────────┐│
│  │SLA túl  │ │Karbant.││
│  │  3      │ │  2 esed││
│  └─────────┘ └────────┘│
│                         │
│  Legutóbbi bejelentések │
│  ┌─────────────────────┐│
│  │ 🔴 PV-2024-01234   ││
│  │ Lift meghibásodás   ││
│  │ B épület • 10:30    ││
│  ├─────────────────────┤│
│  │ 🟡 PV-2024-01233   ││
│  │ Klíma nem hűt       ││
│  │ A épület • 09:15    ││
│  └─────────────────────┘│
│                         │
│  [Dashboard][Bej.][📷][Fel.][☰]│
└─────────────────────────┘
```

### 8.3 QR szkenner

```
┌─────────────────────────┐
│  QR szkenner      [✕]   │
├─────────────────────────┤
│                         │
│  ┌─────────────────────┐│
│  │                     ││
│  │    📷 Kamera nézet  ││
│  │                     ││
│  │   ┌─────────┐      ││
│  │   │ QR keret│      ││
│  │   └─────────┘      ││
│  │                     ││
│  └─────────────────────┘│
│                         │
│  Irányítsa a kamerát    │
│  a QR kódra             │
│                         │
│  [📸 Galéria] [🔦 Lámpa]│
│                         │
└─────────────────────────┘
```

### 8.4 Bejelentés részletnézet (mobil)

```
┌─────────────────────────┐
│  ◄  PV-2024-01234  [⋮] │
├─────────────────────────┤
│                         │
│  Lift meghibásodás      │
│  B épület               │
│                         │
│  🔴 Kritikus  • Új     │
│                         │
│  Leírás                 │
│  A 3. emeleti lift      │
│  nem működik...         │
│                         │
│  📍 B épület > 3. em.  │
│  ⚙ B-LIFT-03           │
│                         │
│  ──────────────────     │
│                         │
│  Hozzászólások (3)      │
│  👤 Kovács J. 11:00    │
│  Technikus kiküldve...  │
│                         │
│  👤 Nagy P. 12:30      │
│  Alkatrész rendelve...  │
│                         │
│  [Komment írása...  📎 ]│
│                         │
│  [Dashboard][Bej.][📷][Fel.][☰]│
└─────────────────────────┘
```

---

## 9. Téma rendszer

### 9.1 Támogatott témák

- **Light mode**: Világos háttér, sötét szöveg
- **Dark mode**: Sötét háttér, világos szöveg

### 9.2 Színsémák

Mindkét témához elérhető színsémák (fő szín / accent):
- Kék (blue) - Alapértelmezett
- Zöld (green)
- Lila (purple)
- Piros (red)
- Narancs (orange)
- Szürke (default/megna)

### 9.3 Implementáció

- **Web**: Tailwind CSS `dark:` osztályok + CSS custom properties a színsémához
- **Mobil**: Flutter ThemeData + Material 3 ColorScheme
- A felhasználó beállításaiban választható
- Rendszer beállítás követése (prefers-color-scheme) is opció

---

## 10. Értesítés megjelenések

### 10.1 Csengő ikon legördülő

```
┌──────────────────────────┐
│ Értesítések        Mind  │
├──────────────────────────┤
│ 🔴 SLA figyelmeztetés    │
│ PV-2024-01234 - 2 óra..  │
│ 5 perce                  │
├──────────────────────────┤
│ 📋 Feladat kiosztva      │
│ Lift ellenőrzés - A ép.  │
│ 15 perce                 │
├──────────────────────────┤
│ 💬 Hozzászólás            │
│ PV-2024-01233 - Kovács J │
│ 1 órája                  │
├──────────────────────────┤
│ [Összes értesítés →]     │
└──────────────────────────┘
```

### 10.2 Push értesítés (mobil/web)

```
┌────────────────────────────────┐
│ 🔔 Previse                    │
│ Új bejelentés: PV-2024-01234  │
│ Lift meghibásodás - B épület   │
└────────────────────────────────┘
```
