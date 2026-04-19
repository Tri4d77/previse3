<?php

return [
    'failed' => 'Hibás email cím vagy jelszó.',
    'password' => 'A megadott jelszó helytelen.',
    'throttle' => 'Túl sok bejelentkezési kísérlet. Kérjük próbálja újra :seconds másodperc múlva.',
    'inactive' => 'A fiókja inaktív. Kérjük forduljon a szuper-adminisztrátorhoz.',
    'unverified' => 'A fiókja még nem lett aktiválva. Kérjük fogadja el a meghívót az email-ben kapott linken.',
    'no_active_membership' => 'Nincs aktív szervezeti tagságod. Fordulj a szervezeti adminisztrátorhoz!',
    'invalid_membership' => 'Érvénytelen vagy inaktív tagság.',
    'logged_out' => 'Sikeres kijelentkezés.',
    'logged_out_all' => 'Sikeres kijelentkezés minden eszközről.',
    'reset_link_sent' => 'Ha a megadott email cím regisztrálva van, a jelszó-visszaállítási linket elküldtük.',
    'password_reset_success' => 'A jelszó sikeresen módosítva.',
    'invitation_invalid' => 'Érvénytelen vagy már felhasznált meghívó link.',
    'invitation_expired' => 'A meghívó link lejárt. Kérjen új meghívót a rendszergazdától.',
    'invitation_accepted' => 'Tagság sikeresen aktiválva. Most már bejelentkezhet.',
    'email_required' => 'Az email cím megadása kötelező.',
    'email_invalid' => 'Kérjük adjon meg érvényes email címet.',
    'password_required' => 'A jelszó megadása kötelező.',
    'unauthenticated' => 'Bejelentkezés szükséges.',
    'forbidden' => 'Nincs jogosultsága ehhez a művelethez.',

    // Jelszó- és sessionkezelés (M4)
    'password_same_as_old' => 'Az új jelszó nem egyezhet meg a jelenlegivel.',
    'password_changed' => 'A jelszó sikeresen módosítva.',
    'cannot_revoke_current_session' => 'A jelenlegi sessiont nem tudod innen kijelentkeztetni. Használd a Kijelentkezés gombot.',
    'session_not_found' => 'A session nem található.',
    'session_revoked' => 'Session sikeresen kijelentkeztetve.',
    'other_sessions_revoked' => 'Minden más eszköz kijelentkeztetve.',

    // Kétfaktoros hitelesítés (M5)
    '2fa_already_enabled' => 'A kétfaktoros hitelesítés már be van kapcsolva. Előbb kapcsold ki, ha újra szeretnéd beállítani.',
    '2fa_not_enabled' => 'A kétfaktoros hitelesítés nincs bekapcsolva.',
    '2fa_setup_not_started' => 'Nincs folyamatban lévő 2FA beállítás. Előbb kezdd el a bekapcsolást.',
    '2fa_invalid_code' => 'Érvénytelen ellenőrző kód.',
    '2fa_code_required' => 'Kötelező megadni vagy a 6 jegyű kódot, vagy egy recovery kódot.',
    '2fa_enabled' => 'Kétfaktoros hitelesítés sikeresen bekapcsolva. Mentsd el a recovery kódokat biztonságos helyre.',
    '2fa_disabled' => 'Kétfaktoros hitelesítés kikapcsolva.',
    '2fa_recovery_codes_regenerated' => 'Új recovery kódok generálva. A régiek már nem használhatók.',

    // Email-cím változtatás (M6)
    'email_same_as_current' => 'Az új email cím nem egyezhet meg a jelenlegivel.',
    'email_already_taken' => 'Ez az email cím már foglalt.',
    'email_change_requested' => 'Megerősítő levelet küldtünk az új címre. Kattints rá a változás érvényesítéséhez.',
    'email_change_confirmed' => 'Az email cím sikeresen módosítva.',
    'email_change_cancelled' => 'Az email-változtatási kérés visszavonva.',
    'email_change_invalid' => 'Érvénytelen vagy már felhasznált megerősítő link.',
    'email_change_expired' => 'A megerősítő link lejárt. Kezdd újra a változtatást.',
    'email_change_nothing_pending' => 'Nincs folyamatban email-változtatás.',

    // M7 - Szervezetből kilépés + fiók megszüntetése
    'membership_not_found' => 'A tagság nem található.',
    'left_organization' => 'Sikeresen kiléptél a szervezetből.',
    'cannot_leave_last_membership' => 'Ez az egyetlen aktív tagságod. Ha tényleg szeretnéd elhagyni, töröld a fiókod a Profil → Biztonság oldalon.',
    'cannot_leave_last_super_admin' => 'Te vagy az egyetlen szuper-admin. Előbb nevezz ki új szuper-admint, mielőtt elhagyod a Platform szervezetet.',
    'cannot_delete_last_super_admin' => 'Te vagy az egyetlen szuper-admin. Előbb hozz létre új szuper-adminisztrátort, mielőtt törölnéd a fiókod.',
    'account_deletion_scheduled' => 'Fiókod törlésre ütemezve. 30 napon belül a megadott email-címen be tudsz jelentkezni és visszavonhatod, utána véglegesen törlődik.',
    'account_deletion_cancelled' => 'Fiók-törlés visszavonva.',
    'account_already_scheduled_for_deletion' => 'A fiók már törlésre van ütemezve.',
    'account_not_scheduled_for_deletion' => 'A fiók nincs törlésre ütemezve.',
];
