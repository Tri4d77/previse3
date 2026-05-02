# Future Features — v2 / későbbi iterációk

A v1 indulás után megvalósítandó funkciók. Itt jegyezzük fel azokat a
részleteket, amelyek a tervezés során felmerültek, de a scope-csökkentés
érdekében késleltettük.

---

## Locations modul

### Helyiség-csoportok (ML2 keretein belül)
**Forrás**: ML2.1 finalizálás után, user kérés.
**Tervezett fázis**: **ML2.4** (még az ML2-es nagyobb csomagon belül)

**Üzleti igény**: a helyiségek néha logikailag összevonhatók egy közös térré. Pl. egy bevásárlóközpontban a 3 különálló üzlethelyiséget (A, B, C) egy bérlő összenyitja és „MultiShop" névvel egységes térként használja. A különálló helyiségek (saját nevekkel, számokkal, ticket-előzményeikkel) **megmaradnak** a rendszerben, de tartoznak egy új **helyiség-csoport** entitáshoz is.

**Adatmodell**:
- Új tábla: `room_groups` (`id`, `location_id`, `name`, `description`, `color` — a csoport opcionális színjelzése a UI-n, `sort_order`, `created_at`, `updated_at`)
- Pivot: `room_room_group` (`room_id`, `room_group_id`, `joined_at`) — egy helyiség akár több csoportban is lehet (pl. ideiglenes közösség)
- Permission: `locations.manage_room_groups`

**REST endpointok**:
- `GET /locations/{location}/room-groups`
- `POST /locations/{location}/room-groups`
- `PUT /room-groups/{id}`
- `DELETE /room-groups/{id}`
- `POST /room-groups/{id}/rooms` — helyiség hozzáadása a csoporthoz
- `DELETE /room-groups/{id}/rooms/{room_id}` — helyiség kivétele
- `GET /room-groups/{id}` — csoport részletei + tagok

**UI**:
- Új tab a Helyszín részletoldalon: „Helyiség-csoportok" (vagy a Szintek+Helyiségek tab egy szekciójaként)
- Csoport-lista, kártyák tagok-listával
- Csoportba szerkesztő modal: helyiségek multi-select listája szintenként csoportosítva
- A helyiség sorában jelölés, ha legalább egy csoporthoz tartozik (csoport-color badge-ek)
- Tickets/Tasks/Assets-en a `room_id` mellé a UI **felajánlhatja a csoport megjelölését** is — pl. egy bejelentés egy csoportra mutathat a több helyiség helyett (alkalmazói döntés)

**Üzleti szabályok**:
- Egy csoportnak a saját `location_id`-jú helyiségeket lehet csak hozzáadni
- Csoport törlése a tagokat **nem törli**, csak a kapcsolatot
- Helyiség törlése automatikusan kiveszi a csoportokból (pivot cascade)

### Interaktív alaprajzi hotspot-rendszer
**Forrás**: ML3 fázis tervezésekor (2.A kérdés)

Az ML3-ban a szint-/helyiség-alaprajzokat csak feltöltjük és zoomolható módon megjelenítjük. A felhasználó az alaprajzon vizuálisan látja a helyiségek elhelyezkedését, de a rendszer nem tud a kép-koordinátákról.

**v2 funkció**: az adminisztrátor megrajzolhatja a szint-alaprajzon a helyiségek határait (polygon hotspots). A felhasználó kattintással léphet a szintrajzról a helyiség-részletes nézetre. Ehhez:
- Polygon-szerkesztő komponens (pl. `leaflet-image` plugin vagy SVG-overlay)
- `room.hotspot_polygon` JSON-mező a `rooms` táblán (kép-koordináták listája)
- Kattintás-eseménnyel route-váltás

---

## Roles & permissions admin UI
**Forrás**: M11 fázis után, Locations permission-rendszer kapcsán

A backend (`/api/v1/roles`, `/api/v1/permissions`, `/api/v1/roles/{id}/permissions`) **kész**, de a frontend admin felület még csak **placeholder** (`pages/admin/RolesPage.vue` 44 sor).

**M12 fázis**: vizuális permission-mátrix-szerkesztő:
- Listanézet a szervezet szerepköreiről (bal oldal: szerepkör-lista, jobb oldal: jogosultság-checkboxok)
- Új szerepkör létrehozása
- Default szerepkörök (admin, dispatcher, …) szerkesztése
- A szervezeti admin felüldefiniálhatja a default permission-mátrixot
- Csoportosítva modulonként (locations, tickets, tasks, stb.)

---

## Felhasználó-szintű jogosultság-felülbírálás
**Forrás**: Locations spec (1.12 szakasz végén)

A jelenlegi rendszer szerepkör-szintű permissions-okat ad. **Felhasználó-szinten** is lehessen finomítani: pl. egy `user` szerepkörű kollégának plusszal megadunk `locations.update`-et anélkül, hogy a szerepkörét módosítanánk.

Ehhez új tábla: `membership_permissions` (additív + tiltó override-ok membershipenként).

---

## Globális kontakt-katalógus a szervezeten belül
**Forrás**: Locations spec (1.11 szakasz végén)

A felhasználók láthatják egymás telefonszámát, e-mail-címét egy közös kontakt-keresőben (a saját `users.phone` mezőjük alapján). A `<ContactCard>` tooltip komponens újrafelhasználható mindenhol, ahol egy kolléga neve megjelenik (ticket assignee, comment author, location responsible, stb.).

---

## Egyéb későbbre halasztott

- **DWG támogatás**: konvertáló réteg (pl. szerver-oldali ImageMagick + LibreCAD) a DWG fájlok automatikus PNG/PDF-re konverziójához. Most a felhasználó saját maga konvertál.
- **Floor plan multi-fájl**: egy szinthez/helyiséghez több alaprajz (pl. „Alapozási", „Villamossági", „Tűzvédelmi") — most csak 1 fő alaprajz fájl.
- **Tag-választó UI fejlesztés**: ha sok címke van, kategorizálás vagy keresés.
- **Map-import (KML/GeoJSON)**: helyszínek tömeges térképi import.
- **Routing optimalizáció**: a karbantartók napi körútjának automatikus tervezése a helyszín-térképen.
- **Mobile QR-szken**: a helyszín saját QR-kódja, a kapunál szkennelve a karbantartó megnyitja a helyszín dashboard-ját.
