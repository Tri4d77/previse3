# 05 - Core modulok specifikációja

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

## 2. Felhasználó-kezelés modul

### 2.1 Funkciók

**Felhasználói profil:**
- Név, email, telefonszám megjelenítése és szerkesztése
- Profilkép feltöltése (max 2MB, JPEG/PNG)
- Jelszó módosítás (régi jelszó megadásával)

**Admin felhasználó-kezelés:**
- Felhasználók listája (szűrhető: név, email, szerepkör, státusz, csoport)
- Új felhasználó meghívása (név, email, szerepkör, csoport megadásával)
- Felhasználó szerkesztése (név, szerepkör, csoport módosítása)
- Felhasználó aktiválása / deaktiválása
- Felhasználó törlése (soft delete)

**Felhasználói csoportok:**
- Csoportok listája és kezelése (létrehozás, szerkesztés, törlés)
- Felhasználók hozzáadása/eltávolítása csoportokból
- Egy felhasználó több csoportba is tartozhat

### 2.2 Felhasználói beállítások

| Beállítás | Típus | Alapértelmezés | Leírás |
|-----------|-------|----------------|--------|
| theme | select | light | Téma (light / dark) |
| color_scheme | select | blue | Színséma |
| locale | select | hu | Nyelv |
| timezone | select | Europe/Budapest | Időzóna |
| items_per_page | select | 25 | Lista elemek száma (10, 25, 50, 100) |
| default_page | select | dashboard | Bejelentkezés utáni oldal |
| notification_email | boolean | true | Email értesítések |
| notification_push | boolean | true | Push értesítések |
| notification_sound | boolean | true | Értesítési hangjelzés |

### 2.3 API végpontok

| Metódus | Útvonal | Leírás |
|---------|---------|--------|
| GET | /api/v1/users | Felhasználók listája |
| POST | /api/v1/users | Új felhasználó meghívása |
| GET | /api/v1/users/{id} | Felhasználó adatai |
| PUT | /api/v1/users/{id} | Felhasználó módosítása |
| DELETE | /api/v1/users/{id} | Felhasználó törlése |
| PATCH | /api/v1/users/{id}/toggle-active | Aktiválás/deaktiválás |
| GET | /api/v1/profile | Saját profil |
| PUT | /api/v1/profile | Profil módosítása |
| PUT | /api/v1/profile/password | Jelszó módosítása |
| POST | /api/v1/profile/avatar | Profilkép feltöltése |
| GET | /api/v1/settings | Beállítások lekérése |
| PUT | /api/v1/settings | Beállítások mentése |
| GET | /api/v1/groups | Csoportok listája |
| POST | /api/v1/groups | Új csoport |
| PUT | /api/v1/groups/{id} | Csoport módosítása |
| DELETE | /api/v1/groups/{id} | Csoport törlése |

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
