---
name: PutMio Cinematic
colors:
  surface: '#0b1326'
  surface-dim: '#0b1326'
  surface-bright: '#31394d'
  surface-container-lowest: '#060e20'
  surface-container-low: '#131b2e'
  surface-container: '#171f33'
  surface-container-high: '#222a3d'
  surface-container-highest: '#2d3449'
  on-surface: '#dae2fd'
  on-surface-variant: '#c7c4d7'
  inverse-surface: '#dae2fd'
  inverse-on-surface: '#283044'
  outline: '#908fa0'
  outline-variant: '#464554'
  surface-tint: '#c0c1ff'
  primary: '#c0c1ff'
  on-primary: '#1000a9'
  primary-container: '#8083ff'
  on-primary-container: '#0d0096'
  inverse-primary: '#494bd6'
  secondary: '#ffe083'
  on-secondary: '#3c2f00'
  secondary-container: '#eec200'
  on-secondary-container: '#645000'
  tertiary: '#ffb783'
  on-tertiary: '#4f2500'
  tertiary-container: '#d97721'
  on-tertiary-container: '#452000'
  error: '#ef4444'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#e1e0ff'
  primary-fixed-dim: '#c0c1ff'
  on-primary-fixed: '#07006c'
  on-primary-fixed-variant: '#2f2ebe'
  secondary-fixed: '#ffe083'
  secondary-fixed-dim: '#eec200'
  on-secondary-fixed: '#231b00'
  on-secondary-fixed-variant: '#574500'
  tertiary-fixed: '#ffdcc5'
  tertiary-fixed-dim: '#ffb783'
  on-tertiary-fixed: '#301400'
  on-tertiary-fixed-variant: '#703700'
  background: '#0b1326'
  on-background: '#dae2fd'
  surface-variant: '#2d3449'
  success: '#10b981'
  warning: '#f59e0b'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '800'
    lineHeight: 40px
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  margin-desktop: 2.5rem
  margin-mobile: 1rem
  gutter: 1.5rem
  container-max: 1440px
  section-gap: 3rem
---

## Brand & Style
The brand identity is "PutMio Cinematic," a premium personal media center aesthetic that balances high-end entertainment vibes with functional, developer-friendly clarity. It is built on a **Glassmorphic** and **Modern Corporate** hybrid style.

The interface evokes a sense of deep immersion through the use of dark backgrounds and vibrant, glowing accents. It targets tech-savvy media enthusiasts who value both visual flair (shimmer effects, hover scales) and information density. The emotional response is one of "organized discovery"—clean, structured, yet visually rich.

## Colors
The palette is rooted in a "Deep Space" navy (`#0b1326`), providing a high-contrast foundation for content. 

- **Primary:** An Indigo-based spectrum used for interactive states and brand identity.
- **Surface Strategy:** Uses a tiered approach of increasingly lighter navy shades to define hierarchy without relying solely on borders.
- **Semantic Colors:** Bright, high-saturation red, amber, and emerald are used for status feedback, often paired with low-opacity background tints (10-20%) to create "soft alerts."
- **Glass Effects:** Background blurs (12px to 20px) are applied to fixed headers and modals to maintain context while focusing attention.

## Typography
The system uses a duo-font approach. **Hanken Grotesk** serves as the workhorse for display and body text, offering a sharp, contemporary feel with high legibility. **JetBrains Mono** is used selectively for labels, metadata, and navigational items to inject a "technical" or "data-centric" personality.

- **Headlines:** Use heavy weights (700-800) to anchor sections.
- **Labels:** Always utilize the monospaced font, often in uppercase or with increased letter spacing to distinguish them from reading text.
- **Contrast:** High contrast between `on-surface` (white/blue-tinted) and `on-surface-variant` (muted lilac-grey) is essential for information hierarchy.

## Layout & Spacing
The system follows a **Fixed-Fluid Hybrid** grid. On desktop, content is constrained to a 1440px max-width container with generous 40px (2.5rem) side margins. On mobile, margins reduce to 16px (1rem).

