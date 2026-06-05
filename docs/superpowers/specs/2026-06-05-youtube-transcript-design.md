# YouTube-transcript als extra bron — ontwerp

_Datum: 5 juni 2026. Status: ter review. Feature die in fase 1 bewust buiten scope stond (startspec §1), nu opgepakt._

## 1. Doel

Per raadsvergadering de bijbehorende YouTube-uitzending vinden, het transcript ophalen, en dat transcript gebruiken als **extra bron** voor de vergadering-samenvatting — zodat de samenvatting niet alleen weergeeft wat er vóórlag (PDF's) en formeel besloten is (besluitenlijst), maar ook **het debat**: wat raadsleden zeiden, argumenten, moties, inspraak.

**Kernbesef (afbakening van waarde):** de formele besluiten zitten al in de besluitenlijst-PDF die ORI levert. Het transcript voegt uitsluitend de *deliberatie/het debat* toe. Het is dus een verrijking, geen voorwaarde voor een correcte samenvatting.

## 2. Vastgelegde beslissingen (brainstorm 5 juni 2026)

1. **Integratie op vergadering-niveau.** Transcript + besluitenlijst + agenda-tekst → de bestaande `GenerateMeetingSummary`. Per-agendapunt-samenvattingen blijven op de PDF's (geen transcript-splitsing per agendapunt).
2. **Transcript via een betaalde transcript-API** (default: Supadata of vergelijkbaar) achter een `TranscriptProvider`-interface. De API levert captions én een AI-fallback wanneer er geen captions zijn. **Geen** `yt-dlp`/`ffmpeg`/eigen Whisper-chunking. Interface houdt de deur open voor eigen Whisper later.
3. **Hybride matching.** Deterministische code haalt kandidaat-video's op (kanaal + datumvenster); een `laravel/ai`-agent kiest de beste match + confidence + reden. Hoge confidence → automatisch koppelen; laag/meerdere kandidaten → bevestigen in de admin review-queue (de bestaande harde review-gate).
4. **Post-meeting timing.** De video (en de besluitenlijst) verschijnen ná de vergadering. Een dagelijkse job zoekt, voor council-meetings waarvan `start_date` voorbij is en die nog geen transcript hebben, de video op en retryt N dagen.
5. **Re-summarize.** Zodra het transcript binnen is verandert de bron → nieuwe `source_hash` → `GenerateMeetingSummary` draait opnieuw met `[besluitenlijst + agenda-tekst + transcript]`; de oude meeting-draft wordt vervangen, de reviewer ziet de verrijkte versie.

## 3. Componenten (elk één verantwoordelijkheid, Action-based)

- `YouTubeClient` (infrastructuur, `app/Services/YouTube/`) — zoekt video's op een kanaal binnen een datumvenster. Backend: YouTube Data API v3 of de zoek-endpoint van de transcript-vendor. Levert kandidaat-DTO's (videoId, titel, publishedAt, duur).
- `TranscriptProvider` (interface, `app/Services/Transcript/`) — `fetch(string $youtubeVideoId, string $language = 'nl'): TranscriptResult` (`text`, `source` = `captions|ai`, `segments?`). Default-implementatie: `SupadataTranscriptProvider`. Achter de interface zodat een toekomstige `WhisperTranscriptProvider` plugbaar is.
- `app/Actions/Videos/FindMeetingVideo` — `handle(Meeting $meeting): ?MeetingVideo`. Haalt kandidaten op via `YouTubeClient`, roept `MatchMeetingVideo` (agent) aan, schrijft/update `MeetingVideo` met status.
- `app/Ai/Agents/VideoMatchAgent` (laravel/ai, structured output) — krijgt de meeting (naam, datum, type) + kandidatenlijst, retourneert `{video_id, confidence (0-100), reason}`. Kiest, zoekt niet zelf.
- `app/Actions/Videos/FetchMeetingTranscript` — `handle(MeetingVideo $video): void`. Roept `TranscriptProvider::fetch`, slaat transcript op, zet status, triggert re-summarize.
- `app/Jobs/MatchMeetingVideosJob` (scheduled, dagelijks) — itereert in aanmerking komende meetings, dispatcht per meeting `FindMeetingVideo`; bij bevestigde match → `FetchMeetingTranscript`.
- Uitbreiding `GenerateMeetingSummary` — neemt het transcript (indien aanwezig) mee in de input en in de `source_hash`.

## 4. Dataflow

```
MatchMeetingVideosJob (dagelijks)
  └─ per council-meeting (start_date voorbij, geen confirmed transcript, < N dagen oud):
       FindMeetingVideo
         ├─ YouTubeClient.searchChannel(youtube_channel_id, start_date ± venster)
         ├─ VideoMatchAgent.pick(meeting, kandidaten) → {video_id, confidence, reason}
         ├─ confidence ≥ drempel → MeetingVideo.status = matched (auto)
         └─ anders → status = needs_confirmation (verschijnt in review-queue)
       [na (auto- of mens-)bevestiging]
       FetchMeetingTranscript
         ├─ TranscriptProvider.fetch(video_id, 'nl') → text + source
         ├─ MeetingVideo.transcript_text/source/status = transcribed
         └─ dispatch SummarizeMeetingJob (× beide niveaus) — re-summarize met transcript
```

## 5. Datamodel

Nieuwe tabel `meeting_videos` (1-op-1 met `meetings`):
- `id`, `meeting_id` (FK, unique, cascade)
- `youtube_video_id` nullable, `video_url` nullable
- `match_confidence` unsignedTinyInteger nullable, `match_reason` text nullable
- `candidates` json nullable (de opgehaalde kandidaten, voor audit/handmatige keuze)
- `confirmed_at` datetime nullable (auto of door reviewer)
- `transcript_text` longtext nullable, `transcript_source` string nullable (`captions`|`ai`)
- `transcript_fetched_at` datetime nullable
- `status` string (`pending`|`needs_confirmation`|`matched`|`transcribed`|`not_found`|`failed`)
- `attempts` unsignedInteger default 0, `last_attempt_at` datetime nullable
- timestamps

Config:
- `municipalities.settings.youtube_channel_id` (per gemeente; Brummen = RTV794/VoorstVeluwezoom-kanaal).
- `config/volgjeraad.php`: `youtube.search_window_days`, `youtube.match_confidence_threshold`, `youtube.max_find_days` (stop met zoeken na N dagen), transcript-vendor-config.
- `.env`: `YOUTUBE_API_KEY` (indien Data API voor zoeken), `SUPADATA_API_KEY` (of vendor-equivalent).

## 6. Foutafhandeling

- **Video niet gevonden** binnen het venster → status `not_found`/blijf retryen tot `max_find_days`, daarna opgeven (meeting-samenvatting blijft op PDF's; transcript is optioneel).
- **Verkeerde match** → ondervangen door de confidence-drempel + menselijke bevestiging bij twijfel.
- **Transcript-API faalt** → status `failed` + gelogd; retry met backoff; blokkeert nooit de PDF-gebaseerde samenvatting.
- **Lege/onbruikbare transcript** → flag; meeting-samenvatting valt terug op PDF-bronnen.

## 7. Testaanpak (Pest)

- `YouTubeClient` — HTTP-fake; assert juiste channel/datum-query, kandidaat-parsing.
- `SupadataTranscriptProvider` — HTTP-fake; captions-pad + AI-fallback-pad; taalparameter.
- `FindMeetingVideo` — agent-fake (`VideoMatchAgent::fake`): hoge confidence → auto-matched; lage → needs_confirmation; meerdere kandidaten opgeslagen.
- `FetchMeetingTranscript` — provider-fake; transcript opgeslagen + re-summarize gedispatcht (Bus::fake).
- `GenerateMeetingSummary` — met transcript in de input verandert `source_hash` en regenereert.
- `MatchMeetingVideosJob` — selecteert alleen in aanmerking komende meetings; respecteert `max_find_days`.

## 8. Afhankelijkheden

- Transcript-API-account + key (Supadata of equivalent). **Te verifiëren tijdens implementatie:** Nederlandse transcript-kwaliteit, prijs, of dezelfde vendor ook kanaal-zoeken dekt (anders YouTube Data API v3 voor het zoeken, gratis quota).
- `laravel/ai` (al aanwezig) voor `VideoMatchAgent`.
- **Geen** binaries (`yt-dlp`/`ffmpeg`) zolang we de transcript-API gebruiken.

## 9. Non-goals (nu niet)

- Geen per-agendapunt-splitsing van het transcript, geen fragmenten knippen.
- Geen sprekersherkenning/diarisatie.
- Geen eigen Whisper-pijplijn (wel voorbereid via de interface).
- Geen video-embeds/clips op de site.
- Niet voor commissie/college (alleen raad, conform fase 1).

## 10. Te verifiëren tijdens implementatie

- Brummen-kanaal-id van RTV794/VoorstVeluwezoom + of hun titels betrouwbaar "raadsvergadering" + datum bevatten.
- Bestaan er auto-captions op die livestreams, of moet de AI-fallback het altijd doen (kwaliteit + kosten-impact).
- Exacte transcript-vendor + endpoint-contract.
