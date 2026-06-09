# 15-minuten meeting-resolutie-loop

**Datum:** 2026-06-09
**Status:** Goedgekeurd ontwerp, klaar voor implementatieplan

## Probleem

De huidige pijplijn matcht video's in een **dagelijkse batch** (`volgjeraad:match-videos`,
06:30). Een nieuwe raadsvergadering die net is geweest wacht daardoor tot de volgende
ochtend voordat video/transcript/samenvatting in gang komt. De ingest draait al elke 15
minuten; we willen de rest van de pijplijn op diezelfde cadans laten meelopen, met een
duidelijke bron-eis: een samenvatting wordt alleen gemaakt als er **bronmateriaal** is.

## Doel

Elke 15 minuten:
1. Per gemeente nieuwe/gewijzigde meetings ophalen (bestaand gedrag, blijft).
2. Voor elke "summarizable" meeting die heeft plaatsgevonden de bron-resolutie evalueren.
3. Zodra er een bron is (transcript óf notule) → samenvattingen genereren → meeting komt in
   **review** (Draft-samenvattingen). De review-stap wordt later verwijderd (buiten scope).
4. Geen bron → meeting blijft vastgelegd, maar **zonder samenvatting en zonder mail**.

## Kern: bron-eis

Een samenvatting vereist precies één van twee bronnen:

- **Transcript** — uit een YouTube-video. Alleen voor **raadsvergaderingen** van gemeenten
  met een ingesteld `youtube_channel_id`.
- **Notule** — een notule/besluitenlijst-document in ORI. Geldt voor iedereen (geen kanaal,
  niet-raad, of video mislukt).

Geen van beide → geen samenvatting.

## Beslisboom per meeting

Geëvalueerd door de readiness-sweep, voor elke meeting met `ingest_mode = summarize`,
`summarized_at IS NULL`, `summary_skipped_reason IS NULL` en `now >= starts_at`:

```
isCouncilWithChannel = (type == Council) && municipality.settings.youtube_channel_id != null

# 1) Transcript-pad
if isCouncilWithChannel:
    if now < starts_at + video_wait_hours (24u):
        return                      # video staat nog niet online → wachten
    ensureVideoSearchAttempted()    # FindMeetingVideo / ProcessMeetingVideoJob
    if video.status == Transcribed:
        summary_source = 'transcript'
        summarize(meeting)          # bestaande DispatchMeetingSummariesIfReady-pad
        return
    if video zoekt/transcribeert nog (binnen attempt-budget):
        return                      # transcript afwachten
    # video NotFound, of transcript-budget op → doorvallen naar notule-pad

# 2) Notule-pad
if notule_detected_at != null:
    summary_source = 'notule'
    summarize(meeting)
    return
if mediaComplete(meeting):          # alle agendaItems.attachments_fetched_at gezet
    runNotuleDetectionAgent()       # zet notule_detected_at + notule_media_object_id indien gevonden
    if notule_detected_at != null:
        summary_source = 'notule'
        summarize(meeting)
        return

# 3) Geen bron → begrensde werkdag-rechecks
if now >= starts_at + notule_recheck_working_days (2 werkdagen):
    summary_skipped_reason = 'no_source'   # terminaal; meeting vastgelegd, geen samenvatting, geen mail
    return
# anders: niets doen; volgende tick / werkdag-recheck pakt het op
```

### Beslissingen (vastgelegd met gebruiker)

- **Video op 24u zonder resultaat → direct notule-pad.** Geen extra video-retry-marge.
  Bewuste trade-off: een video die pas ~30u na de vergadering online komt wordt gemist.
- **Notule-detectie via AI-agent**, niet via string-matching. Draait **alleen** wanneer de
  bijlagen compleet/gewijzigd zijn én op de werkdag-rechecks. Resultaat wordt gecachet op de
  meeting (`notule_detected_at`). Max ~3-4 calls per meeting.
- **Werkdagen** = weekend overslaan (`Carbon::addWeekdays`); NL-feestdagen worden voor v1
  als werkdag geteld.
- **Geen bron** → meeting wél vastgelegd, `summary_skipped_reason = 'no_source'`, geen
  nieuwsbrief (bestaande nieuwsbrief-logica dispatcht alleen bij voltooide samenvattingen).

## Her-ingest van de agenda voor onopgeloste meetings

