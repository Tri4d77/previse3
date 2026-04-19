<?php

return [

    'common' => [
        'button_fallback' => 'Ha a gomb nem működik, másold be a böngészőbe ezt a linket:',
        'footer_line1' => ':app — épület-üzemeltetési platform',
        'footer_line2' => 'Ezt az üzenetet automatikus rendszer küldte, kérjük ne válaszolj rá.',
    ],

    'invitation' => [
        'subject' => 'Meghívó :app — :organization',
        'heading' => 'Szia :name!',
        'intro_new_user' => ':inviter meghívott a :organization szervezetbe a következő szerepkörrel: :role.',
        'intro_existing_user' => ':inviter meghívott a :organization szervezetbe a következő szerepkörrel: :role.',
        'explain_new_user' => 'A meghíváshoz kattints az alábbi gombra, majd állítsd be a jelszavad. Ezt követően beléphetsz a rendszerbe.',
        'explain_existing_user' => 'Mivel már van fiókod a Previse-ben, csak erősítsd meg a meghívót az alábbi gombbal. Ezt követően ehhez a szervezethez is válthatsz a fejlécben lévő szervezet-választóval.',
        'action_new_user' => 'Meghívó elfogadása',
        'action_existing_user' => 'Tagság megerősítése',
        'expiry_note' => 'A meghívó <strong>:days nap</strong> után lejár. Ha nem te vagy a címzett, hagyd figyelmen kívül ezt az üzenetet.',
    ],

    'password_reset' => [
        'subject' => 'Jelszó-visszaállítás — :app',
        'heading' => 'Szia :name!',
        'intro' => 'Jelszó-visszaállítási kérést kaptunk a fiókodhoz. A jelszó megváltoztatásához kattints az alábbi gombra.',
        'ignore_note' => 'Ha nem te kérted a jelszó-visszaállítást, ezt az üzenetet biztonságosan figyelmen kívül hagyhatod — a jelszavad változatlan marad.',
        'action' => 'Új jelszó beállítása',
        'expiry_note' => 'A visszaállító link <strong>:minutes percig</strong> érvényes. Utána újra kell kérned a visszaállítást.',
    ],

    // M6 - Email-cím változtatás
    'email_change_confirm' => [
        'subject' => 'Email-cím változtatás megerősítése — :app',
        'heading' => 'Szia :name!',
        'intro' => 'Email-cím változtatást kezdeményeztél: :old_email → :new_email. A változtatás érvényesítéséhez kattints az alábbi gombra ezen az új címen.',
        'action' => 'Új email megerősítése',
        'expiry_note' => 'A megerősítő link <strong>:minutes percig</strong> érvényes. Utána a folyamatot újra kell indítanod.',
        'ignore_note' => 'Ha nem te kezdeményezted a változtatást, hagyd figyelmen kívül ezt az üzenetet — a fiókod email-címe nem változik.',
    ],
    'email_change_notice' => [
        'subject' => 'Biztonsági értesítés: email-cím változtatás — :app',
        'heading' => 'Szia :name!',
        'intro' => 'Email-cím változtatás indult a fiókodon. Új cím: :new_email. Időpont: :time. IP: :ip.',
        'not_you_warning' => 'Ha NEM te kezdeményezted ezt a változtatást, azonnal jelentkezz be és változtasd meg a jelszavad, vagy a fiókodnak küldd el a „Jelszóemlékeztetőt".',
        'security_tip' => 'Biztonsági tipp: engedélyezd a kétfaktoros hitelesítést a Profil → Biztonság oldalon.',
    ],

    // M6 - Biztonsági értesítések (általános sablon)
    'security' => [
        'footer_tip' => 'Biztonsági tipp: ha bármi gyanúsat látsz, jelentkezz be, jelentkeztess ki minden eszközt a Profil → Biztonság oldalon, és változtasd meg a jelszavad.',
        'not_you_warning' => 'Ha NEM te vagy a felelős ezért az eseményért, azonnal cselekedj: változtasd meg a jelszavad, jelentkeztess ki minden eszközt, és kapcsold be a 2FA-t (ha még nincs).',

        'password_changed' => [
            'subject' => 'Jelszó módosítva — :app',
            'heading' => 'Jelszó módosítva',
            'intro' => 'A fiókod jelszava módosult.',
        ],
        'two_factor_enabled' => [
            'subject' => '2FA bekapcsolva — :app',
            'heading' => 'Kétfaktoros hitelesítés bekapcsolva',
            'intro' => 'A fiókodra mostantól a bejelentkezéshez 2FA kód kell.',
        ],
        'two_factor_disabled' => [
            'subject' => '2FA kikapcsolva — :app',
            'heading' => 'Kétfaktoros hitelesítés kikapcsolva',
            'intro' => 'A fiókodról kikapcsolták a kétfaktoros hitelesítést.',
        ],
        'new_device_login' => [
            'subject' => 'Új bejelentkezés — :app',
            'heading' => 'Új eszközről / helyről történt bejelentkezés',
            'intro' => 'Új eszközről vagy IP-ről jelentkeztek be a fiókodba.',
        ],
        'email_changed' => [
            'subject' => 'Email-cím módosítva — :app',
            'heading' => 'Email-cím módosítva',
            'intro' => 'A fiókod email-címe sikeresen módosult.',
        ],

        'labels' => [
            'time' => 'Időpont',
            'ip' => 'IP-cím',
            'device' => 'Eszköz',
        ],
    ],

];
