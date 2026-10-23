# Mappestruktur / Folder structure

Oversigt over filerne i Domusius (v0.00). Filer markeret med *oprettes af installationen* findes ikke i
repoet, men dannes, når systemet installeres, og må aldrig lægges i Git.

*Overview of the files in Domusius (v0.00). Files marked as created by the installer are not in the
repository; they are generated at install time and must never be committed.*

```
domusius/
├─ .gitignore
├─ forgot_password.php
├─ index.php
├─ LICENSE
├─ logout.php
├─ NOTICE.md
├─ README.md
├─ register.php
├─ reset_password.php
├─ admin/
│  ├─ admin_panel.php
│  └─ pages/
│     ├─ brugere.php
│     ├─ log.php
│     ├─ log_admin_action.php
│     ├─ mail_system.php
│     ├─ modules.php
│     └─ system.php
├─ assets/
│  ├─ admin.css
│  └─ style.css
├─ config/
│  ├─ config.example.php
│  ├─ config.php   ← oprettes af installationen
│  └─ init.php
├─ docs/
│  └─ MODULE-STANDARD.md
├─ hooks/
│  └─ README.md
├─ inc/
│  ├─ dashboard.php
│  ├─ hooks.php
│  ├─ login_view.php
│  ├─ module_loader.php
│  └─ profil.php
├─ install/
│  ├─ db_check.php
│  ├─ install.lock   ← oprettes af installationen
│  ├─ install.php
│  └─ DB/
│     └─ setup_db.sql
├─ module-template/
│  ├─ eksempel.css
│  ├─ index.php
│  ├─ init.php
│  ├─ module.json
│  ├─ uninstall.php
│  ├─ admin/
│  │  └─ settings.php
│  ├─ upgrades/
│  │  └─ DB_0.01.php
│  └─ widgets/
│     ├─ dashboard_view.php
│     └─ widgets.json
└─ system/
   ├─ db_version.php
   ├─ mail_functions.php
   ├─ modules/
   │  ├─ module_manager.php
   │  ├─ core/
   │  │  └─ module.json
   │  ├─ system/
   │  │  └─ module.json
   │  └─ users/
   │     └─ module.json
   ├─ PHPMailer/
   │  ├─ DSNConfigurator.php
   │  ├─ Exception.php
   │  ├─ OAuth.php
   │  ├─ OAuthTokenProvider.php
   │  ├─ PHPMailer.php
   │  ├─ POP3.php
   │  └─ SMTP.php
   └─ upgrades/
      ├─ DB_0.01.php
      ├─ DB_0.02.php
      ├─ DB_0.03.php
      ├─ DB_0.10.php
      └─ DB_1.01.php
```

## Kort om mapperne

| Mappe | Indhold |
|---|---|
| `admin/` | Administrationspanelet og dets sider (brugere, system, moduler, mail, log) |
| `assets/` | Fælles CSS |
| `config/` | Databaseforbindelse (`config.php` oprettes af installationen) og `config.example.php` som skabelon |
| `docs/` | Dokumentation, bl.a. [MODULE-STANDARD.md](MODULE-STANDARD.md) |
| `hooks/` | Valgfri PHP-filer, der indlæses efter login |
| `inc/` | Kernen: hook-system, modulindlæser, dashboard, login-visning og profil |
| `install/` | Installationen og databaseskemaet (`DB/setup_db.sql`) |
| `module-template/` | Tom skabelon til et nyt modul |
| `system/` | Modulhåndtering, mailfunktioner, PHPMailer og kernens databaseopgraderinger |
| `system/modules/` | Her ligger modulerne. Kernemodulerne `core`, `system` og `users` følger med |

Installerede moduler lægges som egne mapper under `system/modules/<modulnavn>/`.
