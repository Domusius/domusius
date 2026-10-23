# Domusius

A small, modular home-dashboard platform written in plain **PHP + MySQL/MariaDB**.
The core handles users, login, an admin area and module management. Everything else –
solar production, electricity prices, an electric-car log, recipes … – is a **module** that
anyone can write and drop into `system/modules/`.

*Domusius er et lille, modulopbygget hjemmesystem i PHP og MySQL. Kernen styrer brugere, login,
admin og moduler. Alt andet er moduler, som du selv kan lave.*

> **Status:** early version (v0.00). Tested on PHP 8.2 with MySQL/MariaDB (Nginx on a Synology NAS).
> Expect rough edges, and please report them.

## Requirements

- PHP 8.x with the `pdo_mysql` extension enabled
- MySQL or MariaDB
- A web server (Apache or Nginx). No special rewrite rules are needed.
- Run it on a private network or behind a VPN (for example Tailscale) unless you know what you are doing.

## Install

1. Copy the files to your web server's document root (or a sub-folder).
2. Create an **empty** database, and a database user that only has access to that database.
3. Open `/install/install.php` in your browser and fill in the database details, the name of
   your system and an admin account (password at least 8 characters).
4. Log in, then go to **Admin → Moduler** to install and activate modules.

The installer writes `config/config.php` and `install/install.lock`. Neither file must ever be
committed to Git (both are in `.gitignore`). A template is in `config/config.example.php`.

## Modules

A module is a folder in `system/modules/<key>/` with a `module.json`, an optional `init.php`,
an `index.php` page, a dashboard widget, an admin settings page and database upgrade files.

- Read **[docs/MODULE-STANDARD.md](docs/MODULE-STANDARD.md)** (Danish) for the full description.
- Copy **[module-template/](module-template/)** to start a new module.

Modules run with the full privileges of PHP on your server. **Only install modules you trust.**

## Security notes

- Keep secrets (API keys, passwords) in the database or in `config/config.php`, never in module files.
- Remove or protect the `install/` folder after installation.
- Report security problems privately to the maintainer rather than in a public issue.

## License

**AGPL-3.0**, with additional terms about keeping the "Powered by Domusius" notice – see
[`LICENSE`](LICENSE) and [`NOTICE.md`](NOTICE.md). PHPMailer (in `system/PHPMailer/`) has its own license.

Copyright (C) 2026 Espersen-Glasius
