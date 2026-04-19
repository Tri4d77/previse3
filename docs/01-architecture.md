# 01 - Rendszer-architektúra

> ⚠️ **Frissítve az M1–M2.5 fázisokban.**
> - User-modell a **User–Membership–Organization** architektúra szerint (lásd [11-user-membership.md](11-user-membership.md)) — új Model: `Membership`, kibővített `PersonalAccessToken`.
> - Szervezetek hierarchikusak (Platform → Subscriber → Client) és státusz-kezeltek (active/inactive/terminated).
> - Super-admin impersonation flow (token `context_organization_id` mezővel).
> - Fejlesztői környezet: **Docker** (PHP 8.4-fpm, MySQL 8, Nginx, phpMyAdmin) — nem Laragon/XAMPP.
> - Frontend build: **Vite 6**, **Tailwind 3** (nem Tailwind 4, a native binding-problémák miatt).

## 1. Áttekintés

A Previse v2 egy moduláris bejelentés-, feladat-, hibajegy- és projektkezelő rendszer, amelyet épület-üzemeltetők és karbantartó/kivitelező cégek számára fejlesztünk. A rendszer három klienst szolgál ki egyetlen REST API-n keresztül: webalkalmazás (Vue.js SPA), Android alkalmazás és iOS alkalmazás (Flutter).

## 2. Technológiai stack

| Réteg | Technológia | Verzió |
|-------|-------------|--------|
| Backend framework | Laravel | 11.x+ |
| PHP verzió | PHP | 8.4 |
| Adatbázis | MySQL / MariaDB | 8.x / 10.6+ |
| Frontend framework | Vue.js | 3.x (Composition API) |
| Frontend state | Pinia | 2.x |
| Frontend routing | Vue Router | 4.x |
| Frontend UI | Tailwind CSS + Headless UI | 3.x / 1.x |
| Frontend build | Vite | 6.x |
| Mobil framework | Flutter | 3.x+ |
| Mobil UI | Material 3 | - |
| API autentikáció | Laravel Sanctum | 4.x |
| Email | Laravel Mail (SMTP) | - |
| Push értesítések | Firebase Cloud Messaging (FCM) | - |
| Fájltárolás | Laravel Storage (lokális / S3) | - |
| Keresés | MySQL FULLTEXT (később: Meilisearch) | - |
| Hosting | CPanel shared hosting | - |

## 3. Architektúra-diagram

```
┌─────────────────────────────────────────────────────────┐
│                      KLIENSEK                           │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │  Vue.js SPA  │  │ Flutter App  │  │ Flutter App  │  │
│  │  (Web)       │  │ (Android)    │  │ (iOS)        │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │
│         │                 │                  │          │
└─────────┼─────────────────┼──────────────────┼──────────┘
          │                 │                  │
          │    HTTPS / JSON (REST API)         │
          │                 │                  │
┌─────────┼─────────────────┼──────────────────┼──────────┐
│         ▼                 ▼                  ▼          │
│  ┌─────────────────────────────────────────────────┐    │
│  │              Laravel API Gateway                │    │
│  │         (Sanctum Auth + Rate Limiting)          │    │
│  └─────────────────────┬───────────────────────────┘    │
│                        │                                │
│  ┌─────────────────────┼───────────────────────────┐    │
│  │              LARAVEL MODULOK                    │    │
│  │                     │                           │    │
│  │  ┌────────┐ ┌───────────┐ ┌──────────────┐     │    │
│  │  │ Auth   │ │ Tickets   │ │ Helyszínek   │     │    │
│  │  ├────────┤ ├───────────┤ ├──────────────┤     │    │
│  │  │ Users  │ │ Tasks     │ │ Eszközök     │     │    │
│  │  ├────────┤ ├───────────┤ ├──────────────┤     │    │
│  │  │ RBAC   │ │ Projects  │ │ Karbantartás │     │    │
│  │  ├────────┤ ├───────────┤ ├──────────────┤     │    │
│  │  │ Notify │ │ Issues    │ │ Szerződések  │     │    │
│  │  ├────────┤ ├───────────┤ ├──────────────┤     │    │
│  │  │ Files  │ │ Suggest.  │ │ Dashboard    │     │    │
│  │  │        │ │ Docs      │ │ Search       │     │    │
│  │  └────────┘ └───────────┘ └──────────────┘     │    │
│  └─────────────────────┬───────────────────────────┘    │
│                        │                                │
│  ┌─────────────────────┼───────────────────────────┐    │
│  │            ADATBÁZIS RÉTEG                      │    │
│  │                     │                           │    │
│  │  ┌─────────────┐  ┌┴────────────┐  ┌────────┐  │    │
│  │  │ MySQL 8.x   │  │ File Storage│  │ Cache  │  │    │
│  │  │ (Eloquent)  │  │ (local/S3)  │  │(file)  │  │    │
│  │  └─────────────┘  └─────────────┘  └────────┘  │    │
│  └─────────────────────────────────────────────────┘    │
│                                                         │
│                    CPANEL SZERVER                        │
└─────────────────────────────────────────────────────────┘
```

