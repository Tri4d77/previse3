# 03 - REST API dokumentáció

## 1. Áttekintés

### 1.1 Alap URL
```
https://api.previse.hu/api/v1/
```

### 1.2 Autentikáció
- **SPA (Vue.js)**: Cookie-based session (Sanctum)
- **Mobil (Flutter)**: Bearer token header: `Authorization: Bearer {token}`

### 1.3 Válaszformátum

Minden válasz JSON. Sikeres válasz:

```json
{
  "data": { ... },          // Egyes erőforrás
  "meta": { ... }           // Lapozás infó (listáknál)
}
```

vagy kollekcióknál:

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 245,
    "from": 1,
    "to": 25
  },
  "links": {
    "first": "...?page=1",
    "last": "...?page=10",
    "prev": null,
    "next": "...?page=2"
  }
}
```

Hiba válasz:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Hibaüzenet"]
  }
}
```

### 1.4 Közös query paraméterek

| Paraméter | Típus | Leírás |
|-----------|-------|--------|
| page | int | Oldalszám (alapértelmezett: 1) |
| per_page | int | Elemek száma oldalanként (10, 25, 50, 100; alapértelmezett: 25) |
| sort | string | Rendezési mező (pl. created_at, title) |
| order | string | Rendezési irány (asc / desc; alapértelmezett: desc) |
| search | string | Keresés (cím és leírás mezőkben) |

---

## 2. Auth végpontok

| Metódus | Útvonal | Leírás | Auth |
|---------|---------|--------|------|
| GET | /sanctum/csrf-cookie | CSRF cookie lekérése (SPA) | Nem |
| POST | /api/v1/auth/login | Bejelentkezés | Nem |
| POST | /api/v1/auth/logout | Kijelentkezés | Igen |
| POST | /api/v1/auth/logout-all | Kijelentkezés minden eszközről | Igen |
| POST | /api/v1/auth/forgot-password | Jelszó-visszaállítás kérése | Nem |
| POST | /api/v1/auth/reset-password | Új jelszó beállítása | Nem |
| POST | /api/v1/auth/accept-invitation | Meghívó elfogadása és jelszó beállítása | Nem |
| GET | /api/v1/auth/user | Bejelentkezett felhasználó adatai | Igen |

### Login részletek

**POST /api/v1/auth/login**

```json
Request:
{
  "email": "user@company.hu",
  "password": "password123",
  "device_name": "iPhone 15"     // csak mobil esetén
}

Response 200:
{
  "data": {
    "user": {
      "id": 1,
      "name": "Kovács János",
      "email": "user@company.hu",
      "avatar_url": "/storage/avatars/uuid.jpg",
      "role": {
        "id": 2,
        "name": "Diszpécser",
        "slug": "dispatcher"
      },
      "organization": {
        "id": 1,
        "name": "ABC Üzemeltetés Kft.",
        "slug": "abc"
      },
      "permissions": ["tickets.read", "tickets.create", "tickets.update", ...],
      "settings": {
        "theme": "dark",
        "color_scheme": "blue",
        "locale": "hu",
        "items_per_page": 25,
        "notification_email": true,
        "notification_push": true,
        "notification_sound": true
      }
    },
    "token": "1|abc123def456..."   // csak mobil módban
  }
}

Response 401:
{
  "message": "Hibás email cím vagy jelszó."
}

Response 429:
{
  "message": "Túl sok bejelentkezési kísérlet. Próbálja újra 15 perc múlva."
}
```

---

## 3. Felhasználó-kezelés végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/profile | Saját profil | Bejelentkezve |
| PUT | /api/v1/profile | Profil módosítása | Bejelentkezve |
| PUT | /api/v1/profile/password | Jelszó módosítása | Bejelentkezve |
| POST | /api/v1/profile/avatar | Profilkép feltöltése | Bejelentkezve |
| GET | /api/v1/settings | Beállítások lekérése | Bejelentkezve |
| PUT | /api/v1/settings | Beállítások mentése | Bejelentkezve |
| GET | /api/v1/users | Felhasználók listája | users.read |
| POST | /api/v1/users | Felhasználó meghívása | users.create |
| GET | /api/v1/users/{id} | Felhasználó adatai | users.read |
| PUT | /api/v1/users/{id} | Felhasználó módosítása | users.edit |
| DELETE | /api/v1/users/{id} | Felhasználó törlése | users.edit |
| PATCH | /api/v1/users/{id}/toggle-active | Aktiválás/deaktiválás | users.deactivate |

