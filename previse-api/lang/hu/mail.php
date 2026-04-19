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

];
