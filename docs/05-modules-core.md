# 05 - Core modulok specifikációja

> ⚠️ **Frissítve az M1–M2.5 fázisokban.** A Felhasználó-kezelés modul szét lett választva **user-szintű** (személy) és **membership-szintű** (szervezeti tagság) műveletekre. Lásd [11-user-membership.md](11-user-membership.md) és a frissített [04-auth-rbac.md](04-auth-rbac.md) dokumentumokat.

## 1. Auth modul

Részletes leírás: [04-auth-rbac.md](04-auth-rbac.md)

### 1.1 Funkciók összefoglalása
- Meghívó-alapú regisztráció
- Email + jelszó bejelentkezés
- Jelszó-visszaállítás (email token)
- Sanctum autentikáció (SPA cookie + mobil token)
- Session idle timeout
- Lockscreen (frontend)
- Kijelentkezés (aktuális / minden eszköz)

### 1.2 Üzleti szabályok
- Jelszó minimum 8 karakter, tartalmazzon nagybetűt, kisbetűt és számot
- Meghívó token érvényessége: 7 nap
- Jelszó-visszaállítás token érvényessége: 60 perc
- Sikertelen bejelentkezések után: 5 próbálkozás / 15 perc (throttling)
- Inaktív fiók nem tud bejelentkezni

---

## 2. Felhasználó- és tagságkezelés modul

### 2.1 Funkciók

A modul kétszintű. Egy **user (személy)** adatai függetlenek attól, hány szervezetnek tagja; a **membership (tagság)** szervezetspecifikus: role, csoport, aktivitás.

**User-szintű (saját profil):**
- Név, email, telefonszám megjelenítése és szerkesztése
- Profilkép feltöltése (max 2 MB, JPEG/PNG)
- Jelszó módosítás (régi jelszó megadásával) — **M4**
- Email-cím változtatás (kettős megerősítés) — **M6**
- Aktív sessionök listája és revokálása — **M4**
- 2FA be/kikapcsolás — **M5**
- Saját tagságok listája (melyik szervezeteknek vagyok tagja, milyen szerepkörrel)
- Kilépés szervezetből — **M7**
- Saját fiók megszüntetése (30 napos grace) — **M7**

**Membership-szintű (szervezet-admin):**
- Tagságok listája az aktuális szervezetben (szűrhető: név, email, szerepkör, státusz, csoport)
- Új tag meghívása (email + role; ha az email már létezik: csak új membership)
- Tagság szerkesztése (role, csoport módosítása)
- Tagság aktiválás / deaktiválás
- Tagság törlése (soft delete)
- Meghívó újraküldése
- Csoportok (org-szintű): létrehozás/szerkesztés/törlés, tagok hozzáadása/eltávolítása

### 2.2 Felhasználói beállítások (user_settings)

| Beállítás | Típus | Alapértelmezés | Leírás |
|-----------|-------|----------------|--------|
| theme | select | light | Téma (light / dark) |
| color_scheme | select | teal | Színséma (elsődleges: teal) |
| locale | select | hu | Nyelv (hu / en) |
| timezone | select | Europe/Budapest | Időzóna |
| items_per_page | select | 25 | Lista elemek száma (10, 25, 50, 100) |
| default_organization_id | select | NULL | Alapértelmezett szervezet — ha be van állítva, a login utáni szervezet-választó lépést átugrjuk |
| lockscreen_timeout_minutes | int | 30 | Lockscreen inaktivitási időkorlát percben (tesztkörnyezetben 3) |
| notification_email | boolean | true | Email értesítések |
| notification_push | boolean | true | Push értesítések |
| notification_sound | boolean | true | Értesítési hangjelzés |

### 2.3 API végpontok

Részletes felsorolás: [03-api-endpoints.md §3–§4](03-api-endpoints.md).

- **User-szintű**: `/api/profile`, `/api/profile/password`, `/api/profile/avatar`, `/api/profile/memberships`, `/api/settings` (és az M4–M7 fázisokban: `/api/profile/sessions`, `/api/profile/2fa/*`, `/api/profile/email`, `/api/profile` DELETE).
- **Membership-szintű (szervezet kontextusban)**: `/api/users` (a tagságokat adja vissza!), `/api/users/{id}`, `/api/users/{id}/toggle-active`, `/api/users/{id}/resend-invitation`.
- **Csoportok**: `/api/groups`, `/api/groups/{id}`, `/api/groups/{id}/members`.

---

## 3. Jogosultságkezelés (RBAC) modul

Részletes leírás: [04-auth-rbac.md](04-auth-rbac.md)

### 3.1 Admin felület funkciók
- Szerepkörök listája
- Szerepkör létrehozása (név, leírás)
- Jogosultsági mátrix szerkesztése (checkbox tábla: szerepkör x engedély)
- Felhasználók szerep-hozzárendelésének módosítása

### 3.2 API végpontok