Een notule kan dagen na de vergadering aan ORI worden toegevoegd zonder dat de
meeting-payload zelf wijzigt. De huidige agenda-ingest wordt alleen gedispatcht bij
gewijzigde/nieuwe meetings. Daarom moet de readiness-sweep op de werkdag-rechecks
(werkdag +1 en +2) **de agenda + media opnieuw laten ophalen** voor nog-onopgeloste
meetings, zodat een laat toegevoegde notule binnenkomt voordat de detectie draait.

## NotuleDetectionAgent

- Nieuwe AI-agent in de bestaande `app/Ai/Agents`-structuur (zelfde patroon als
  `VideoMatchAgent`).
- **Input:** lijst van de documenten van de meeting — `name`, `file_name`, en indien
  beschikbaar de upload-/document-datum uit het ORI-`raw_payload`.
- **Output (structured):** `{ is_notule_present: bool, media_object_id: int|null,
  confidence: int }`. Bij `is_notule_present && confidence >= drempel` → opslaan in
  `notule_detected_at` + `notule_media_object_id`.
- Kosten geborgd door de aanroep-gating (compleet/gewijzigd + 2 rechecks) en caching.

## Datamodel-wijzigingen

Migratie op `meetings`:

| Kolom | Type | Doel |
|---|---|---|
| `summary_source` | `string` nullable | `'transcript'` / `'notule'` — waaruit samengevat is |
| `source_resolved_at` | `datetime` nullable | wanneer de bron is vastgesteld |
| `notule_detected_at` | `datetime` nullable | cache: notule gevonden |
| `notule_media_object_id` | `bigint unsigned` nullable FK | welk document de notule is |
| `summary_skipped_reason` | `string` nullable | terminaal, bv. `'no_source'` |

## Config-wijzigingen (`config/volgjeraad.php`)

- `youtube.transcript_wait_days` (7) **vervangen** door `youtube.video_wait_hours` = 24.
- Nieuw: `youtube.notule_recheck_working_days` = 2.
- Bestaande `max_transcript_attempts` / `match_confidence_threshold` blijven.

## Scheduler-wijzigingen (`routes/console.php`)

- `volgjeraad:ingest` — **ongewijzigd**, elke 15 min (meetings + agenda-ingest).
- `volgjeraad:match-videos` (dagelijks 06:30) → **vervangen** door `volgjeraad:resolve`,
  elke 15 min, `withoutOverlapping()`. Draait een nieuwe `ResolveReadyMeetingsJob` die de
  in aanmerking komende meetings selecteert en per meeting `ResolveMeetingSummarySources`
  aanroept.

## Componenten

| Component | Verantwoordelijkheid |
|---|---|
| `ResolveReadyMeetingsJob` | selecteert summarizable, plaatsgevonden, onopgeloste meetings en dispatcht per-meeting verwerking (chunked) |
| `ResolveMeetingSummarySources` (actie) | de beslisboom hierboven voor één meeting; bevat de 24u-gate, video-trigger, notule-detectie-trigger, recheck-/skip-logica |
| `NotuleDetectionAgent` (AI) | bepaalt of er een notule tussen de documenten zit |
| Bestaand `ProcessMeetingVideoJob` / `FindMeetingVideo` / `FetchMeetingTranscript` | hergebruikt voor het transcript-pad |
| Bestaand `DispatchMeetingSummariesIfReady` / `SummarizeMeetingJob` | hergebruikt voor het genereren van samenvattingen; `transcriptResolved()` wordt vervangen/aangevuld door de nieuwe bron-logica |
| Migratie | nieuwe `meetings`-kolommen |
| `MeetingProcessingStatus` (enum) + `Meeting::processingStatus()` | afgeleide status met `adminLabel()` / `publicMessage()` |
| Beheer-lijsten (dashboard, `Municipalities/Show`, review) | statusbadge/-kolom tonen |
| `MunicipalityController` / `MeetingController` + React-pagina's | publieke status-regel + verruimde lijst-query |

`Meeting::transcriptResolved()` en de gate `DispatchMeetingSummariesIfReady` worden herzien
zodat de bron-eis (transcript óf notule) de plaats inneemt van de oude
`transcript_wait_days`-logica. De media-compleet-conditie blijft.

## Status & UI

De verwerkingsstatus wordt **afgeleid** uit de meeting-toestand (geen aparte opgeslagen
status-kolom), één keer berekend en op twee manieren getoond: gedetailleerd in beheer,
vriendelijk/beknopt publiek.

### `MeetingProcessingStatus` (enum)