- **Fixed header offset:** The app header is `h-16` (4rem) and fixed. Authenticated pages use `pt-24` (6rem) on `<main>` — header clearance plus 2rem of breathing room before the first line (back link, h1, breadcrumb). Do not add extra top padding on individual pages; use `pb-*` only for bottom spacing inside admin shells.
- **Vertical Rhythm:** Sections are separated by large gaps (3rem/48px) to allow the "glass" panels to breathe.
- **Component Spacing:** Inside cards and sections, a consistent 1.5rem gutter is used. 
- **Aspect Ratios:** Poster cards strictly adhere to a 2:3 ratio, ensuring consistency in media catalogs.

## Elevation & Depth
Depth is expressed through **Tonal Layering** and **Glassmorphism**, rather than traditional heavy shadows.

- **Level 0 (Background):** `#0b1326` - The base canvas.
- **Level 1 (Sections/Cards):** `#171f33` - Surface containers with subtle 1px borders (`outline-variant` at 20-30% opacity).
- **Level 2 (Active/Hover):** `#2d3449` - Surface-variant highlights.
- **Overlays:** Modals and headers use `backdrop-blur-xl` (24px) combined with an 80% opacity surface color to create a sense of floating over the content.
- **Interactive Depth:** Poster cards use a 1.05x scale transform on hover accompanied by a diffused 25px shadow to "lift" them off the grid.

## Shapes
The shape language is consistently **Rounded**, leaning towards a soft-industrial aesthetic.

- **Primary Radius:** 0.5rem (8px) for standard inputs and buttons.
- **Large Radius:** 0.75rem (12px) to 1rem (16px) for cards, section containers, and modals.
- **Pill Shapes:** Used for badges (tags) and secondary controls (e.g. locale menu) to provide visual variety.

## Components
- **Buttons:**
  - *Primary:* Solid `primary-container` background with high-contrast text. Features a 1.05x scale on hover and 0.95x on active click.
  - *Secondary:* Outlined with `outline` color, subtle hover background fill.