## 4. Backend architektúra (Laravel)

### 4.1 Könyvtárstruktúra

```
previse-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   ├── ForgotPasswordController.php
│   │   │   │   └── ResetPasswordController.php
│   │   │   ├── Api/
│   │   │   │   ├── TicketController.php
│   │   │   │   ├── TaskController.php
│   │   │   │   ├── ProjectController.php
│   │   │   │   ├── IssueController.php
│   │   │   │   ├── SuggestionController.php
│   │   │   │   ├── DocumentController.php
│   │   │   │   ├── LocationController.php
│   │   │   │   ├── AssetController.php
│   │   │   │   ├── MaintenanceController.php
│   │   │   │   ├── ContractController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── SearchController.php
│   │   │   │   ├── ActivityController.php
│   │   │   │   └── MessageController.php
│   │   │   └── Admin/
│   │   │       ├── RoleController.php
│   │   │       ├── PermissionController.php
│   │   │       ├── SettingsController.php
│   │   │       └── CategoryController.php
│   │   ├── Middleware/
│   │   │   ├── CheckPermission.php
│   │   │   └── CheckTenant.php
│   │   ├── Requests/
│   │   │   ├── Ticket/
│   │   │   ├── Task/
│   │   │   ├── Project/
│   │   │   └── ...
│   │   └── Resources/
│   │       ├── TicketResource.php
│   │       ├── TaskResource.php
│   │       └── ...
│   ├── Models/
│   │   ├── User.php
│   │   ├── Membership.php
│   │   ├── Organization.php
│   │   ├── PersonalAccessToken.php   # kibővített Sanctum (current_membership_id, context_organization_id)
│   │   ├── Ticket.php
│   │   ├── Task.php
│   │   ├── Project.php
│   │   ├── Issue.php
│   │   ├── Suggestion.php
│   │   ├── Document.php
│   │   ├── Location.php
│   │   ├── Asset.php
│   │   ├── Maintenance.php
│   │   ├── Contract.php
│   │   ├── Notification.php
│   │   ├── Message.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── Category.php
│   │   ├── Status.php
│   │   ├── Comment.php
│   │   ├── Timeline.php
│   │   ├── Attachment.php
│   │   └── ...
│   ├── Policies/
│   │   ├── TicketPolicy.php
│   │   ├── TaskPolicy.php
│   │   └── ...
│   ├── Notifications/
│   │   ├── TicketCreated.php
│   │   ├── TaskAssigned.php
│   │   ├── SlaWarning.php
│   │   └── ...
│   ├── Services/
│   │   ├── TicketService.php
│   │   ├── SlaService.php
│   │   ├── SearchService.php
│   │   ├── DashboardService.php
│   │   └── QrCodeService.php
│   ├── Enums/
│   │   ├── TicketStatus.php
│   │   ├── TaskStatus.php
│   │   ├── ProjectStatus.php
│   │   ├── Priority.php
│   │   └── AssetStatus.php
│   └── Observers/
│       ├── TicketObserver.php
│       ├── TaskObserver.php
│       └── ...
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── api.php
│   └── channels.php
├── config/
├── storage/
│   └── app/
│       └── uploads/
│           ├── tickets/
│           ├── tasks/
│           ├── projects/
│           ├── documents/
│           └── assets/
└── tests/
    ├── Feature/
    └── Unit/
```

### 4.2 Tervezési elvek

- **Service Layer Pattern**: Az üzleti logika Service osztályokban, nem a Controller-ekben
- **Form Request Validation**: Minden input validáció dedikált Request osztályban
- **API Resources**: Egységes JSON válaszformátum Resource osztályokkal
- **Observer Pattern**: Automatikus timeline/audit log bejegyzések model eseményeknél
- **Policy-based Authorization**: Minden CRUD művelet Policy-vel védve
- **Enum-based Statuses**: PHP 8.4 enum típusok a státuszokhoz és prioritásokhoz

