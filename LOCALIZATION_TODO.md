# Lokalizációs hibák (M9 utáni TODO)

Ezeket a hibákat akkor javítjuk, amikor az érintett komponenseken/oldalakon
egyébként is dolgozunk. A jelenlegi `i18n` rendszer kész, csak a hibás helyek
nincsenek mind átkötve a kulcsokra.

## Tudatos kihagyások (a user által jelzett)

- [ ] **MainLayout** – menüpont kereső mező `placeholder` szöveg
  (a sidebar kereső input-ja a navigációs menüpontok szűréséhez)
- [ ] **MainLayout** – szervezet-választó kereső mező `placeholder`
- [ ] **MainLayout** – „Szervezetek" menüpont szuper-adminoknak
  (nav.organizations vagy hasonló kulcs hiányzik / nincs használva)
- [ ] **MainLayout** – Profil menüben „Képernyő zárolása" gomb felirata
- [ ] **MainLayout** – Szuper-admin impersonation banner: „Szuper-admin mód: XY Karbantartó Kft. megtekintése" + „Vissza a Platformra" gomb felirata
- [ ] **Bármely további `placeholder=` attribútum**, ami inline magyar (át kell nézni)

## Átfogó sweep (M9 alatt nem fedett komponensek)

- [ ] **`pages/admin/UsersPage.vue`** stats kártyák: „Összes tag", „Aktív", „Meghívva", „Inaktív" (van már `users.*` kulcsok, de nincsenek használatban)
- [ ] **`pages/admin/UsersPage.vue`** szűrő-feliratok: „Összes szerepkör", „Minden státusz", „Törölt tagok megjelenítése", „Szűrők törlése", „Keresés név, email…", „Felhasználó", „Csatlakozott"
- [ ] **`pages/admin/UsersPage.vue`** lapozás: „Előző", „Következő" (van már `common.previous/next`, csak nincs használva)
- [ ] **`pages/admin/OrganizationsPage.vue`** (M2.5) – sok inline magyar
- [ ] **`components/common/OrgRow.vue`** – status menüben „Aktiválás", „Inaktiválás", „Megszüntetés" feliratok
- [ ] **`components/common/InviteUserModal.vue`** – még nem teljes
- [ ] **`components/common/InvitationSuccessModal.vue`** – még nem teljes
- [ ] **`pages/auth/AcceptInvitationPage.vue`** – ha van inline szöveg
- [ ] **`pages/auth/LockScreenPage.vue`** – feliratok ellenőrzése
- [ ] **`pages/auth/ForgotPasswordPage.vue`** – feliratok ellenőrzése
- [ ] **`pages/auth/SelectOrganizationPage.vue`** – feliratok ellenőrzése
- [ ] **`pages/dashboard/DashboardPage.vue`** – ha van benne valami

## i18n kulcs-optimalizálás

- [ ] **`back_to_login`** kulcs duplikáció van a `auth.*` namespace-en belül (két helyen ugyanaz a szöveg).
- [ ] **`common.cancel` vs `users.cancel` vs egyéb** – egyetlen `common.cancel` legyen, és minden komponens onnan használja.
- [ ] **`common.save`, `common.delete`, `common.actions`** – ugyanaz, ne legyen több helyen ugyanaz a kulcs.
- [ ] **Eseménycímke deduplikáció**: jelenleg `event_*` a `profile.*` alatt van; ha más oldalon (pl. admin auth log) is használnánk, vigyük át egy globális `events.*` namespace-be.
- [ ] **„Mégse" / „Cancel"**: jelenleg több helyen szerepel külön (`profile.email_pending_cancel`, `profile.email_change_button`, stb.) – ahol semantikailag azonos a „mégse" ott legyen `common.cancel`.

## Csomagolás

A javításokat 2 stratégiával végezzük:
1. **Inline javítás**: amikor egy komponensen egyébként is dolgozunk
2. **Dedikált M9.5 sweep**: ha a fenti lista nagyobbra duzzad, vagy a v1 előtt rendet akarunk vágni

## Backend lokalizáció

Az alábbiak már működnek, de a sweep alatt érdemes átnézni:
- `lang/hu/auth.php`, `lang/en/auth.php` — auth flow-k
- `lang/hu/mail.php`, `lang/en/mail.php` — email tartalmak
- `lang/hu/users.php`, `lang/en/users.php` — felhasználó-kezelés

Backend felesleges duplikáció nincs (gyors átnézés), de új kulcsoknál figyelni kell.
