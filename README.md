# PutMio

Media center personale su [put.io](https://put.io) — catalogo stile Plex, streaming via proxy, multi-utente.

## Requisiti

- PHP 7.4+ (consigliato 8.x) con estensioni: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`
- MySQL 5.7+ / MariaDB
- Apache con `mod_rewrite` e HTTPS
- Account [put.io](https://put.io)
- (Opzionale) API key [TMDB](https://www.themoviedb.org/settings/api)
- (Opzionale) Account OpenSubtitles per i sottotitoli

## Dipendenze (Composer)

PutMio usa [Composer](https://getcomposer.org/) per gestire le dipendenze PHP (attualmente [PHPMailer](https://github.com/PHPMailer/PHPMailer) per l’invio email).

Dalla root del progetto:

```bash
composer install --no-dev
```

In produzione carica anche la cartella `vendor/` sul server. Se non hai accesso SSH, esegui `composer install` in locale e trasferisci `vendor/` via FTP/SFTP insieme agli altri file.

## Installazione

1. Clona o scarica il repository nella directory web del tuo hosting (sottocartella o document root).
2. Installa le dipendenze Composer (vedi sopra).
3. Crea un **database MySQL vuoto** dal pannello del provider e annota host, nome database, utente e password.
4. Verifica che `storage/` sia scrivibile dal web server (es. permessi 755 o 775).
5. Apri l’URL dell’app nel browser. Parte il wizard di installazione:
   - Controllo requisiti di sistema
   - **Database**: inserisci le credenziali del DB già creato e il prefisso tabelle (default `pm_`)
   - PutMio crea **solo le tabelle** nel database
   - Creazione account amministratore (+ SMTP opzionale)
6. Accedi e vai in **Admin → Impostazioni**:
   - Inserisci `client_id` e `client_secret` da [put.io OAuth Apps](https://app.put.io/settings/account/oauth/apps)
   - Registra come Redirect URI: `https://tuodominio.example/admin/oauth/putio/callback` (adatta host e percorso alla tua installazione)
   - Clic **Collega account put.io**
   - (Opzionale) Configura **SMTP** per inviti famiglia e reset password
   - (Opzionale) Configura **OpenSubtitles** per i sottotitoli
   - **Sincronizza ora**
7. Classifica i titoli in **Admin → Classificazione**: manualmente oppure con **Scansione TMDB**.

### Database

PutMio **non crea il database**. Va creato in anticipo dal pannello del provider; il wizard installa solo le tabelle (prefisso predefinito `pm_`, personalizzabile).

### Diagnostica

In caso di errore 500 all’avvio, apri `check.php` nella cartella dell’app per verificare PHP, estensioni e permessi.

## Sync automatico (cron)

La sync aggiorna il catalogo da put.io e **rimuove** i titoli eliminati sul cloud (inclusi progressi visione e metadati collegati). Pianificala ogni 6–12 ore, oppure usa **Sincronizza ora** manualmente.

**Script CLI** (adatto alla maggior parte degli hosting con cron PHP):

```
./percorso-installazione/cron-sync.php
```

Adatta il percorso alla posizione reale sul server (es. `./putmio/cron-sync.php` se l’app è in una sottocartella `putmio/`). Il comando è visibile e copiabile in **Admin → Impostazioni**.

**URL HTTP** (se il cron del provider supporta `wget` o `curl`):

```
https://tuodominio.example/percorso/cron/sync?token=IL_TUO_CRON_TOKEN
```

Il token è in **Admin → Impostazioni** (generato in installazione).

## Funzionalità

- Wizard installazione guidato
- Login, inviti famiglia via email (SMTP), reset password, «Ricordami» (sessione persistente 30 giorni)
- **Accesso da TV / altro dispositivo**: tab «QR / TV» nella pagina login con codice e QR (su Smart TV LG si apre automaticamente); autorizzazione dal telefono già connesso (`/authorize-device`); dopo l’autorizzazione il dispositivo resta associato **30 giorni** (revoca al logout o al cambio password)
- **Smart TV LG (webOS):** il browser integrato può non supportare la proprietà CSS `gap`; PutMio applica fallback con margini (`@supports`) così catalogo, slider e header mantengono le spaziature corrette
- Catalogo film / serie / animazione con classificazione TMDB (scansione e associazione in blocco)
- Episodi TV raggruppati automaticamente per serie (pattern `S01E03` nel nome file; se manca il titolo serie nel file, viene usato il nome della cartella padre su put.io)
- TMDB on-demand (admin)
- Player **Video.js** con proxy streaming Range HTTP e **sottotitoli OpenSubtitles** (ricerca, download condiviso, offset sync per utente)
- Sezione **In corso** con ripresa visione
- Interfaccia **italiano / inglese** (`lang/`) con menu lingua in header
- **Modalità TV** per Smart TV / telecomando: layout 10-foot, navigazione a frecce, pannello info al focus, play in fullscreen automatico solo su dispositivi TV (non desktop); admin solo su desktop
- Dashboard admin streaming (banda, sessioni attive)
- Sync selettiva contenuti condivisi dagli amici put.io (Admin → Impostazioni)
- **PWA** installabile su mobile/desktop («Aggiungi a schermata Home» / installazione da browser); cache offline degli asset statici (CSS/JS/icone). I link di autorizzazione TV (`/authorize-device`) sono configurati per aprirsi nell'app installata quando possibile (Android/Chrome); su iOS usare il menu **Autorizza TV** nell'app
- **Cache-busting asset** con versione nel nome del file (es. `app.v1718900000.css`): a ogni modifica di CSS/JS l'URL cambia automaticamente (basato su `filemtime`), così tutti i client — incluse le Smart TV, dove svuotare la cache è scomodo — caricano sempre l'ultima versione senza hard-refresh manuale. Richiede `mod_rewrite` (regola in `.htaccess`)

## Aggiornamenti piattaforma

La versione installata è nel file `VERSION` (root). In **Admin → Aggiornamenti** (`/admin/aggiornamenti`) puoi confrontare la versione locale con l’ultima release su GitHub.

Configura in `config.php` (vedi `config.example.php`):

```php
'updates' => [
    'github_repo' => 'Geimsiello/putmio',
    'github_token' => 'ghp_...', // consigliato: evita il rate limit GitHub (60 req/h per IP)
],
```

Su hosting condiviso (es. OVH) molti siti condividono lo stesso IP verso GitHub: senza token compare spesso **HTTP 403 / rate limit**. Un Personal Access Token (sola lettura sul repo) porta il limite a 5000 richieste/ora. Crealo su GitHub → *Settings → Developer settings → Personal access tokens* (scope `public_repo` o token fine-grained read-only).

L’updater aggiorna **solo il core** (codice, template, asset): `config.php`, `storage/` e i dati nel database non vengono toccati. Prima dell’aggiornamento viene creato un backup ZIP in `storage/backups/`. Le migrazioni schema necessarie partono automaticamente via `Migrator` alla prima richiesta dopo l’aggiornamento.

## Struttura

```
putmio/
  front.php          # Front controller (entry point)
  index.php          # Alias verso front.php
  sw.js              # Service worker PWA (cache asset statici)
  cron-sync.php      # Sync automatica da cron (solo CLI)
  config.php         # Generato dal wizard (non committare)
  composer.json      # Dipendenze PHP
  vendor/            # Dipendenze installate con Composer
  src/               # Codice applicazione
  src/Update/        # Updater core (manifest, GitHub Releases)
  templates/         # Viste
  lang/              # Traduzioni interfaccia (it, en)
  public/assets/     # CSS/JS
  storage/           # Log, poster, lock installazione
  sql/schema.sql     # Schema DB
```

## Sicurezza

- `config.php` e `storage/.installed` non vanno nel repository
- `noindex` e `robots.txt` per limitare l’indicizzazione
- Token put.io cifrati in database
- CSRF su tutti i form POST

## Reinstallazione

Elimina `config.php` e `storage/.installed`, poi ricarica l’URL dell’app per ripetere il wizard.

## Roadmap

- **Multi-tenant** (pianificato): vedi [docs/MULTI_TENANT.md](docs/MULTI_TENANT.md)