### 4.3 API konvenciók

- **Base URL**: `/api/v1/`
- **Formátum**: JSON (application/json)
- **Autentikáció**: Bearer token (mobil) / Cookie (SPA)
- **Válasz struktúra**:

```json
{
  "data": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 245
  }
}
```

- **Hiba válasz**:

```json
{
  "message": "Validation error",
  "errors": {
    "title": ["A cím megadása kötelező."]
  }
}
```

- **HTTP státusz kódok**: 200 (OK), 201 (Created), 204 (No Content), 400 (Bad Request), 401 (Unauthorized), 403 (Forbidden), 404 (Not Found), 422 (Validation Error), 429 (Too Many Requests), 500 (Server Error)

## 5. Frontend architektúra (Vue.js SPA)

### 5.1 Könyvtárstruktúra

```
previse-web/
├── src/
│   ├── assets/
│   │   ├── css/
│   │   └── images/
│   ├── components/
│   │   ├── common/
│   │   │   ├── AppHeader.vue
│   │   │   ├── AppSidebar.vue
│   │   │   ├── AppBreadcrumb.vue
│   │   │   ├── DataTable.vue
│   │   │   ├── SearchFilter.vue
│   │   │   ├── FileUploader.vue
│   │   │   ├── StatusBadge.vue
│   │   │   ├── Timeline.vue
│   │   │   ├── CommentSection.vue
│   │   │   ├── Pagination.vue
│   │   │   └── Modal.vue
│   │   ├── tickets/
│   │   ├── tasks/
│   │   ├── projects/
│   │   ├── issues/
│   │   ├── locations/
│   │   ├── assets/
│   │   ├── dashboard/
│   │   └── admin/
│   ├── composables/
│   │   ├── useAuth.js
│   │   ├── useApi.js
│   │   ├── useNotifications.js
│   │   ├── useSearch.js
│   │   ├── usePagination.js
│   │   └── useFileUpload.js
│   ├── layouts/
│   │   ├── MainLayout.vue
│   │   ├── AuthLayout.vue
│   │   └── AdminLayout.vue
│   ├── pages/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── tickets/
│   │   ├── tasks/
│   │   ├── projects/
│   │   ├── issues/
│   │   ├── suggestions/
│   │   ├── documents/
│   │   ├── locations/
│   │   ├── assets/
│   │   ├── maintenance/
│   │   ├── contracts/
│   │   ├── messages/
│   │   ├── profile/
│   │   └── admin/
│   ├── router/
│   │   └── index.js
│   ├── stores/
│   │   ├── auth.js
│   │   ├── tickets.js
│   │   ├── tasks.js
│   │   ├── projects.js
│   │   ├── notifications.js
│   │   ├── ui.js
│   │   └── ...
│   ├── services/
│   │   └── api.js          (Axios instance + interceptors)
│   ├── utils/
│   │   ├── formatters.js
│   │   ├── validators.js
│   │   └── constants.js
│   ├── App.vue
│   └── main.js
├── public/
├── index.html
├── vite.config.js
├── tailwind.config.js
└── package.json
```

### 5.2 Tervezési elvek

- **Composition API**: Minden komponens `<script setup>` szintaxissal
- **Pinia Stores**: Modulonként egy-egy store a state kezeléshez
- **Composables**: Újrahasználható logika (auth, API hívások, szűrők)
- **Lazy Loading**: Route-szintű code splitting `defineAsyncComponent()`-tel
- **Responsive Design**: Tailwind CSS breakpoint-ok (mobile-first)
- **Dark/Light Mode**: Tailwind `dark:` osztályok, felhasználói preferencia

## 6. Mobil architektúra (Flutter)

### 6.1 Könyvtárstruktúra

