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
2. Have the login details for a MySQL/MariaDB user that is allowed to create databases and tables.
   You do **not** have to create the database or any tables yourself – the installer does it.
3. Open the address of the site in your browser (just the root folder, no special page). Until the
   installation is finished you are sent to the installer automatically.
4. Fill in the database host, user and password, a name for the database (it is created if it does not
   exist), the name of your system, and an admin account (password at least 8 characters).
5. When the installer is done, the same address shows the login page. Log in, then go to
   **Admin → Moduler** to install and activate modules.

The installer writes `config/config.php` and `install/install.lock`. The lock file is what stops the
installer from running again. Neither file must ever be committed to Git (both are in `.gitignore`).
A template is in `config/config.example.php`. To run the installer again, delete both files first.

## Modules

A module is a folder in `system/modules/<key>/` with a `module.json`, an optional `init.php`,
an `index.php` page, a dashboard widget, an admin settings page and database upgrade files.

- Read **[docs/MODULE-STANDARD.md](docs/MODULE-STANDARD.md)** (Danish) for the full description.
- Copy **[module-template/](module-template/)** to start a new module.

Modules run with the full privileges of PHP on your server. **Only install modules you trust.**

## Security notes

- Keep secrets (API keys, passwords) in the database or in `config/config.php`, never in module files.
- The installer is locked by `install/install.lock` once it has run, but you can also delete the `install/` folder afterwards.
- Report security problems privately to the maintainer rather than in a public issue.

## License

**AGPL-3.0**, with additional terms about keeping the "Powered by Domusius" notice – see
[`LICENSE`](LICENSE) and [`NOTICE.md`](NOTICE.md). PHPMailer (in `system/PHPMailer/`) has its own license.

Copyright (C) 2026 Espersen-Glasius
