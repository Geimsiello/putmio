# PutMio

Media center personale su [put.io](https://put.io) — catalogo stile Plex, streaming via proxy, multi-utente famiglia.

## Requisiti

- PHP 7.4+ (consigliato 8.x) con estensioni: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`
- MySQL 5.7+ / MariaDB
- Apache con `mod_rewrite` e HTTPS
- Account put.io + (opzionale) API key [TMDB](https://www.themoviedb.org/settings/api)

## Installazione (solo SFTP, senza SSH)

1. Crea un **database MySQL vuoto** dal pannello OVH (annota host, nome, utente, password).
2. Carica l’intera cartella `putmio/` in `/www/putmio/` via FileZilla/SFTP (inclusi `.htaccess` e, opzionale, `.ovhconfig`).
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
   - **Sincronizza ora**
6. Classifica i titoli in **Admin → Classificazione** e opzionalmente collega metadati TMDB dalla scheda titolo.

### Database (come WordPress)

PutMio **non crea il database**. Devi crearlo dal pannello OVH, poi nel wizard inserisci host, nome, utente e password. PutMio installerà solo le tabelle (prefisso predefinito `pm_`, personalizzabile).

## Sync automatico (cron OVH)

Dal pannello hosting → Cron, chiama questa URL ogni 6–12 ore:

```
https://tuodominio.it/putmio/cron/sync?token=IL_TUO_CRON_TOKEN
```

Il token è visibile in **Admin → Impostazioni** (generato in installazione).

In alternativa usa **Sincronizza ora** manualmente.

## Funzionalità

- Wizard installazione su `/putmio/` (senza URL `/install` dedicato)
- Login, inviti famiglia, reset password (SMTP)
- Catalogo film / serie / animazione con classificazione manuale
- TMDB on-demand (admin)
- Player **Video.js** con proxy streaming Range HTTP
- Sezione **In corso** con ripresa visione
- Tema light / dark per utente
- Dashboard admin streaming (banda, sessioni attive)

## Struttura

```
putmio/
  front.php          # Front controller (entry point reale)
  index.php          # Alias verso front.php (compatibilità)
  config.php         # Generato dal wizard (non committare)
  src/               # PHP applicazione
  templates/         # Viste
  public/assets/     # CSS/JS
  storage/           # Log, poster, lock installazione
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
| Video non riproduce | Controlla collegamento put.io; codec non supportato → conversione MP4 su put.io |
| Sync fallisce | Token scaduto → ricollega put.io in Impostazioni |

## Licenza

Uso personale — progetto privato Renato Armenio.