- **Inputs:** Dark backgrounds (`surface`) with `outline-variant` borders. Focus state uses a `primary` ring with 0px border-gap.
- **Poster Cards:** The signature component. 2:3 ratio, overflow hidden, featuring a bottom-aligned gradient overlay (from black to transparent) that reveals metadata on hover. Contenuti condivisi: badge proprietario centrato in basso (`poster-owner-badge`, nick monospaced primary); su card con barra avanzamento (`poster-card--with-progress`) il badge sale sopra la barra.
- **Badges:** Pill-shaped, small padding (px-3 py-1), using the `label-md` monospaced font.
- **Skeleton Loaders:** Uses a `shimmer` animation from left to right, utilizing a white/5% gradient over a `surface-variant` background.
- **Switches:** iOS-style toggle with a clear `primary-container` active state and a smooth sliding animation.
- **Transactional emails:** HTML table-based layout with inline CSS (email client safe). Dark palette aligned with the site (`#0b1326` background, `#171f33` card, `#c0c1ff` / `#8083ff` accents). Used for family invite links.
- **Amici put.io (impostazioni):** riga selezionata con bordo sinistro `primary`, sfondo `primary/12`, salvataggio AJAX al toggle checkbox, feedback tramite toast in basso a destra.
- **TMDB Link Modal:** Glass overlay (`#161616/80` + `backdrop-blur-md`), max-width 800px. Header con titolo e sottotitolo monospaced (nome file originale). Colonna sinistra: ricerca, pulsante «Vedi contenuto» (solo in modalità catalogo, sotto Cerca, stile secondary outlined) + lista risultati con mini-poster, titolo, titolo originale, anno, badge tipo, voto e synopsis troncata (`line-clamp-2`). Colonna destra: anteprima dettagliata con poster grande, generi, overview troncata a 3 righe con link «carica altro» / «mostra meno», regista/creatore; footer Annulla/Applica fisso sotto l'area scrollabile. Mostrata solo per contenuti senza `tmdb_id`.
- **Classificazione TMDB (admin):** Card compatta con conteggio contenuti da classificare (`surface-container-high`, icona `folder_open`, numerica 32px). Sezione scansione con pulsante «Scansione TMDB» (senza lista interna). All'avvio scansione compare una sezione separata sotto («Risultati scansione») con progresso `current/total`; ogni file ha checkbox (deselezionata di default, pre-selezionata solo con affidabilità 100%), nome file monospaced, badge condiviso, anno nel file se rilevato, ricerca usata. Fino a 8 candidati TMDB selezionabili con radio: titolo, anno tra parentesi, tipo, affidabilità %, voto, titolo originale, synopsis troncata, mini-poster `w92`. Azioni Seleziona tutti, Deseleziona, Salva selezionati. Card risultati `surface-container`, righe `surface-container-high`; la lista usa lo scroll nativo della pagina (nessuna altezza fissa sul container).
- **Serie TV:** Nel catalogo compare una sola scheda per serie; gli episodi (file con `SxxExx` nel nome) sono raggruppati sotto la scheda con lista per stagione. Card episodio: numero in badge primary, titolo, stato visione, icona play.
- **Serie TV — dettaglio episodi:** Tab orizzontali per stagione con underline `primary-container`; badge pill «X/Y episodi visti» a destra. Card episodio (`pm-episode-card`): check verde se completato, altrimenti codice `E01`; thumbnail 16:9 con barra avanzamento in basso se in corso; meta (numero + badge «IN CORSO»), titolo bold, synopsis `line-clamp-2`; durata corta (`48m`) e percentuale a destra. Bordo `primary` per episodio attivo. Lista a tutta larghezza sotto header serie.
- **Home sliders:** Righe orizzontali con scroll snap (`pm-slider`): «Continua a guardare» e «Aggiunti di recente» usano card poster 2:3 (`w-36`/`w-44`); sotto compaiono righe per genere (titolo = nome genere, slider contenuti classificati) solo se il genere ha almeno un titolo, in ordine alfabetico, con link «Vedi tutto» al catalogo filtrato. Track senza scrollbar visibile, scroll touch (`pan-x`) e drag col mouse su desktop; la rotella scrolla solo la pagina (non gli slider); frecce prev/next (`pm-slider__nav`).
- **Media Detail (linked):** Layout a due colonne con link «Torna al Catalogo», poster grande, generi pill, griglia metadati (Durata/Anno/Tipo; per i film anche Qualità da nome file, es. 1080p/720p/4K) e azioni Riprendi/Riproduci, Segna come visto, Ricomincia dall'inizio. Per le serie: pulsante primary «Riprendi S02E03» / «Riproduci S01E01» (ultimo episodio in corso o primo non completato) sopra la lista episodi.
- **Login:** Shell centrata con blob atmosferici (solo dark). Sopra la card: logo `favicon.svg` + titolo PutMio + tagline (`body-md`). Card `auth-glass` con tab «Email» / «QR / TV»; tab email: titolo sezione, barra accent primary, input con icona mail/lock (`auth-input`), riga «Ricordami» (`auth-checkbox`) + link password dimenticata, pulsante `auth-btn-primary`. Tab dispositivo: QR 200×200 (`#dae2fd` su `#171f33`), codice monospaced grande (`primary`), spinner attesa, pulsante `auth-btn-secondary` rigenera codice. Pagina `/authorize-device` (utente loggato): conferma richiesta con icona `devices`, label tipo dispositivo, IP, codice; CTA `auth-btn-primary` Autorizza + `auth-btn-secondary` Annulla. Autocomplete `username` / `current-password` per password manager. Con «Ricordami» attivo: cookie persistente 30 giorni (`putmio_remember`) con token ruotato a ogni accesso.
- **Header app:** Menu lingua in alto a destra (`pm-locale-menu`): trigger pill con icona `language`, codice locale (`IT`/`EN`) e chevron; pannello glass con opzioni native e check sulla lingua attiva. Posizionato prima del badge utente. Stessa UI sulla shell login (angolo alto dx). **Mobile (< md):** burger in header; drawer full-screen (`bg-background`, stesso body) con pulsante `close` in alto a destra, link navigazione con icona Material, footer con lingua in dropdown e voce esci in listing (icona `logout` + nome utente + «Esci»).
- **Player:** Usa l'header app condiviso (glass + nav). Contenuto max-width 1200px: video Video.js in modalità fluid con aspect ratio dinamico dai metadati (placeholder 16:9), artwork player con **backdrop TMDB** (`backdrop_local_path` / `backdrop_url`, w1280) e fallback poster; sfondo cinematico sfocato (`.putmio-player-poster-bg`) + `poster` nativo Video.js, nascosto in riproduzione. Per episodi fallback backdrop/poster della serie. Bordo `outline-variant/30`, selettore sorgente MP4/originale, traccia audio (se disponibili) e **sottotitoli** via menu CC nativo Video.js (tutte le tracce scaricate), pulsante «Gestisci sottotitoli» + hint testuale, pannello sync ±0.1s/±0.5s (offset server-side sul VTT) visibile quando una traccia è attiva. Aspetto sottotitoli via modale nativa Video.js (CC → impostazioni): default font 100%, testo bianco, sfondo `#060e20`, bordo uniforme; preferenze salvate in localStorage utente. Modale stilizzata PutMio (`surface-container-high` / `#222a3d` sui select). Posizione cue ancorata in basso sopra control bar. Modale sottotitoli glass max-width 800px: sezione «Già disponibili» (card `surface-container-high`, Usa/Elimina admin) + «Cerca su OpenSubtitles» con risultati (lingua, release, download count). Nei risultati ricerca: «Scarica» attivo, «Scaricamento…» (disabled + spinner) in corso, «Già scaricato» (disabled, bordo/icona success) se presente in cache o appena scaricato; toast di conferma al termine. Footer attribuzione OpenSubtitles. Nota «Streaming da put.io», card info con titolo (serie per episodi), sottotitolo monospaced primary, durata/anno, synopsis, azioni Riprendi/Riproduci · Segna come visto · Ricomincia · prev/next episodio, footer tecnico da nome file. Sfondo atmosferico con blob primary/tertiary. Controlli Video.js con skip nativi ±10s nella control bar, volume verticale (hover sul mute: pannello `surface-container` arrotondato 12px, track pill) e progress bar `primary-container` (`#8083ff`). Toast `.pm-next-episode` in overlay (ultimi 30 s).
- **OpenSubtitles (impostazioni admin):** Fieldset dopo TMDB con badge Configurato/Non configurato, campi API key / username / password (placeholder `••••` se salvati). Footer card con bordo superiore: hint a sinistra + CTA `pm-btn-primary` «Verifica connessione» (full-width mobile, shadow primary).
- **Media Detail — Sottotitoli:** Card `surface-container-high` con conteggio tracce e pulsante «Gestisci sottotitoli» (film singoli, non serie).
- **PWA:** Manifest dinamico (`/manifest.webmanifest`, `theme_color` / `background_color` `#0b1326`). Icone PNG 192×192 e 512×512 in `public/assets/icons/` (palette favicon: `#c0c1ff` + play `#0d0096`). Service worker `sw.js` in root: cache solo asset in `/public/assets/`; pagine HTML, API e stream restano sempre da rete. `handle_links: preferred`, `launch_handler` (`focus-existing`) e `url_handlers` su `/authorize-device` per aprire i link QR nell'app installata (Android/Chrome). Su iOS il QR apre una pagina intermedia con codice copiato e istruzioni per aprire PutMio dalla Home. `apple-touch-icon` 192px; meta `theme-color`, `apple-mobile-web-app-capable`, `apple-mobile-web-app-title`.
- **TV mode (interfaccia 10-foot):** Attivazione auto su UA Smart TV / Android TV / Apple TV o toggle manuale (icona TV/desktop in header). Classe `html.tv-mode`, header semplificato (`pm-tv-header`: Home, Catalogo, In corso, Esci), navigazione telecomando (`tv-nav.js`, `data-pm-tv-focus`), pannello info al focus (`pm-tv-info-rail`), poster moderatamente più grandi, caption sotto poster nascoste (info nel rail). Play da TV: autostart + fullscreen automatico **solo su dispositivi TV** (`putmio-tv-player-immersive` CSS a schermo intero + tentativo API nativa), non su desktop. Admin/TMDB/OpenSubtitles modale disabilitati in TV mode (`putmio_admin_ui_enabled()`). Dettaglio media: layout hero `pm-catalog-tv-hero`. Home TV: max 2 righe genere oltre a Continua/Recenti.