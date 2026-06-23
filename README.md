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
- **Accesso da TV / altro dispositivo**: tab «QR / TV» nella pagina login con codice e QR; autorizzazione dal telefono già connesso (`/authorize-device`); dopo l’autorizzazione il dispositivo resta associato **30 giorni** (revoca al logout o al cambio password)
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

## Risoluzione problemi

| Problema | Soluzione |
|----------|-----------|
| Errore 500 all’apertura | Apri `check.php` e `probe.php`; controlla permessi (cartelle 755, file 644) e `storage/logs/shutdown.log` |
| Pagina bianca | Controlla PHP 7.4+ e log in `storage/logs/app.log` |
| Wizard non parte | Verifica che `config.php` non esista già |
| Dipendenze mancanti | Esegui `composer install --no-dev` e carica `vendor/` |
| Video non riproduce | Controlla collegamento put.io; usa la sorgente **MP4 put.io** nel player se disponibile |
| Video si interrompe a metà | Di default PutMio reindirizza al CDN put.io (`stream_via_redirect` in `config.php`). Se usi il proxy PHP, l’hosting può troncare connessioni lunghe — imposta `stream_via_redirect` a `true` |
| Video senza audio / barra a 0:00 | Seleziona **MP4 put.io** nel player; per MKV/AC3 apri il file su put.io per generare la conversione |
| Errore 429 sullo stream | Sessioni stream bloccate: attendi 10 minuti o svuota `stream_sessions` (active=0); in `config.php` puoi alzare `max_concurrent_streams_per_ip` |
| Sync fallisce | Token put.io scaduto → ricollega put.io in Impostazioni |
| Sottotitoli non disponibili | Configura OpenSubtitles in Admin → Impostazioni |
| Invito email non parte | Verifica SMTP in Admin → Impostazioni; controlla `storage/logs/app.log` |
| Cron non parte | Verifica percorso script in Impostazioni; controlla log email del provider o `storage/logs/app.log` |

## Roadmap

- **Multi-tenant** (pianificato): vedi [docs/MULTI_TENANT.md](docs/MULTI_TENANT.md)