| Case | Conditie (afgeleid) | Beheer-label | Publiek (één regel) |
|---|---|---|---|
| `PreLaunch` | `ingest_mode != Summarize` én `starts_at < municipality.launch_date` | "Voor livegang — niet samengevat" | "Deze vergadering vond plaats vóór de livegang en is niet samengevat." |
| `Scheduled` | `now < starts_at` (summarizable) | "Gepland" | *verborgen in lijst* |
| `AwaitingVideo` | raad+kanaal én `now < starts_at + video_wait_hours` | "In afwachting van video — verwerking vanaf {tijd}" | "Wordt verwerkt zodra de video beschikbaar is." |
| `Processing` | bron-resolutie actief (videozoektocht/transcript loopt, of media compleet en notule-detectie) | "Bezig met verwerken" | "Bezig met verwerken." |
| `AwaitingNotule` | geen bron, media (nog) niet compleet of binnen recheck-venster | "In afwachting van notule (recheck {werkdag})" | "Wachten op de besluitenlijst." |
| `InReview` | `summarized_at != null`, geen gepubliceerde samenvatting | "In review" | "Bezig met verwerken." |
| `Published` | minstens één gepubliceerde samenvatting | "Gepubliceerd" | *toont de samenvatting* |
| `NoSource` | `summary_skipped_reason == 'no_source'` | "Geen bron — geen samenvatting" | "Geen samenvatting: er is geen besluitenlijst beschikbaar." |

Implementatie als methode `Meeting::processingStatus(): MeetingProcessingStatus` (precedent:
`summaryStatusLabel()`). De enum draagt `adminLabel()` en `publicMessage()`. Voor
beheer-labels met een tijdstip (`AwaitingVideo` → `starts_at + video_wait_hours`,
`AwaitingNotule` → eerstvolgende werkdag-recheck) levert de meeting de bijbehorende
`Carbon` mee; **publiek tonen we geen interne timestamps**.

### Beheer-UI

Toon de afgeleide status (incl. tijdstip waar van toepassing) overal waar meetings worden
gelijst: admin-dashboard, `admin/Municipalities/Show`, en de review-lijst. Geen nieuwe
pagina's; statuskolom/-badge toevoegen aan bestaande lijsten.

### Publieke UI

- **Gemeentepagina (lijst)** — `MunicipalityController::show` toont nu ook **plaatsgevonden**
  meetings zonder gepubliceerde samenvatting, met hun `publicMessage()`. Query verruimen van
  "alleen gepubliceerd" naar "gepubliceerd **of** `starts_at <= now`". Toekomstige
  (`Scheduled`) meetings blijven verborgen. Pre-launch meetings tonen de pre-launch-melding.
- **Meetingpagina** — `MeetingController::show` geeft `processing_status` + `publicMessage`
  mee; de React-pagina toont de regel wanneer er (nog) geen gepubliceerde samenvatting is.
- Eén vriendelijke regel per status; geen interne details/timestamps publiek.

## Foutafhandeling

- AI-agent faalt → loggen, meeting blijft onopgelost; volgende recheck probeert opnieuw
  (binnen de werkdag-grens). Na de grens → `no_source`.
- YouTube/transcript-fouten → bestaande `ThrottlesExceptions`/backoff-middleware blijft.
- Sweep `withoutOverlapping()` voorkomt dubbele verwerking.
- Alle takken loggen via de bestaande `RecordProcessingEvent`/`ProcessingLog`.

## Testen

- Unit/feature-tests voor `ResolveMeetingSummarySources` per tak van de beslisboom:
  raad+kanaal vóór/na 24u, transcript gevonden, video niet gevonden → notule, notule
  gevonden, geen bron → recheck → `no_source`, niet-raad → notule-pad.
- Werkdag-grens: meeting op vrijdag → recheck-deadline valt op dinsdag (weekend overgeslagen).
- `NotuleDetectionAgent` met een gemockte AI-respons (fake), inclusief de aanroep-gating
  (draait niet bij incomplete media; draait wel op rechecks).
- Scheduler: `volgjeraad:resolve` geregistreerd, elke 15 min, `withoutOverlapping`.
- `Meeting::processingStatus()` levert per toestand de juiste `MeetingProcessingStatus`
  (alle 8 cases), inclusief de pre-launch- en `no_source`-takken.
- Publieke gemeentepagina toont plaatsgevonden meetings zonder samenvatting met de juiste
  `publicMessage()`; toekomstige meetings blijven verborgen.

## Buiten scope

- Verwijderen van de review-stap (Draft → direct publiceren). Later.
- NL-feestdagenkalender voor de werkdag-berekening.
- Korte video-retry-marge na 24u.
```
