# PutMio

Media center personale su [put.io](https://put.io) — catalogo stile Plex, streaming via proxy, multi-utente famiglia.

## Requisiti

- PHP 7.4+ (consigliato 8.x) con estensioni: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`
- MySQL 5.7+ / MariaDB
- Apache con `mod_rewrite` e HTTPS
- [Composer](https://getcomposer.org/) per PHPMailer (cartella `vendor/`)
- Account put.io + (opzionale) API key [TMDB](https://www.themoviedb.org/settings/api)

## Installazione (solo SFTP, senza SSH)

1. Crea un **database MySQL vuoto** dal pannello OVH (annota host, nome, utente, password).
2. Carica l’intera cartella `putmio/` in `/www/putmio/` via FileZilla/SFTP (inclusi `.htaccess`, `vendor/` e, opzionale, `.ovhconfig`).
3. Verifica che `storage/` sia scrivibile (permessi 755 dalla GUI FTP se necessario).
4. Se vedi errore 500, apri prima `https://tuodominio.it/putmio/check.php` per la diagnostica (PHP, estensioni, permessi).
5. Apri `https://tuodominio.it/putmio/` — wizard automatico (come WordPress):
   - Requisiti di sistema
   - **Database**: inserisci i dati del DB già creato + prefisso tabelle (default `pm_`)
   - PutMio crea **solo le tabelle** nel database
   - Account amministratore (+ SMTP opzionale)
5. Accedi e vai in **Admin → Impostazioni**:
   - Inserisci `client_id` e `client_secret` da [put.io OAuth Apps](https://app.put.io/settings/account/oauth/apps)
   - Redirect URI da registrare: `https://tuodominio.it/putmio/admin/oauth/putio/callback`
   - Clic **Collega account put.io**
   - (Opzionale) Configura **SMTP** per gli inviti famiglia via email
   - (Opzionale) Configura **OpenSubtitles** in Impostazioni (API key + account) per i sottotitoli
   - **Sincronizza ora**
6. Classifica i titoli in **Admin → Classificazione**: manualmente oppure con **Scansione TMDB** per proporre associazioni automatiche da confermare con checkbox.

### Database (come WordPress)

PutMio **non crea il database**. Devi crearlo dal pannello OVH, poi nel wizard inserisci host, nome, utente e password. PutMio installerà solo le tabelle (prefisso predefinito `pm_`, personalizzabile).

## Sync automatico (cron OVH)

Dal pannello hosting → Cron, chiama questa URL ogni 6–12 ore:

```
https://tuodominio.it/putmio/cron/sync?token=IL_TUO_CRON_TOKEN
```

Il token è visibile in **Admin → Impostazioni** (generato in installazione).

In alternativa usa **Sincronizza ora** manualmente.

La sync aggiorna il catalogo da put.io e **rimuove** i titoli eliminati sul cloud (inclusi progressi visione e metadati collegati).

## Funzionalità

- Wizard installazione su `/putmio/` (senza URL `/install` dedicato)
- Login, inviti famiglia via email (SMTP + PHPMailer), reset password (SMTP), «Ricordami» (sessione persistente 30 giorni)
- Catalogo film / serie / animazione con classificazione manuale
- Episodi TV raggruppati automaticamente per serie (pattern `S01E03` nel nome file)
- TMDB on-demand (admin)
- Player **Video.js** con proxy streaming Range HTTP e **sottotitoli OpenSubtitles** (ricerca, download condiviso, offset sync per utente)
- Sezione **In corso** con ripresa visione
- Tema light / dark per utente
- Dashboard admin streaming (banda, sessioni attive)
- Sync selettiva contenuti condivisi dagli amici put.io (Admin → Impostazioni)

## Struttura

```
putmio/
  front.php          # Front controller (entry point reale)
  index.php          # Alias verso front.php (compatibilità)
  config.php         # Generato dal wizard (non committare)
  composer.json      # Dipendenze PHP (PHPMailer)
  vendor/            # Dipendenze installate con composer install
  src/               # PHP applicazione
  templates/         # Viste
  public/assets/     # CSS/JS
  storage/           # Log, poster, backdrop player, lock installazione
  sql/schema.sql     # Schema DB
```

## Sicurezza

- `config.php` e `storage/.installed` non vanno nel repository
- App nascosta: `noindex` + `Disallow: /putmio/` in robots.txt
- Token put.io cifrati in database
- CSRF su tutti i form POST

## Reinstallazione

Via FTP elimina `config.php` e `storage/.installed`, poi ricarica `/putmio/` per ripetere il wizard.

## Risoluzione problemi

| Problema | Soluzione |
|----------|-----------|
| Errore 500 all’apertura | Apri `/putmio/check.php` e `/putmio/probe.php`; controlla permessi (cartelle 755, file 644) e `storage/logs/shutdown.log` |
| Pagina bianca | Controlla PHP 7.4+ e log in `storage/logs/app.log` |
| Wizard non parte | Verifica che `config.php` non esista già |
| Video non riproduce | Controlla collegamento put.io; usa la sorgente **MP4 put.io** nel player se disponibile |
| Video si interrompe a metà | Di default PutMio reindirizza al CDN put.io (`stream_via_redirect` in `config.php`). Se usi il proxy PHP, l’hosting può troncare connessioni lunghe — imposta `stream_via_redirect` a `true` |
| Video senza audio / barra a 0:00 | Seleziona **MP4 put.io** nel player; per MKV/AC3 apri il file su put.io per generare la conversione |
| Errore 429 sullo stream | Sessioni stream bloccate: attendi 10 minuti o svuota `stream_sessions` (active=0); in `config.php` puoi alzare `max_concurrent_streams_per_ip` |
| Sync fallisce | Token scaduto → ricollega put.io in Impostazioni |
| Sottotitoli non disponibili | Configura OpenSubtitles in Admin → Impostazioni (API key, username, password) |
| Invito email non parte | Verifica SMTP in Admin → Impostazioni; controlla `storage/logs/app.log` |

### Dipendenze PHP

Dalla cartella del progetto:

```bash
composer install --no-dev
```

Carica anche la cartella `vendor/` sul server via SFTP.

## Roadmap

- **Multi-tenant** (pianificato, non ancora implementato): vedi [docs/MULTI_TENANT.md](docs/MULTI_TENANT.md) per il piano completo di conversione.

## Licenza

Uso personale — progetto privato Renato Armenio.