### Felhasználó lista szűrők

| Paraméter | Típus | Leírás |
|-----------|-------|--------|
| role | string | Szerepkör slug szerinti szűrés |
| group_id | int | Csoport szerinti szűrés |
| is_active | boolean | Aktív/inaktív szűrés |
| search | string | Név vagy email keresés |

---

## 4. Csoport végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/groups | Csoportok listája | Bejelentkezve |
| POST | /api/v1/groups | Új csoport | users.manage_roles |
| PUT | /api/v1/groups/{id} | Csoport módosítása | users.manage_roles |
| DELETE | /api/v1/groups/{id} | Csoport törlése | users.manage_roles |
| POST | /api/v1/groups/{id}/members | Tag hozzáadása | users.manage_roles |
| DELETE | /api/v1/groups/{id}/members/{userId} | Tag eltávolítása | users.manage_roles |

---

## 5. Szerepkör és jogosultság végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/roles | Szerepkörök listája | users.manage_roles |
| POST | /api/v1/roles | Új szerepkör | users.manage_roles |
| PUT | /api/v1/roles/{id} | Szerepkör módosítása | users.manage_roles |
| DELETE | /api/v1/roles/{id} | Szerepkör törlése | users.manage_roles |
| GET | /api/v1/permissions | Összes engedély | users.manage_roles |
| PUT | /api/v1/roles/{id}/permissions | Szerepkör engedélyei | users.manage_roles |

---

## 6. Bejelentés (Ticket) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/tickets | Bejelentések listája | tickets.read |
| POST | /api/v1/tickets | Új bejelentés | tickets.create |
| GET | /api/v1/tickets/{id} | Bejelentés részletei | tickets.read |
| PUT | /api/v1/tickets/{id} | Bejelentés módosítása | tickets.update |
| DELETE | /api/v1/tickets/{id} | Bejelentés törlése | tickets.delete |
| PATCH | /api/v1/tickets/{id}/status | Státusz módosítása | tickets.update |
| PATCH | /api/v1/tickets/{id}/assign | Felelős kijelölése | tickets.assign |
| POST | /api/v1/tickets/{id}/comments | Hozzászólás | tickets.read |
| GET | /api/v1/tickets/{id}/comments | Hozzászólások listája | tickets.read |
| GET | /api/v1/tickets/{id}/timeline | Timeline/napló | tickets.read |
| POST | /api/v1/tickets/{id}/attachments | Csatolmány feltöltése | tickets.update |
| GET | /api/v1/tickets/{id}/attachments | Csatolmányok listája | tickets.read |
| POST | /api/v1/tickets/{id}/follow | Követés | tickets.read |
| DELETE | /api/v1/tickets/{id}/follow | Követés megszüntetése | tickets.read |
| POST | /api/v1/tickets/{id}/reactions | Reakció/intézkedés hozzáadása | tickets.update |

### Bejelentés lista szűrők

| Paraméter | Típus | Leírás |
|-----------|-------|--------|
| status_id | int | Státusz |
| category_id | int | Kategória |
| priority | string | Prioritás (low, medium, high, critical) |
| assignee_id | int | Felelős |
| reporter_id | int | Bejelentő |
| location_id | int | Helyszín |
| floor_id | int | Szint |
| room_id | int | Helyiség |
| asset_id | int | Eszköz |
| date_from | date | Dátumtól |
| date_to | date | Dátumig |
| sla_overdue | boolean | SLA-t túllépettek |
| has_attachment | boolean | Van csatolmánya |
| search | string | Keresés cím és leírás mezőkben |

### Bejelentés létrehozás

