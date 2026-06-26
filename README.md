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
- **Built-in player** — proxy streaming (put.io tokens stay on the server), multi-language audio tracks, [OpenSubtitles](https://www.opensubtitles.org/) subtitles
- **Multi-user** — separate accounts for the family, per-user content filters, invites and password reset via email
- **Smart TV & remote** — 10-foot TV mode, QR login from your phone, installable PWA
- **Resume playback** — “Continue watching” section synced across devices
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

## Quick install

1. **Clone** the repository into your server’s web directory.
2. **Install dependencies** with Composer (see below).
3. **Create an empty MySQL database** and note host, name, user, and password.
4. **Make `storage/` writable** by the web server.
5. **Open the URL** in your browser: the setup wizard starts (requirements → database → admin).
6. In **Admin → Settings**: connect put.io (OAuth), configure options, and run the first sync.
7. In **Admin → Classification**: link titles to TMDB (manual or automatic scan).

### Dependencies (Composer)

```bash
composer install --no-dev
```

Upload `vendor/` to the server too if you install locally and deploy via SFTP/rsync.

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

Sync refreshes the catalog from put.io and removes titles deleted in the cloud (including watch progress and linked metadata). Schedule it every 6–12 hours or use **Sync now** from the admin panel.

**CLI** (recommended):

```bash
/path/to/putmio/cron-sync.php
```

**HTTP** (if your cron supports `wget`/`curl`):

```
https://yourdomain.example/cron/sync?token=YOUR_CRON_TOKEN
```

The token is in **Admin → Settings** (generated at install). The CLI command is shown and copyable in the same panel.

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
- Admin streaming dashboard (bandwidth, active sessions)
- Sync logs with expandable detail of added/removed content
- Selective sync of content shared by put.io friends
- Installable **PWA**; offline cache for static assets
- Automatic asset **cache-busting** (`app.v*.css`) so updates land on TVs without manual cache clears

---

## Updates

The installed version is in `VERSION`. In **Admin → Updates** you can compare your local release with the latest on GitHub.

Configure in `config.php` (see `config.example.php`):

```php
'updates' => [
    'github_repo' => 'yourusername/putmio',
    'github_token' => '', // optional: avoids GitHub API rate limits
],
```

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
