# put.io — HLS, audio e sottotitoli (note implementazione)

Documento di riferimento per funzionalità put.io non ancora integrate in PutMio o da estendere in futuro.

## Audio multi-lingua (implementato)

PutMio usa **HLS put.io** come sorgente predefinita:

- Endpoint PutMio: `GET /stream?id={putio_file_id}&format=hls`
- PutMio recupera il manifest da put.io: `GET /v2/files/{id}/hls/media.m3u8?subtitle_key=all`
- Il manifest contiene le rendition audio in `#EXT-X-MEDIA:TYPE=AUDIO`
- Video.js (VHS) espone le tracce via `player.audioTracks()` — menu **Audio** nel player

Fallback manuali nel selettore sorgente:

| `format` | put.io | Uso |
|---|---|---|
| `hls` | `/hls/media.m3u8` | Multi audio (consigliato) |
| `mp4` | `/mp4/stream` | Singola traccia AAC stereo |
| `original` | `/url` | File MKV/MP4 originale (codec spesso non supportati dal browser) |

## Sottotitoli put.io (da implementare)

put.io espone sottotitoli separati dall’audio, simile a un catalogo per file.

### Elenco sottotitoli

```
GET https://api.put.io/v2/files/{id}/subtitles
Authorization: Bearer {access_token}
```

Risposta (esempio):

```json
{
  "subtitles": [
    {
      "key": "V7mVadfvq34erarjy9tqj0435hgare",
      "language": "Italian",
      "name": "film-ita.srt",
      "source": "mkv"
    }
  ]
}
```

**Sorgenti (`source`):**

| Valore | Significato |
|---|---|
| `mkv` | Estratto dal contenitore MKV |
| `folder` | File `.srt`/`.vtt` nella stessa cartella su put.io |
| `opensubtitles` / `opensubtitles_rest` | Scaricato da OpenSubtitles |

### Download singolo sottotitolo

```
GET https://api.put.io/v2/files/{id}/subtitles/{key}?format=webvtt
GET https://api.put.io/v2/files/{id}/subtitles/{key}?format=srt
```

### Sottotitoli nel manifest HLS

Il parametro `subtitle_key` su `/hls/media.m3u8` controlla i sottotitoli inclusi nel playlist:

```
GET /v2/files/{id}/hls/media.m3u8?subtitle_key=all
```

- `all` — sottotitoli per le lingue preferite dell’utente put.io
- `{key}` — un sottotitolo specifico dall’elenco `/subtitles`

Il manifest può includere tracce `#EXT-X-MEDIA:TYPE=SUBTITLES` con URI verso segmenti WebVTT.

### Integrazione PutMio (proposta)

1. **Opzione A — HLS nativo:** passare `subtitle_key` al manifest e abilitare i sottotitoli nel player Video.js (`addRemoteTextTrack` o API VHS `subtitleTracks`).
2. **Opzione B — Ibrido:** mantenere OpenSubtitles per ricerca/download locale + offrire anche i sottotitoli put.io (`source=mkv`) come tracce aggiuntive senza duplicare quelli già in DB.
3. **Preferenze utente:** mappare `language` put.io sulle etichette UI; gestire offset sync come per i sottotitoli OpenSubtitles attuali.

### Codice esistente utile

- `PutMio\PutIO\Client::getHlsManifest(int $fileId, string $subtitleKey = 'all')` — già usato per l’audio; il parametro `subtitle_key` è pronto per i subs.
- `PutMio\Stream\StreamProxy::streamHls()` — serve il manifest al player autenticato.

### Differenza rispetto a OpenSubtitles

| | OpenSubtitles (PutMio oggi) | put.io subtitles |
|---|---|---|
| Ricerca | Per TMDB/IMDB | Automatica su put.io + impostazioni lingua utente |
| Storage | `storage/subtitles/` locale | Su put.io, scaricabili via API |
| Player | URL `/subtitles/serve?id=` | VTT da API o embedded in HLS |
| Condivisione | Tra utenti PutMio | Già sul file put.io |

## media_info (originale)

```
GET /v2/files/{id}?media_info=true&codecs=true
```

Le tracce dell’MKV originale sono in `media_info.streams` (non `tracks`). Utile per diagnostica, non necessario per la riproduzione HLS.

## Riferimenti

- [put.io API v2](https://api.put.io/v2/docs/gettingstarted.html) (se disponibile)
- Gist OpenAPI community: `Put.io 2.7.0 OAS 3.0`
- Video.js HTTP Streaming: [multiple audio tracks](https://github.com/videojs/http-streaming/blob/main/docs/multiple-alternative-audio-tracks.md)