**POST /api/v1/tickets**

```json
Request:
{
  "title": "Lift meghibásodás - B épület",
  "description": "A 3. emeleti lift nem működik, hibajelzést mutat.",
  "category_id": 5,
  "priority": "high",
  "location_id": 2,
  "floor_id": 8,
  "room_id": null,
  "asset_id": 15
}

Response 201:
{
  "data": {
    "id": 1234,
    "reference_number": "PV-2024-01234",
    "title": "Lift meghibásodás - B épület",
    "description": "A 3. emeleti lift nem működik, hibajelzést mutat.",
    "category": { "id": 5, "name": "Lift" },
    "status": { "id": 1, "name": "Új", "slug": "new", "color": "#3498db" },
    "priority": "high",
    "reporter": { "id": 42, "name": "Kovács János" },
    "assignee": null,
    "location": { "id": 2, "name": "B épület" },
    "floor": { "id": 8, "name": "3. emelet" },
    "room": null,
    "asset": { "id": 15, "name": "B-LIFT-03" },
    "sla_deadline": "2024-03-15T18:00:00Z",
    "attachments_count": 0,
    "comments_count": 0,
    "created_at": "2024-03-15T10:30:00Z",
    "updated_at": "2024-03-15T10:30:00Z"
  }
}
```

---

## 7. Bejelentés beállítás végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/ticket-categories | Kategóriák listája | Bejelentkezve |
| POST | /api/v1/ticket-categories | Új kategória | tickets.manage_categories |
| PUT | /api/v1/ticket-categories/{id} | Kategória módosítása | tickets.manage_categories |
| DELETE | /api/v1/ticket-categories/{id} | Kategória törlése | tickets.manage_categories |
| GET | /api/v1/ticket-statuses | Státuszok listája | Bejelentkezve |
| POST | /api/v1/ticket-statuses | Új státusz | tickets.manage_statuses |
| PUT | /api/v1/ticket-statuses/{id} | Státusz módosítása | tickets.manage_statuses |
| DELETE | /api/v1/ticket-statuses/{id} | Státusz törlése | tickets.manage_statuses |
| GET | /api/v1/ticket-reactions | Reakciók/intézkedések | Bejelentkezve |
| POST | /api/v1/ticket-reactions | Új reakció típus | tickets.manage_categories |
| PUT | /api/v1/ticket-reactions/{id} | Reakció módosítása | tickets.manage_categories |
| DELETE | /api/v1/ticket-reactions/{id} | Reakció törlése | tickets.manage_categories |

---

## 8. Feladat (Task) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/tasks | Feladatok listája | tasks.read |
| POST | /api/v1/tasks | Új feladat | tasks.create |
| GET | /api/v1/tasks/{id} | Feladat részletei | tasks.read |
| PUT | /api/v1/tasks/{id} | Feladat módosítása | tasks.update |
| DELETE | /api/v1/tasks/{id} | Feladat törlése | tasks.delete |
| PATCH | /api/v1/tasks/{id}/status | Státusz módosítása | tasks.update |
| PATCH | /api/v1/tasks/{id}/assign | Felelős(ök) kijelölése | tasks.assign |
| PATCH | /api/v1/tasks/{id}/star | Csillagozás | tasks.read |
| POST | /api/v1/tasks/{id}/comments | Hozzászólás | tasks.read |
| GET | /api/v1/tasks/{id}/comments | Hozzászólások | tasks.read |
| POST | /api/v1/tasks/{id}/attachments | Csatolmány feltöltés | tasks.update |
| GET | /api/v1/tasks/my | Saját feladatok | tasks.read |
| POST | /api/v1/recurring-tasks | Ismétlődő feladat beállítása | tasks.manage_recurring |
| PUT | /api/v1/recurring-tasks/{id} | Ismétlődés módosítása | tasks.manage_recurring |
| DELETE | /api/v1/recurring-tasks/{id} | Ismétlődés törlése | tasks.manage_recurring |

### Feladat lista szűrők

