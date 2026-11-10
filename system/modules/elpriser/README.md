# El-priser (Domusius-modul)

Henter daglige elpriser og viser dem som graf og på dashboardet. Prisen regnes om til en **samlet pris pr. kWh** med afgifter og moms, så den kan bruges til at se, hvad strømmen faktisk koster.

- Kilde 1: **Energi Data Service** (Energinet), åbne data uden nøgle. Bruger datasættet `DayAheadPrices` fra 1. oktober 2025 og `Elspotprices` for ældre dage.
- Kilde 2: et **Google-ark**, der er udgivet som CSV.
- Egne kilder kan tilføjes (se `engines/_skabelon.php`).

Data fra Energi Data Service tilhører Energinet. Se vilkårene for brug på energidataservice.dk.

## Installation

1. Læg mappen `elpriser` i `system/modules/` i din Domusius.
2. Åbn **Admin → Moduler**, tryk **Installér** og derefter **Aktivér**.
3. Åbn **El-pris Opsætning** (under Ekstra moduler i admin-menuen).
4. Vælg kilde og prisområde (DK1 eller DK2), og tryk **Gem indstillinger**.
5. Tilføj dine afgifter under **Afgifter og tarif**, hvis du vil have den samlede pris. Uden afgifter er prisen kun spotprisen med moms.
6. Tryk **Hent nu**. Slå eventuelt **Hent manglende dage automatisk** til.
7. Under **Vis på dashboard** kan du vælge, at modulet vises på dashboardet.

## Sådan regnes prisen

```
samlet pris = (spotpris + elafgift + transport + abonnement) × (1 + moms)
```

Alle tal er kr pr. kWh uden moms. Spotpriser, der kommer som kvarterspriser, slås sammen til timer, og gennemsnit, højeste og laveste regnes ud fra timepriserne. Afgifter gælder fra deres startdato. Ændrer du afgifterne, så hent perioden igen for at regne ældre dage om.

## Google-ark som kilde

Første række skal have kolonnerne `dato`, `spot_gns` og gerne `spot_max` og `spot_min` (spotpriser i kr pr. kWh uden afgifter og moms). Udgiv arket på nettet som CSV, og indsæt adressen under **Kilde og region**. Kun `https://` er tilladt.

## Database

Modulet bruger to tabeller, `electricity_prices` og `electricity_rates_history`, og indstillinger med præfikset `elpriser_` i `site_settings`. Afinstallation fjerner alle tre.

## Filer

```
elpriser/
├── module.json
├── init.php            CSS og automatisk hentning ved login
├── functions.php       fælles funktioner og regnemåden
├── index.php           modulets side
├── uninstall.php
├── style.css
├── admin/settings.php  opsætning
├── engines/            kilder (energinet, google_sheets, _skabelon)
├── upgrades/DB_0.00.php
└── widgets/
```

## Flytning fra et ældre system

Har du tidligere haft et ældre El-priser-modul med nøglen `Elpriser` og indstillinger, der hedder `el_...`, skal nøglerne omdøbes, og kilden skal have et nyt navn. **Tag en backup af databasen først.**

| Gammel indstilling | Ny indstilling |
|---|---|
| `el_region` | `elpriser_region` |
| `el_auto_fetch` eller `elpriser_auto_fetch` | `elpriser_auto_fetch` |
| `el_fetch_method` (`Energinet`, `Google Sheets`) | `elpriser_fetch_method` (`energinet`, `google_sheets`) |
| `el_sheets_url` | `elpriser_sheets_url` |

Modulets nøgle skal også omdøbes fra `Elpriser` til `elpriser` i tabellerne `modules` og `module_settings`, og i andre moduler, der kræver det (fx ROI).
