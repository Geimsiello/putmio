# PutMio

**Your personal media center on [put.io](https://put.io)** — Plex-style catalog, secure streaming, multi-user. Self-hosted, open source, ready for TV and mobile.

Already storing files on put.io but missing a polished interface for the family? PutMio turns your cloud storage into a real home theater: posters, metadata, resume playback, invites for your household — no Plex, Jellyfin, or Emby subscriptions.

<!--
Screenshots (enable when ready — see image creation guide)

<p align="center">
  <img src="screenshots/catalog-home.png" alt="PutMio catalog with posters and Continue Watching section" width="800">
</p>

## Preview

| Title page | Player | TV login |
|:---:|:---:|:---:|
| <img src="screenshots/title-detail.png" alt="Title detail with TMDB metadata" width="400"> | <img src="screenshots/player.png" alt="Video player with subtitles" width="400"> | <img src="screenshots/tv-login.png" alt="Smart TV login with code and QR" width="400"> |

---
-->

---

## Why PutMio

| | Plex / similar services | PutMio |
|---|---|---|
| **Where files live** | Local server or NAS | Already on put.io |
| **Cost** | Hardware + often a subscription | Your put.io account + web hosting only |
| **Control** | Third-party platform | Your code, your data |
| **Family** | Free-tier limits | Unlimited users, email invites |

**In short:** if you use put.io as your media vault, PutMio is the front end you were missing — lightweight, installable on any PHP hosting, built for Smart TV, phone, and desktop.

### What you get

- **Cinematic catalog** — movies, TV series, and animation with posters, synopses, and automatic classification via [TMDB](https://www.themoviedb.org/)
- **Built-in player** — proxy streaming (put.io tokens stay on the server), multi-language audio tracks, subtitles from **put.io** (auto-sync) and [OpenSubtitles](https://www.opensubtitles.org/), configurable preload (Admin → Settings)
- **Multi-user** — separate accounts for the family, per-user content filters, invites and password reset via email
- **Smart TV & remote** — 10-foot TV mode, QR login from your phone, installable PWA
- **Resume playback** — “Continue watching” section synced across devices
- **Watchlist** — save films and series with a bookmark; personal list at `/watchlist`, slider on home when not empty, toggle via `POST /api/watchlist` (top-level titles only, not individual episodes)
- **Shared content** — selectively import files your put.io friends share with you
- **In-panel updates** — version check and core updates from GitHub Releases without touching config or database

### How it works

```
put.io (your files)  →  automatic sync  →  PutMio catalog  →  proxy streaming  →  TV / browser / PWA
                              ↑
                         TMDB (metadata)
```

1. Connect your put.io account via OAuth.
2. PutMio syncs the catalog (cron or manual) and enriches titles with TMDB.
3. You and your family browse and watch from the browser — the server proxies streams to put.io.

---

## Requirements

| Component | Minimum |
|---|---|
| **PHP** | 7.4+ (8.x recommended) — extensions: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json` |
| **Database** | MySQL 5.7+ or MariaDB |
| **Web server** | Apache, Nginx, or similar with URL rewriting and **HTTPS** |
| **put.io** | Active account with OAuth app ([create here](https://app.put.io/settings/account/oauth/apps)) |
| **Optional** | [TMDB](https://www.themoviedb.org/settings/api) API key · [OpenSubtitles](https://www.opensubtitles.org/) account · SMTP for email |

PutMio is plain PHP: no Node, Docker, or Redis required. Works on a VPS, shared hosting, or a home server — pick your stack.

---

## Installation

After install, complete setup in the browser (wizard → put.io OAuth → first sync). See [Quick setup](#quick-setup) below.

### SSH / VPS / NAS (recommended)

For a home server, NAS with shell access, or any machine where you can SSH in. Dependencies are installed **on the server** — no manual SFTP upload of `vendor/`.

**Prerequisites on the server**

- Git, Composer 2.x, PHP CLI 7.4+ (8.x recommended) with the same extensions as the web SAPI
- MySQL/MariaDB (local or remote)
- Web server (Apache/Nginx) with HTTPS and URL rewriting

**1. Clone and install dependencies**

```bash
cd /var/www   # or your NAS web root, e.g. /volume1/web
git clone https://github.com/Geimsiello/putmio.git putmio
cd putmio
composer install --no-dev --optimize-autoloader
```

> **NAS tip:** enable SSH on your NAS (Synology, QNAP, etc.), open a terminal session, and use the same commands. Point your web station / virtual host at the `putmio/` folder.

**2. Create the database**

```bash
mysql -u root -p -e "CREATE DATABASE putmio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'putmio'@'localhost' IDENTIFIED BY 'your-strong-password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON putmio.* TO 'putmio'@'localhost'; FLUSH PRIVILEGES;"
```

Adjust user, host, and privileges to match your environment.

**3. Permissions**

The web server user must be able to write to `storage/`:

```bash
# Linux — replace www-data with your web server user (nginx, apache, http, …)
sudo chown -R www-data:www-data storage/
chmod -R 775 storage/
```

**4. Web server**

- **Document root:** point the vhost at the PutMio folder (where `front.php` lives).
- **Subfolder install:** if the app is not at the domain root, set `RewriteBase` in `.htaccess` to match your path (default in the repo is `/putmio/`).
- **Apache:** `mod_rewrite` enabled; `.htaccess` is included.
- **Nginx:** route requests to `front.php` (try files + fallback), block direct access to `config.php` and `cron-sync.php`.

**5. Cron (after the wizard)**

```bash
crontab -e
```

```cron
# Sync catalog every 6 hours (use the PHP binary your CLI uses)
0 */6 * * * /usr/bin/php /var/www/putmio/cron-sync.php >> /var/www/putmio/storage/logs/cron-sync.log 2>&1
```

The exact CLI command is also shown in **Admin → Settings** once installed.

**6. Updates via SSH**

```bash
cd /var/www/putmio
git pull
composer install --no-dev --optimize-autoloader
```

You can also use **Admin → Updates** for core updates from GitHub Releases (see [Updates](#updates)).

---

### Shared hosting (SFTP / no SSH)

When you only have a control panel and file upload:

1. **Clone or download** the repository on your computer.
2. Run locally:

   ```bash
   composer install --no-dev
   ```

3. **Upload** the entire project (including `vendor/`) via SFTP/FTP to the web directory.
4. Continue with [Quick setup](#quick-setup) below.

---

### Quick setup

1. **Create an empty MySQL database** (panel or CLI) and note host, name, user, and password.
2. **Make `storage/` writable** by the web server.
3. **Open the URL** in your browser: the setup wizard starts (requirements → database → admin).
4. In **Admin → Settings**: connect put.io (OAuth), configure options, and run the first sync.
5. In **Admin → Classification**: link titles to TMDB (manual or automatic scan).

### put.io OAuth

Register this Redirect URI:

```
https://yourdomain.example/admin/oauth/putio/callback
```

(adjust host and path to match your installation)

### Database

PutMio **does not create the database** — prepare it beforehand. The wizard only installs tables (default prefix `pm_`, customizable).

### Diagnostics

On a 500 error at startup, open `check.php` to verify PHP, extensions, and permissions.

---

## Automatic sync (cron)

Sync refreshes the catalog from put.io and removes titles deleted in the cloud (including watch progress and linked metadata). Schedule catalog sync every **4–12 hours** on shared hosting, or use **Sync now** from the admin panel.

**Shared hosting (HTTP cron):** the catalog sync is kept lightweight (no subtitle import by default). If someone is streaming, the cron **skips** the run instead of blocking the site. Overlapping runs are prevented by a database lock.

On SSH/VPS/NAS installs, see step 5 in the [Installation](#installation) guide. On shared hosting without shell access, use the HTTP URLs below (shown in **Admin → Settings**).

**Catalog — CLI** (recommended on VPS):

```bash
/path/to/putmio/cron-sync.php
```

**Catalog — HTTP** (OVH shared hosting, `wget`/`curl`):

```
https://yourdomain.example/cron/sync?token=YOUR_CRON_TOKEN
```

**Subtitles — HTTP** (optional, once daily during low traffic):

```
https://yourdomain.example/cron/sync-subtitles?token=YOUR_CRON_TOKEN
```

The token is in **Admin → Settings** (generated at install).

Optional `config.php` keys (see `config.example.php`):

- `sync_defer_when_streaming` — postpone cron sync while streams are active (default `true`)
- `sync_subtitles_with_catalog` — include subtitle import in catalog sync (default `false`)
- `sync_stale_run_minutes` — mark stuck sync logs as failed after N minutes (default `180`)

---

## Features

- Guided installation wizard
- Login, family invites, password reset, “Remember me” (30 days)
- **TV access**: code + QR on the login page; authorization from your phone (`/authorize-device`); device paired for 30 days
- Movie / TV / animation catalog with TMDB classification
- TV episodes grouped by series (`S01E03`, parent folder on put.io); seasons merged when sharing the same `tmdb_id`; **Merge duplicate series** tool
- **Video.js** player: HTTP Range proxy, put.io HLS (multi-language audio), OpenSubtitles with per-user offset
- **Continue watching** section with resume playback
- **Italian / English** UI; account settings (language, devices, content filter)
- **TV mode** for Smart TV / remote: 10-foot layout, arrow-key navigation, fullscreen play on TV devices
- **Dedicated TV site** at `/tv/` (QR login, self-hosted assets, spatial remote navigation) — open manually until UA redirect is enabled; LG IPK can point here when ready
- Admin streaming dashboard (bandwidth, active sessions)
- Sync logs with expandable detail of added/removed content
- Selective sync of content shared by put.io friends
- Installable **PWA**; offline cache for static assets
- Automatic asset **cache-busting** (`app.v*.css`) so updates land on TVs without manual cache clears

---

### Personal Fire TV APK

For personal sideloading on a Fire Stick, you can keep an Android WebView wrapper outside Git in `local-firetv-app/` (ignored by `.gitignore`). The wrapper should hardcode your own PutMio URL, reuse the existing TV mode through its Fire TV user agent, and keep the APK keystore local so future APKs can update the installed app.

The web/core updater in **Admin → Updates** remains the source of PutMio updates. Rebuild and reinstall the APK only when you change the local Android wrapper itself.

### Personal LG webOS IPK

For personal installation on an LG TV in Developer Mode, keep a **packaged TV app** in `local-webos-app/` (ignored by `.gitignore`). Version 2.x bundles fonts, SVG icon fallbacks, and TV-optimized CSS inside the IPK. A local HTTP proxy forwards to your PutMio URL and injects those assets into the remote HTML — **no changes required on the PutMio server**.

Configure `config.json` with your `putmio_url`, then run `.\build-ipk.ps1` (downloads fonts and `webOSTV.js`, builds the IPK). See `local-webos-app/README.md` for details.

Install or update the generated `.ipk` manually with the LG/webOS CLI (`ares-install`). In-app APK/IPK self-updates are intentionally out of scope; the PutMio web/core updater still handles the server-side app.

Keep these files private and out of Git:

```text
local-firetv-app/
local-webos-app/
*.apk
*.ipk
*.jks
keystore.properties
```

---

## Updates

The installed version is in `VERSION`. In **Admin → Updates** you can compare your local release with the latest on GitHub.

Configure in `config.php` (see `config.example.php`). By default updates are pulled from the upstream repository:

```php
'updates' => [
    'github_repo' => 'Geimsiello/putmio',
    'github_token' => '', // optional: avoids GitHub API rate limits
],
```

If you maintain a fork, point `github_repo` to your own `owner/repo`.

A read-only Personal Access Token raises the limit from 60 to 5000 requests/hour — useful on shared hosting where many sites share the same outbound IP.

The updater touches **only the core** (code, templates, assets): `config.php`, `storage/`, and the database are left intact. An automatic ZIP backup is created in `storage/backups/`; schema migrations run via `Migrator` on the first request after an update.

**Obsolete file cleanup:** after an update, deprecated files are removed via the `REMOVED_FILES.json` manifest and a mirror sync of `src/`, `lang/`, `vendor/`, `sql/`, `templates/`, and `public/`.

---

## Project structure

```
putmio/
  front.php          # Front controller
  index.php          # Alias to front.php
  sw.js              # PWA service worker
  cron-sync.php      # Cron sync (CLI only)
  config.php         # Generated by wizard (do not commit)
  VERSION            # Semver platform version
  REMOVED_FILES.json # Obsolete files manifest per release
  composer.json
  vendor/
  src/               # Application code
  src/Update/        # Core updater (GitHub Releases)
  templates/         # Views
  lang/              # UI translations (it, en)
  public/assets/     # CSS/JS
  storage/           # Logs, posters, install lock
  sql/schema.sql     # DB schema
```

---

## Security

- `config.php` and `storage/.installed` must not be in the repository
- `noindex` and `robots.txt` to limit search indexing
- put.io tokens encrypted in the database
- CSRF on all POST forms

## Reinstalling

Delete `config.php` and `storage/.installed`, then reload the URL to run the wizard again.

## Roadmap

- **Multi-tenant** (planned): see [docs/MULTI_TENANT.md](docs/MULTI_TENANT.md)

---

## License & contributions

PutMio is open source. If you find it useful, a ⭐ on GitHub helps others discover it. Issues and pull requests are welcome.