| Paraméter | Típus | Leírás |
|-----------|-------|--------|
| status | string | Státusz (new, in_progress, completed, postponed) |
| priority | string | Prioritás |
| assignee_id | int | Felelős |
| project_id | int | Projekt |
| location_id | int | Helyszín |
| due_date_from | date | Határidőtől |
| due_date_to | date | Határidőig |
| is_starred | boolean | Csillagozott |
| is_overdue | boolean | Lejárt határidejű |

---

## 9. Projekt végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/projects | Projektek listája | projects.read |
| POST | /api/v1/projects | Új projekt | projects.create |
| GET | /api/v1/projects/{id} | Projekt részletei | projects.read |
| PUT | /api/v1/projects/{id} | Projekt módosítása | projects.update |
| DELETE | /api/v1/projects/{id} | Projekt törlése | projects.delete |
| PATCH | /api/v1/projects/{id}/status | Státusz módosítása | projects.change_status |
| GET | /api/v1/projects/{id}/tasks | Projekt feladatai | projects.read |
| POST | /api/v1/projects/{id}/tasks | Feladat hozzáadása projekthez | tasks.create |
| GET | /api/v1/projects/{id}/teams | Csapatok | projects.read |
| POST | /api/v1/projects/{id}/teams | Csapat létrehozása | projects.manage_teams |
| PUT | /api/v1/projects/{id}/teams/{teamId} | Csapat módosítása | projects.manage_teams |
| DELETE | /api/v1/projects/{id}/teams/{teamId} | Csapat törlése | projects.manage_teams |
| POST | /api/v1/projects/{id}/teams/{teamId}/members | Tag hozzáadása | projects.manage_teams |
| DELETE | /api/v1/projects/{id}/teams/{teamId}/members/{userId} | Tag eltávolítása | projects.manage_teams |
| GET | /api/v1/projects/{id}/milestones | Mérföldkövek | projects.read |
| POST | /api/v1/projects/{id}/milestones | Mérföldkő létrehozása | projects.manage_milestones |
| PUT | /api/v1/projects/{id}/milestones/{msId} | Mérföldkő módosítása | projects.manage_milestones |
| DELETE | /api/v1/projects/{id}/milestones/{msId} | Mérföldkő törlése | projects.manage_milestones |
| POST | /api/v1/projects/{id}/follow | Követés | projects.read |
| DELETE | /api/v1/projects/{id}/follow | Követés törlése | projects.read |
| GET | /api/v1/projects/{id}/timeline | Timeline | projects.read |
| POST | /api/v1/projects/{id}/comments | Hozzászólás | projects.read |
| POST | /api/v1/projects/{id}/attachments | Csatolmány | projects.update |

---

## 10. Hibajegy (Issue) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/issues | Hibajegyek listája | issues.read |
| POST | /api/v1/issues | Új hibajegy | issues.create |
| GET | /api/v1/issues/{id} | Hibajegy részletei | issues.read |
| PUT | /api/v1/issues/{id} | Hibajegy módosítása | issues.update |
| DELETE | /api/v1/issues/{id} | Hibajegy törlése | issues.delete |
| PATCH | /api/v1/issues/{id}/status | Státusz módosítása | issues.update |
| PATCH | /api/v1/issues/{id}/assign | Felelős kijelölése | issues.assign |
| PATCH | /api/v1/issues/{id}/resolve | Megoldás rögzítése | issues.resolve |
| POST | /api/v1/issues/{id}/comments | Hozzászólás | issues.read |
| GET | /api/v1/issues/{id}/comments | Hozzászólások | issues.read |
| GET | /api/v1/issues/{id}/timeline | Timeline | issues.read |
| POST | /api/v1/issues/{id}/attachments | Csatolmány | issues.update |

---