```
previse_mobile/
├── lib/
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart
│   │   │   └── interceptors.dart
│   │   ├── auth/
│   │   │   └── auth_service.dart
│   │   ├── storage/
│   │   │   └── secure_storage.dart
│   │   ├── theme/
│   │   │   ├── app_theme.dart
│   │   │   └── colors.dart
│   │   └── utils/
│   │       ├── formatters.dart
│   │       └── validators.dart
│   ├── features/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── tickets/
│   │   ├── tasks/
│   │   ├── projects/
│   │   ├── issues/
│   │   ├── locations/
│   │   ├── assets/
│   │   │   └── qr_scanner/
│   │   ├── maintenance/
│   │   ├── notifications/
│   │   ├── messages/
│   │   └── profile/
│   ├── models/
│   │   ├── ticket.dart
│   │   ├── task.dart
│   │   ├── project.dart
│   │   └── ...
│   ├── providers/
│   │   ├── auth_provider.dart
│   │   ├── ticket_provider.dart
│   │   └── ...
│   ├── widgets/
│   │   ├── status_badge.dart
│   │   ├── timeline_widget.dart
│   │   ├── comment_section.dart
│   │   └── file_picker.dart
│   └── main.dart
├── assets/
├── android/
├── ios/
└── pubspec.yaml
```

### 6.2 Tervezési elvek

- **Feature-first Architecture**: Funkciónként szervezett kód
- **Provider/Riverpod**: State management
- **Dio**: HTTP kliens (interceptor-okkal)
- **Secure Storage**: Token tárolás (flutter_secure_storage)
- **QR Scanner**: mobile_scanner package az eszköz-azonosításhoz
- **Push Notifications**: Firebase Messaging
- **Offline Support**: Hive/SQLite lokális cache (későbbi fázis)

## 7. Adatbázis

### 7.1 Alapelvek

- **Eloquent ORM**: Minden tábla Eloquent Model-lel
- **Migrations**: Verziózott séma-változások
- **Soft Deletes**: Logikai törlés ahol releváns
- **Timestamps**: `created_at`, `updated_at` minden táblán
- **UUID**: Elsődleges kulcsként fontolóra vehető (API biztonság)
- **Indexek**: Keresési mezőkön és foreign key-eken

### 7.2 Multi-tenancy megközelítés

A rendszer **szervezet-alapú multi-tenancy**-t alkalmaz egyetlen adatbázison belül, **membership-kontextusban**:
- A bejelentkezett user aktuális szervezete a tokenből (`personal_access_tokens.current_membership_id` vagy super-admin esetén `context_organization_id`) származik
- Minden fő entitás tartalmaz egy `organization_id` foreign key-t
- Middleware / Global Scope szűri a lekérdezéseket az aktuális szervezetre
- Super-admin impersonation esetén a `context_organization_id` adja a scope-ot (audit-naplózott)
- Szervezetek **hierarchikusak** (Platform → Subscriber → Client) — a subscriber-admin láthatja/kezelheti a client-leszármazottait

## 8. Biztonság

- **HTTPS**: Minden kommunikáció titkosítva
- **Sanctum**: SPA cookie-based auth + API token auth
- **Rate Limiting**: Laravel throttle middleware (API-n)
- **CORS**: Konfigurált origin-ek
- **Input Validation**: Form Request osztályok
- **SQL Injection védelem**: Eloquent prepared statements
- **XSS védelem**: Vue.js automatikus HTML escaping + Laravel `e()` helper
- **CSRF**: Sanctum automatikus CSRF védelem SPA módban
- **File Upload**: Típus és méret ellenőrzés, eredeti fájlnév eltávolítása
- **Password Hashing**: bcrypt (Laravel alapértelmezett)

## 9. Teljesítmény (CPanel korlátok figyelembevételével)

- **Eager Loading**: N+1 query probléma elkerülése `with()`-tel
- **Pagination**: Minden lista végpont lapozott
- **File-based Cache**: Redis helyett file cache CPanel-en
- **Queue**: `database` driver (Redis helyett, CPanel-en)
- **Image Optimization**: Feltöltéskor tömörítés és resize
- **API Response Caching**: Gyakran lekérdezett adatok (kategóriák, státuszok)

## 10. Fejlesztési környezet

- **Lokális fejlesztés**: saját **Docker Compose** stack (PHP 8.4-fpm, MySQL 8, Nginx, phpMyAdmin, Mailpit az M3-tól)
- **Verziókezelés**: Git (GitHub/GitLab)
- **API tesztelés**: PHPUnit + Postman/Insomnia
- **Frontend tesztelés**: Vitest + Vue Test Utils
- **Kódminőség**: Laravel Pint (PHP CS), ESLint + Prettier (JS)
- **API dokumentáció**: Swagger/OpenAPI (L5-Swagger package)