| Metódus | Útvonal | Leírás |
|---------|---------|--------|
| GET | /api/v1/roles | Szerepkörök listája |
| POST | /api/v1/roles | Új szerepkör |
| PUT | /api/v1/roles/{id} | Szerepkör módosítása |
| DELETE | /api/v1/roles/{id} | Szerepkör törlése |
| GET | /api/v1/permissions | Engedélyek listája |
| PUT | /api/v1/roles/{id}/permissions | Szerepkör engedélyeinek frissítése |

---

## 4. Értesítések modul

### 4.1 Értesítés típusok

| Esemény | Email | Push | Belső |
|---------|-------|------|-------|
| Új bejelentés (felelősnek) | X | X | X |
| Bejelentés státusz változás | X | X | X |
| Bejelentés hozzászólás | - | X | X |
| Bejelentés eszkaláció | X | X | X |
| SLA figyelmeztetés | X | X | X |
| Feladat kiosztás | X | X | X |
| Feladat határidő közelít | X | X | X |
| Projekt tag hozzáadás | X | - | X |
| Hibajegy kiosztás | X | X | X |
| Javaslat elbírálás | X | - | X |
| Karbantartás esedékes | X | X | X |
| Szerződés lejárat közelít | X | - | X |
| Üzenet érkezett | - | X | X |
| Követett elem változás | - | X | X |

### 4.2 Belső értesítések

- Laravel `notifications` tábla (adatbázis driver)
- Valós idejű frissítés: polling (30 másodpercenként) - CPanel-en nincs WebSocket
- Értesítés csengő ikon a fejlécben (olvasatlan szám badge)
- Értesítés lista (legördülő + teljes oldal)
- Olvasottnak jelölés (egyenként és összesen)

### 4.3 Email értesítések

- Laravel Mail + SMTP konfiguráció
- HTML email sablonok (Laravel Blade)
- Felhasználónként ki-/bekapcsolható
- Email értesítési összefoglaló (opcionális, napi digest)

### 4.4 Push értesítések

- Firebase Cloud Messaging (FCM)
- Web: Service Worker-en keresztül
- Mobil: Flutter firebase_messaging package
- FCM token regisztráció bejelentkezéskor
- Token frissítés automatikusan

### 4.5 API végpontok

| Metódus | Útvonal | Leírás |
|---------|---------|--------|
| GET | /api/v1/notifications | Értesítések listája |
| GET | /api/v1/notifications/unread-count | Olvasatlan szám |
| PATCH | /api/v1/notifications/{id}/read | Olvasottnak jelölés |
| POST | /api/v1/notifications/mark-all-read | Összes olvasott |
| POST | /api/v1/fcm-tokens | FCM token regisztráció |
| DELETE | /api/v1/fcm-tokens/{id} | FCM token törlése |

---

## 5. Fájlkezelés modul

### 5.1 Támogatott fájltípusok

| Típus | Kiterjesztések | Max méret |
|-------|---------------|-----------|
| Képek | JPEG, PNG, GIF, WebP | 10 MB |
| Dokumentumok | PDF | 20 MB |
| Office | DOC, DOCX, XLS, XLSX, PPT, PPTX | 20 MB |
| Tömörített | ZIP, RAR | 50 MB |

**Tiltott típusok**: EXE, BAT, CMD, SH, PHP, JS, PY, CGI, BIN, PL

### 5.2 Feltöltés folyamat

1. Kliens küldi a fájlt multipart/form-data POST-ként
2. Laravel validálja: típus, méret, fájlnév
3. Eredeti fájlnév eltávolítása, UUID-alapú név generálás
4. Fájl mentése a `storage/app/uploads/{module}/{year}/{month}/` mappába
5. Képeknél miniatűr generálás (thumbnail: 200x200)
6. Adatbázis rekord létrehozása (attachments tábla)
7. Válasz a fájl metaadataival

### 5.3 Tárolási struktúra

```
storage/app/uploads/
├── tickets/
│   └── 2024/
│       └── 03/
│           ├── uuid1.jpg
│           └── uuid1_thumb.jpg
├── tasks/
├── projects/
├── documents/
├── assets/
└── avatars/
```

### 5.4 Letöltés

- Fájlok nem publikus mappában tárolódnak
- Letöltés dedikált endpoint-on keresztül, jogosultság-ellenőrzéssel
- Streaming response nagy fájlokhoz

### 5.5 API végpontok

| Metódus | Útvonal | Leírás |
|---------|---------|--------|
| POST | /api/v1/{entity}/{id}/attachments | Fájl feltöltése entitáshoz |
| GET | /api/v1/{entity}/{id}/attachments | Csatolmányok listája |
| GET | /api/v1/attachments/{id}/download | Fájl letöltése |
| DELETE | /api/v1/attachments/{id} | Csatolmány törlése |

Az `{entity}` lehet: tickets, tasks, projects, issues, assets, maintenance-logs