## 11. Javaslat (Suggestion) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/suggestions | Javaslatok listája | suggestions.read |
| POST | /api/v1/suggestions | Új javaslat | suggestions.create |
| GET | /api/v1/suggestions/{id} | Javaslat részletei | suggestions.read |
| PUT | /api/v1/suggestions/{id} | Javaslat módosítása | suggestions.read (saját) |
| PATCH | /api/v1/suggestions/{id}/review | Elbírálás | suggestions.review |
| POST | /api/v1/suggestions/{id}/vote | Szavazás | suggestions.vote |
| DELETE | /api/v1/suggestions/{id}/vote | Szavazat visszavonása | suggestions.vote |
| POST | /api/v1/suggestions/{id}/comments | Hozzászólás | suggestions.read |

---

## 12. Dokumentumtár végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/document-folders | Mappák fa-struktúrája | documents.read |
| POST | /api/v1/document-folders | Új mappa | documents.manage_folders |
| PUT | /api/v1/document-folders/{id} | Mappa módosítása | documents.manage_folders |
| DELETE | /api/v1/document-folders/{id} | Mappa törlése | documents.manage_folders |
| GET | /api/v1/documents | Dokumentumok listája | documents.read |
| POST | /api/v1/documents | Dokumentum feltöltése | documents.upload |
| GET | /api/v1/documents/{id} | Dokumentum részletei | documents.read |
| PUT | /api/v1/documents/{id} | Dokumentum metaadat módosítás | documents.upload |
| DELETE | /api/v1/documents/{id} | Dokumentum törlése | documents.manage_folders |
| GET | /api/v1/documents/{id}/download | Dokumentum letöltése | documents.download |
| POST | /api/v1/documents/{id}/versions | Új verzió feltöltése | documents.upload |
| GET | /api/v1/documents/{id}/versions | Verziók listája | documents.read |
| GET | /api/v1/documents/{id}/versions/{versionId}/download | Verzió letöltése | documents.download |
| GET | /api/v1/document-tags | Címkék | Bejelentkezve |
| POST | /api/v1/document-tags | Új címke | documents.manage_folders |

---

## 13. Helyszín (Location) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/locations | Helyszínek listája | locations.read |
| POST | /api/v1/locations | Új helyszín | locations.create |
| GET | /api/v1/locations/{id} | Helyszín részletei | locations.read |
| PUT | /api/v1/locations/{id} | Helyszín módosítása | locations.update |
| DELETE | /api/v1/locations/{id} | Helyszín törlése | locations.delete |
| GET | /api/v1/locations/{id}/summary | Helyszín összefoglaló (bejelentések, feladatok, eszközök száma) | locations.read |
| GET | /api/v1/locations/{id}/floors | Szintek listája | locations.read |
| POST | /api/v1/locations/{id}/floors | Új szint | locations.manage_floors |
| PUT | /api/v1/floors/{id} | Szint módosítása | locations.manage_floors |
| DELETE | /api/v1/floors/{id} | Szint törlése | locations.manage_floors |
| GET | /api/v1/floors/{id}/rooms | Helyiségek listája | locations.read |
| POST | /api/v1/floors/{id}/rooms | Új helyiség | locations.manage_rooms |
| PUT | /api/v1/rooms/{id} | Helyiség módosítása | locations.manage_rooms |
| DELETE | /api/v1/rooms/{id} | Helyiség törlése | locations.manage_rooms |

---

## 14. Eszköz (Asset) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/assets | Eszközök listája | assets.read |
| POST | /api/v1/assets | Új eszköz | assets.create |
| GET | /api/v1/assets/{id} | Eszköz részletei | assets.read |
| PUT | /api/v1/assets/{id} | Eszköz módosítása | assets.update |
| DELETE | /api/v1/assets/{id} | Eszköz törlése | assets.delete |
| PATCH | /api/v1/assets/{id}/status | Állapot módosítása | assets.change_status |
| GET | /api/v1/assets/{id}/status-history | Állapot-történet | assets.read |
| GET | /api/v1/assets/{id}/tickets | Eszközhöz kapcsolódó bejelentések | assets.read |
| GET | /api/v1/assets/{id}/issues | Eszközhöz kapcsolódó hibajegyek | assets.read |
| GET | /api/v1/assets/{id}/maintenance-logs | Karbantartási napló | assets.read |
| POST | /api/v1/assets/{id}/generate-qr | QR kód generálás | assets.generate_qr |
| GET | /api/v1/assets/by-qr/{qrCode} | Eszköz lekérése QR kóddal | assets.read |
| GET | /api/v1/asset-types | Eszköz típusok | Bejelentkezve |
| POST | /api/v1/asset-types | Új eszköz típus | assets.manage_types |
| PUT | /api/v1/asset-types/{id} | Típus módosítása | assets.manage_types |

