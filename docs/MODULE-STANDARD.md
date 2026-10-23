# Domusius – modulstandard (v0.1)

Et modul er en mappe i `system/modules/<key>/`. Kernen finder, installerer og viser modulet ud fra
`module.json` og databasen. Kopiér `module-template/` (ligger i roden af repoet), omdøb mappen og ret `key` i `module.json`.

## 1. Mappestruktur

```
system/modules/<key>/
├── module.json          (påkrævet)  beskrivelse af modulet
├── init.php             (valgfri)   indlæses ved hver sidevisning, når modulet er aktivt
├── index.php            (påkrævet*) modulets side, vises på ?side=<key>
├── <key>.css            (valgfri)   modulets egen styling
├── widgets/
│   └── dashboard_view.php (valgfri) kort på dashboardet
├── admin/
│   └── settings.php     (valgfri)   modulets opsætning i admin
├── upgrades/
│   └── DB_<version>.php (valgfri)   databaseændringer, fx DB_0.01.php
└── uninstall.php        (valgfri)   fjerner modulets tabeller og indstillinger
```
\* Hvis `<key>.php` findes, bruges den i stedet for `index.php`.

## 2. module.json

| Felt | Påkrævet | Betydning |
|---|---|---|
| `key` | ja | Unikt id. Skal være identisk med mappenavnet og kolonnen `module_key` i databasen. Små bogstaver og bindestreg (`el-bil`). |
| `name` | ja | Navn der vises i admin og på dashboardet. |
| `description` | ja | Én linje om hvad modulet gør. |
| `version` | ja | Modulets databaseversion, fx `0.01`. Skal matche den nyeste `upgrades/DB_x.php`. |
| `db` | ja | `true`, hvis modulet har `upgrades/`. |
| `menu_title` | ja | Tekst i hovedmenuen. Menuen bygges af kernen. |
| `admin_menu_title` | nej | Tekst i admin-menuen (kræver `admin/settings.php`). |
| `requires` | nej | Liste af modul-nøgler, fx `["Elpriser"]`. **Skal være en liste `[]`**, ikke et objekt `{}`. |
| `protected` | kun kernen | `true` = kan ikke slås fra. Bruges kun af `core`, `system`, `users`. |

## 3. Hvad kernen gør for modulet

- **Side:** `?side=<key>` inkluderer `index.php`. `$pdo`, `$user` og `$site_name` er tilgængelige.
- **Menu:** hovedmenuen bygges fra tabellerne `modules` og `module_settings` (`menu_title`). Modulet skal ikke selv lave menupunkter.
- **Dashboard:** `widgets/dashboard_view.php` inkluderes i et kort. Ramme, overskrift, ikon og størrelse styres af kernen/databasen.
- **Admin:** `admin/settings.php` vises under Admin, kun for administratorer.
- **Opgraderinger:** `upgrades/DB_<version>.php` køres automatisk i rækkefølge, når versionen i databasen er lavere. Filen får `$pdo`. Brug `CREATE TABLE IF NOT EXISTS` og ændringer, der tåler at blive kørt to gange.

## 4. Hooks

Moduler kan registrere funktioner med `add_action('navn', function (...) { ... });`.
Disse hooks bliver i dag faktisk kaldt af kernen:

| Hook | Hvornår |
|---|---|
| `page_load` | Efter login, før siden tegnes |
| `header_scripts` | I `<head>` – til modulets CSS/JS |
| `dashboard_top` | Øverst på dashboardet |
| `footer_info` | I sidens fodlinje |
| `footer_scripts` | Før `</body>` |
| `user_login_sync` | Lige efter et vellykket login (fx hent data). Får brugeren som argument. |

Andre hook-navne (fx `main_menu`, `admin_menu`) kaldes ikke endnu og gør derfor ingenting.

## 5. Database og indstillinger

- Egne tabeller har modulets præfiks: `elbil_`, `growatt_`, `roi_`.
- Modulets indstillinger gemmes i `site_settings` med nøglen som præfiks: `<key>_<navn>`.
- **Hemmeligheder** (API-nøgler, adgangskoder, cookies) gemmes i databasen, aldrig i filer i modulet.
- Brug altid prepared statements (`$pdo->prepare()`), og `htmlspecialchars()` på alt, der skrives til siden.
- Skriv aldrig personlige standardværdier (adresser, priser, datoer) ind i koden. Brug neutrale standarder.

## 6. Regler

1. Et modul må ikke inkludere filer fra et andet modul. Brug `requires` og data i databasen.
2. Et modul skal kunne installeres og afinstalleres uden at efterlade tabeller eller indstillinger.
3. Ingen `config.php`-opslag i modulets filer. `$pdo` findes allerede.
4. Ingen backupfiler (`index_2026-03-13.php`), debug-filer eller testdata i modulet. Brug Git.
5. Moduler kører med fulde PHP-rettigheder på serveren. Installér kun moduler, du stoler på.

## 7. Tjekliste for nyt modul

- [ ] Mappen hedder det samme som `key`
- [ ] `module.json` har alle påkrævede felter, og `version` matcher nyeste `DB_x.php`
- [ ] `requires` er en liste
- [ ] Tabeller og indstillinger har præfiks
- [ ] Ingen hemmeligheder i filerne
- [ ] `uninstall.php` fjerner det, modulet har oprettet
- [ ] Testet: installér, brug, opgradér, afinstallér
