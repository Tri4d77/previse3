# Previse v2 — Fejlesztési konvenciók

## Migrációk

**Minden új migrációban minden mezőhöz írjunk magyar nyelvű `->comment('…')` megjegyzést**,
ami röviden leírja a mező szerepét. Ez a konvenció vonatkozik:

- Új tábla létrehozására (`Schema::create(...)`): MINDEN oszlophoz comment, beleértve
  az `id()`, `foreignId()`, `timestamps()` esetén is (ha értelmes).
- Meglévő táblához oszlop-hozzáadásra (`$table->...->after(...)`): az új oszlopokhoz
  comment kötelező.

Példa:

```php
$table->id()->comment('Elsődleges kulcs');
$table->foreignId('organization_id')
    ->constrained()
    ->cascadeOnDelete()
    ->comment('Tulajdonos szervezet — a katalógus org-specifikus');
$table->string('name', 50)->comment('A címke megjelenítendő neve');
$table->string('color', 20)->default('teal')->comment('Tailwind színkulcs (slate, red, blue, teal, …)');
```

A `timestamps()` és index-/unique-konstrukciók NEM kapnak commentet — csak az adatmezők.