### Eszköz lista szűrők

| Paraméter | Típus | Leírás |
|-----------|-------|--------|
| asset_type_id | int | Típus |
| location_id | int | Helyszín |
| floor_id | int | Szint |
| room_id | int | Helyiség |
| status | string | Állapot |
| warranty_expired | boolean | Lejárt garanciájú |
| search | string | Keresés név, sorozatszám, leltári szám mezőkben |

---

## 15. Karbantartás (Maintenance) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/maintenance-schedules | Ütemtervek listája | maintenance.read |
| POST | /api/v1/maintenance-schedules | Új ütemterv | maintenance.manage_schedules |
| GET | /api/v1/maintenance-schedules/{id} | Ütemterv részletei | maintenance.read |
| PUT | /api/v1/maintenance-schedules/{id} | Ütemterv módosítása | maintenance.manage_schedules |
| DELETE | /api/v1/maintenance-schedules/{id} | Ütemterv törlése | maintenance.manage_schedules |
| GET | /api/v1/maintenance-logs | Karbantartási napló lista | maintenance.read |
| POST | /api/v1/maintenance-logs | Munka naplózása | maintenance.log_work |
| GET | /api/v1/maintenance-logs/{id} | Napló részletei | maintenance.read |
| PUT | /api/v1/maintenance-logs/{id} | Napló módosítása | maintenance.log_work |
| POST | /api/v1/maintenance-logs/{id}/attachments | Csatolmány | maintenance.log_work |

---

## 16. Szerződés (Contract) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/contractors | Alvállalkozók listája | contracts.read |
| POST | /api/v1/contractors | Új alvállalkozó | contracts.manage_contractors |
| GET | /api/v1/contractors/{id} | Alvállalkozó részletei | contracts.read |
| PUT | /api/v1/contractors/{id} | Alvállalkozó módosítása | contracts.manage_contractors |
| DELETE | /api/v1/contractors/{id} | Alvállalkozó törlése | contracts.manage_contractors |
| GET | /api/v1/contracts | Szerződések listája | contracts.read |
| POST | /api/v1/contracts | Új szerződés | contracts.create |
| GET | /api/v1/contracts/{id} | Szerződés részletei | contracts.read |
| PUT | /api/v1/contracts/{id} | Szerződés módosítása | contracts.update |
| DELETE | /api/v1/contracts/{id} | Szerződés törlése | contracts.delete |
| POST | /api/v1/contracts/{id}/locations | Helyszín hozzárendelése | contracts.update |
| DELETE | /api/v1/contracts/{id}/locations/{locId} | Helyszín eltávolítása | contracts.update |
| POST | /api/v1/contracts/{id}/assets | Eszköz hozzárendelése | contracts.update |
| DELETE | /api/v1/contracts/{id}/assets/{assetId} | Eszköz eltávolítása | contracts.update |

---

## 17. Értesítés és üzenet végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/notifications | Értesítések listája | Bejelentkezve |
| GET | /api/v1/notifications/unread-count | Olvasatlan szám | Bejelentkezve |
| PATCH | /api/v1/notifications/{id}/read | Olvasottnak jelölés | Bejelentkezve |
| POST | /api/v1/notifications/mark-all-read | Összes olvasott | Bejelentkezve |
| POST | /api/v1/fcm-tokens | FCM token regisztráció | Bejelentkezve |
| DELETE | /api/v1/fcm-tokens/{tokenId} | FCM token törlése | Bejelentkezve |
| GET | /api/v1/messages | Üzenetek listája | messages.send |
| POST | /api/v1/messages | Üzenet küldése | messages.send |
| GET | /api/v1/messages/{id} | Üzenet részletei | messages.send |
| PATCH | /api/v1/messages/{id}/read | Üzenet olvasottnak jelölése | messages.send |

---

## 18. Dashboard és riport végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/dashboard/summary | Áttekintő összefoglaló | reports.view_dashboard |
| GET | /api/v1/dashboard/tickets-by-status | Bejelentések státusz szerint | reports.view_dashboard |
| GET | /api/v1/dashboard/tickets-by-category | Bejelentések kategória szerint | reports.view_dashboard |
| GET | /api/v1/dashboard/tickets-by-location | Bejelentések helyszín szerint | reports.view_dashboard |
| GET | /api/v1/dashboard/tickets-trend | Bejelentések időszakonként | reports.view_dashboard |
| GET | /api/v1/dashboard/tasks-overview | Feladatok áttekintő | reports.view_dashboard |
| GET | /api/v1/dashboard/sla-performance | SLA teljesítmény | reports.view_dashboard |
| GET | /api/v1/dashboard/location/{id} | Helyszín-specifikus dashboard | reports.view_dashboard |
| GET | /api/v1/reports/export/tickets | Bejelentések exportálása (Excel) | reports.export |
| GET | /api/v1/reports/export/tasks | Feladatok exportálása | reports.export |
| GET | /api/v1/reports/export/maintenance | Karbantartás exportálása | reports.export |

### Dashboard summary válasz

```json
{
  "data": {
    "tickets": {
      "open": 45,
      "in_progress": 12,
      "overdue_sla": 3,
      "closed_today": 8,
      "closed_this_week": 34
    },
    "tasks": {
      "pending": 23,
      "overdue": 5,
      "completed_today": 7
    },
    "projects": {
      "active": 4,
      "suspended": 1
    },
    "maintenance": {
      "due_today": 2,
      "due_this_week": 8,
      "overdue": 1
    },
    "contracts": {
      "expiring_soon": 2
    }
  }
}
```

---

## 19. Keresés és aktivitás végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/search | Globális keresés | Bejelentkezve |
| GET | /api/v1/saved-filters | Mentett szűrők | Bejelentkezve |
| POST | /api/v1/saved-filters | Szűrő mentése | Bejelentkezve |
| PUT | /api/v1/saved-filters/{id} | Szűrő módosítása | Bejelentkezve |
| DELETE | /api/v1/saved-filters/{id} | Szűrő törlése | Bejelentkezve |
| GET | /api/v1/activity | Aktivitás stream | Bejelentkezve |

### Globális keresés

**GET /api/v1/search?q=lift&types=tickets,issues,assets**

```json
{
  "data": {
    "tickets": [
      { "id": 1234, "title": "Lift meghibásodás", "reference_number": "PV-2024-01234", ... }
    ],
    "issues": [
      { "id": 56, "title": "Lift ajtó érzékelő hiba", ... }
    ],
    "assets": [
      { "id": 15, "name": "B-LIFT-03", ... }
    ]
  }
}
```

---

## 20. Hír (News) végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/news | Hírek listája | Bejelentkezve |
| POST | /api/v1/news | Új hír | settings.manage_organization |
| GET | /api/v1/news/{id} | Hír részletei | Bejelentkezve |
| PUT | /api/v1/news/{id} | Hír módosítása | settings.manage_organization |
| DELETE | /api/v1/news/{id} | Hír törlése | settings.manage_organization |

---

## 21. SLA konfiguráció végpontok

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/sla-configs | SLA szabályok listája | settings.manage_sla |
| POST | /api/v1/sla-configs | Új SLA szabály | settings.manage_sla |
| PUT | /api/v1/sla-configs/{id} | Szabály módosítása | settings.manage_sla |
| DELETE | /api/v1/sla-configs/{id} | Szabály törlése | settings.manage_sla |

---

## 22. Fájl letöltés végpont

| Metódus | Útvonal | Leírás | Jogosultság |
|---------|---------|--------|-------------|
| GET | /api/v1/attachments/{id}/download | Csatolmány letöltése | Entitás jogosultság |
