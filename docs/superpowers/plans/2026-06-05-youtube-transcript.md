# YouTube-transcript Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Per raadsvergadering de bijbehorende YouTube-uitzending vinden, het transcript ophalen, en dat transcript als extra debat-bron meenemen in de vergadering-samenvatting.

**Architecture:** Deterministische code (`YouTubeClient`, Saloon) haalt kandidaat-video's op binnen een kanaal + datumvenster; een `laravel/ai`-agent (`VideoMatchAgent`, structured output) kiest de beste match met confidence. Hoge confidence + een video-id dat daadwerkelijk in de kandidatenlijst staat → automatisch koppelen; lage confidence of een onbekend id → de bestaande admin review-gate, waar een reviewer de juiste kandidaat bevestigt. Een betaalde transcript-API (`TranscriptProvider` → `SupadataTranscriptProvider`, Saloon, Universal `/transcript`-endpoint met sync- én async/jobId-polling) levert het transcript. Een dagelijkse `MatchMeetingVideosJob` orkestreert (chunked) en dispatcht per meeting een `ProcessMeetingVideoJob` met `RateLimited('youtube')`-middleware. De vergadering-samenvatting wordt **pas** gegenereerd zodra de transcript-resolutie klaar is (transcript binnen, óf definitief opgegeven na `youtube.transcript_wait_days` / `max_transcript_attempts` / geen video), zodat `GenerateMeetingSummary` één keer draait met afzonderlijk-begrensde blokken `[besluitenlijst/agenda + transcript]`. De agendapunt-samenvattingen (PDF) draaien meteen door.

**Tech Stack:** Laravel 13, PHP 8.5, Saloon (HTTP-integraties), `laravel/ai` (v0, OpenAI), Inertia v3 + React/TSX, Pest 4, MySQL.

---

## Revisie v3 — verwerkt review #114 + lifecycle-besluit (wachten vóór review)

**Productbesluit (lifecycle).** De vergadering-samenvatting verrijkt **niet** meer achteraf een al-goedgekeurde/gepubliceerde versie. In plaats daarvan wordt de meeting-summary **pas gedispatcht zodra de transcript-resolutie klaar is** — status `Transcribed` (transcript aanwezig) óf transcript definitief opgegeven (geen video / `max_transcript_attempts` bereikt / ná `youtube.transcript_wait_days`). De agendapunt-samenvattingen (PDF-gebaseerd) blijven meteen draaien. Hierdoor wordt de meeting-summary **één keer** gemaakt — mét transcript indien beschikbaar — en vervalt het hele "bestaande Approved/Published vervangen"-probleem.

Wijzigingen t.o.v. v2 (review #114):

- **BLOCKER — wachten vóór review.** Nieuw `Meeting::transcriptResolved()`-predicaat (Task 4) + `DispatchMeetingSummariesIfReady`-action (Task 12) gaten de meeting-summary-dispatch achter `(media-ingest klaar) ∧ (transcript-resolutie klaar)`. De v2-Task-17-logica die non-draft summaries oversloeg en drafts verving, is **verwijderd**; `GenerateMeetingSummary` (Task 18) is weer een genereer-één-keer met afzonderlijk-begrensde transcript-blokken en eenvoudige per-level-idempotency. Deze gating is **additief** bovenop de aparte samenvat-dispatch-fix (die `SummarizeMeetingJob` exact één keer per level laat draaien ná media-ingest); v3 voegt daar de transcript-resolutie-conditie aan toe. Nieuwe config `youtube.transcript_wait_days`. De "exact één summary per (meeting, level, language)"-invariant blijft.
- **MAJOR — gesplitste attempts.** `attempts` → twee tellers: `match_attempts` (zoeken/matchen) en `transcript_attempts` (transcript-fetch). `max_transcript_attempts` geldt alleen op `transcript_attempts`, zodat een video die na meerdere zoekpogingen matched het volledige transcript-retrybudget houdt. (Task 3, 4, 11, 13, 14)
- **MAJOR — scheduler-scope vs `max_find_days`.** `NotFound`/geen-video respecteert `max_find_days`; maar `Matched`/`Failed` mét `youtube_video_id` blijven in scope tot `transcript_attempts` de limiet bereikt of status `Transcribed` is — **ook ná `max_find_days`**. (Task 16)
- **MINOR — taakvolgorde.** `ProcessMeetingVideoJob` (nu Task 14) staat vóór de bevestigingsflow (Task 15) die hem importeert; de lifecycle-gate (Task 12) staat vóór de tasks die hem aanroepen (13, 14, 16). De taken zijn lineair uitvoerbaar.

Voortbouwend op v2 (review #113, ongewijzigd): Supadata Universal-endpoint + sync/async job-polling, AI `video_id`-validatie tegen de kandidatenlijst, bevestigingsflow voor lage confidence, `youtube_channel_id` per gemeente, chunked scheduler met `RateLimited('youtube')` + `ThrottlesExceptions`, aparte `ai.max_transcript_chars`, lege-transcript-flag, `withMockClient()`-Saloon-tests, `$this->travelTo(...)` voor tijd.

**Aantal taken: 18.**

---

## File Structure

**Nieuwe bestanden:**
- `app/Enums/VideoStatus.php` — statusmachine van een `MeetingVideo`.
- `database/migrations/2026_06_05_150000_create_meeting_videos_table.php` — 1-op-1 tabel met `meetings`.
- `app/Models/MeetingVideo.php` + `database/factories/MeetingVideoFactory.php` — model + factory.
- `app/Services/YouTube/VideoCandidate.php` — readonly DTO (videoId, title, publishedAt).
- `app/Http/Integrations/YouTube/YouTubeConnector.php` + `app/Http/Integrations/YouTube/Requests/SearchChannelVideosRequest.php` — Saloon-integratie YouTube Data API v3.
- `app/Services/YouTube/YouTubeClient.php` — zoekt video's op een kanaal binnen een venster.
- `app/Services/Transcript/TranscriptResult.php` — readonly DTO (text, source, lang, segments).
- `app/Services/Transcript/TranscriptProvider.php` — interface.
- `app/Services/Transcript/TranscriptJobFailedException.php` — exception voor mislukte/te-trage async-jobs.
- `app/Http/Integrations/Supadata/SupadataConnector.php` + `app/Http/Integrations/Supadata/Requests/FetchTranscriptRequest.php` + `app/Http/Integrations/Supadata/Requests/GetTranscriptJobRequest.php` — Saloon-integratie transcript-vendor (Universal endpoint + job-poll).
- `app/Services/Transcript/SupadataTranscriptProvider.php` — default-implementatie met polling.
- `app/Ai/Agents/VideoMatchAgent.php` + `resources/prompts/video_match.v1.md` — match-agent + prompt.
- `app/Actions/Videos/FindMeetingVideo.php` — kandidaten ophalen + matchen + `MeetingVideo` schrijven.
- `app/Actions/Videos/FetchMeetingTranscript.php` — transcript ophalen + lifecycle-gate triggeren.
- `app/Actions/Summaries/DispatchMeetingSummariesIfReady.php` — lifecycle-gate: dispatcht de meeting-samenvattingen pas bij `(media klaar) ∧ (transcript-resolutie klaar)`.
- `app/Actions/Videos/ConfirmMeetingVideo.php` — reviewer-bevestiging van een kandidaat.
- `app/Http/Controllers/Admin/VideoReviewController.php` + `resources/js/pages/admin/Videos/Index.tsx` — admin-surface voor `needs_confirmation`.
- `app/Jobs/ProcessMeetingVideoJob.php` — per-meeting werk (find/fetch/retry) met rate-limit-middleware.
- `app/Jobs/MatchMeetingVideosJob.php` — dagelijkse, chunked orkestratie.
- `app/Console/Commands/SetMunicipalityYouTubeChannelCommand.php` — `youtube_channel_id` per gemeente zetten.
- Tests: `tests/Unit/VideoStatusTest.php`, `tests/Feature/Videos/MeetingVideoModelTest.php`, `tests/Feature/Videos/MeetingTranscriptResolvedTest.php`, `tests/Unit/VideoCandidateTest.php`, `tests/Feature/YouTube/YouTubeClientTest.php`, `tests/Unit/TranscriptResultTest.php`, `tests/Feature/Transcript/SupadataTranscriptProviderTest.php`, `tests/Feature/Ai/VideoMatchAgentTest.php`, `tests/Feature/Videos/FindMeetingVideoTest.php`, `tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php`, `tests/Feature/Videos/FetchMeetingTranscriptTest.php`, `tests/Feature/Videos/ConfirmMeetingVideoTest.php`, `tests/Feature/Admin/VideoReviewControllerTest.php`, `tests/Feature/Videos/ProcessMeetingVideoJobTest.php`, `tests/Feature/Videos/MatchMeetingVideosJobTest.php`, `tests/Feature/Console/SetMunicipalityYouTubeChannelCommandTest.php`, `tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php`.

**Gewijzigde bestanden:**
- `config/volgjeraad.php` — `youtube`- (incl. `transcript_wait_days`) en `transcript`-secties + `ai.max_transcript_chars`.
- `.env.example` — `YOUTUBE_API_KEY`, `SUPADATA_API_KEY`.
- `app/Models/Meeting.php` — `video()` hasOne-relatie + `transcriptResolved()`-predicaat.
- `app/Actions/Ingest/IngestAgendaMediaObjects.php` — meeting-summary-dispatch achter de lifecycle-gate (agendapunt-dispatch ongewijzigd).
- `app/Providers/AppServiceProvider.php` — binding `TranscriptProvider` → `SupadataTranscriptProvider` + `youtube`-rate-limiter.
- `routes/web.php` — admin-routes voor video-bevestiging.
- `routes/console.php` — dagelijkse schedule.
- `app/Actions/Summaries/GenerateMeetingSummary.php` — transcript als afzonderlijk-begrensd bronblok (genereer-één-keer; **Task 18**).

---

## Task 1: Config + env-keys

**Files:**
- Modify: `config/volgjeraad.php`
- Modify: `.env.example`

- [ ] **Step 1: Voeg de config-secties toe**

Voeg in `config/volgjeraad.php`, in de bestaande `'ai' => [...]`-array ná `'max_source_chars' => 24000,`, toe:

```php
        // Apart tekenbudget voor het transcript-blok zodat het transcript nooit
        // volledig wegvalt achter een lange agenda/PDF-bron (≈15000 tokens).
        'max_transcript_chars' => 60000,
```

Voeg daarna, ná de `'ai' => [...]`-array en vóór `'launch_date'`, deze twee blokken toe:

```php
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
        'base_url' => env('YOUTUBE_BASE_URL', 'https://www.googleapis.com/youtube/v3'),
        'timeout' => 20,
        'connect_timeout' => 5,
        // Zoekvenster rond meeting->starts_at (dagen vóór en ná).
        'search_window_days' => 3,
        // Minimale agent-confidence (0-100) om automatisch te koppelen.
        'match_confidence_threshold' => 75,
        // Stop met zoeken voor meetings ouder dan N dagen (geldt op NotFound/geen-video).
        'max_find_days' => 14,
        // Maximaal aantal transcript-fetch-pogingen per video voordat we opgeven.
        'max_transcript_attempts' => 4,
        // Hoe lang we met de vergadering-samenvatting wachten op een transcript voordat
        // we 'm zonder transcript maken (apart van max_find_days). 'Wachten vóór review'.
        'transcript_wait_days' => 7,
    ],

    'transcript' => [
        'supadata' => [
            'api_key' => env('SUPADATA_API_KEY'),
            'base_url' => env('SUPADATA_BASE_URL', 'https://api.supadata.ai/v1'),
            // Universal-endpoint mode: native|auto|generate. 'auto' = captions, val terug op AI.
            'mode' => env('SUPADATA_MODE', 'auto'),
            'timeout' => 60,
            'connect_timeout' => 5,
            // Async (202 + jobId) job-polling.
            'poll_max_attempts' => 10,
            'poll_interval_ms' => 2000,
        ],
    ],
```

- [ ] **Step 2: Voeg env-keys toe**

Voeg in `.env.example`, ná `OPENAI_API_KEY=`, toe:

```
YOUTUBE_API_KEY=
SUPADATA_API_KEY=
```

- [ ] **Step 3: Verifieer dat config laadt**

Run: `php artisan config:show volgjeraad.youtube`
Expected: toont de `youtube`-array met `search_window_days => 3`, `match_confidence_threshold => 75`, `max_find_days => 14`, `max_transcript_attempts => 4`, `transcript_wait_days => 7`.

- [ ] **Step 4: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: geen fouten.

- [ ] **Step 5: Commit**

```bash
git add config/volgjeraad.php .env.example
git commit -m "feat: youtube + transcript config en env-keys"
```

---

## Task 2: VideoStatus enum

**Files:**
- Create: `app/Enums/VideoStatus.php`
- Test: `tests/Unit/VideoStatusTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Unit/VideoStatusTest.php`:

```php
<?php

use App\Enums\VideoStatus;

test('video status has the expected string values', function (): void {
    expect(VideoStatus::Pending->value)->toBe('pending');
    expect(VideoStatus::NeedsConfirmation->value)->toBe('needs_confirmation');
    expect(VideoStatus::Matched->value)->toBe('matched');
    expect(VideoStatus::Transcribed->value)->toBe('transcribed');
    expect(VideoStatus::NotFound->value)->toBe('not_found');
    expect(VideoStatus::Failed->value)->toBe('failed');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=VideoStatusTest`
Expected: FAIL met "Class App\Enums\VideoStatus not found".

- [ ] **Step 3: Schrijf het enum**

Maak `app/Enums/VideoStatus.php`:

```php
<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Pending = 'pending';
    case NeedsConfirmation = 'needs_confirmation';
    case Matched = 'matched';
    case Transcribed = 'transcribed';
    case NotFound = 'not_found';
    case Failed = 'failed';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=VideoStatusTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Enums/VideoStatus.php tests/Unit/VideoStatusTest.php
git commit -m "feat: VideoStatus enum"
```

---

## Task 3: meeting_videos migratie

**Files:**
- Create: `database/migrations/2026_06_05_150000_create_meeting_videos_table.php`

- [ ] **Step 1: Schrijf de migratie**

Maak `database/migrations/2026_06_05_150000_create_meeting_videos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('youtube_video_id')->nullable();
            $table->text('video_url')->nullable();
            $table->unsignedTinyInteger('match_confidence')->nullable();
            $table->text('match_reason')->nullable();
            $table->json('candidates')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->string('transcript_source')->nullable();
            $table->string('transcript_error')->nullable();
            $table->dateTime('transcript_fetched_at')->nullable();
            $table->string('status')->default('pending')->index();
            // Twee gescheiden tellers (review #114 MAJOR): zoeken/matchen vs transcript-fetch.
            $table->unsignedInteger('match_attempts')->default(0);
            $table->unsignedInteger('transcript_attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_videos');
    }
};
```

- [ ] **Step 2: Run de migratie tegen de testdatabase**

Run: `php artisan migrate --no-interaction`
Expected: "DONE" voor `create_meeting_videos_table`.

- [ ] **Step 3: Verifieer het schema**

Run: `php artisan db:table meeting_videos`
Expected: toont kolommen `meeting_id` (unique), `youtube_video_id`, `transcript_text`, `transcript_error`, `status`, `match_attempts`, `transcript_attempts` etc.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_05_150000_create_meeting_videos_table.php
git commit -m "feat: meeting_videos migratie"
```

---

## Task 4: MeetingVideo model + factory + Meeting::video()

**Files:**
- Create: `app/Models/MeetingVideo.php`
- Create: `database/factories/MeetingVideoFactory.php`
- Modify: `app/Models/Meeting.php` (relatie `video()` + predicaat `transcriptResolved()`)
- Test: `tests/Feature/Videos/MeetingVideoModelTest.php`
- Test: `tests/Feature/Videos/MeetingTranscriptResolvedTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Videos/MeetingVideoModelTest.php`:

```php
<?php

use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('meeting has one video and casts status and candidates', function (): void {
    $meeting = Meeting::factory()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Matched->value,
        'candidates' => [['videoId' => 'abc', 'title' => 'Raadsvergadering']],
    ]);

    expect($meeting->fresh()->video)->not->toBeNull();
    expect($meeting->fresh()->video->id)->toBe($video->id);
    expect($video->status)->toBe(VideoStatus::Matched);
    expect($video->candidates)->toBe([['videoId' => 'abc', 'title' => 'Raadsvergadering']]);
});

test('new meeting video defaults to pending status and zero attempts', function (): void {
    $meeting = Meeting::factory()->create();
    $video = new MeetingVideo(['meeting_id' => $meeting->id]);
    $video->save();

    expect($video->status)->toBe(VideoStatus::Pending);
    expect($video->match_attempts)->toBe(0);
    expect($video->transcript_attempts)->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MeetingVideoModelTest`
Expected: FAIL met "Class App\Models\MeetingVideo not found".

- [ ] **Step 3: Schrijf het model**

Maak `app/Models/MeetingVideo.php`:

```php
<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Database\Factories\MeetingVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingVideo extends Model
{
    /** @use HasFactory<MeetingVideoFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'match_attempts' => 0,
        'transcript_attempts' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'candidates' => 'array',
            'status' => VideoStatus::class,
            'confirmed_at' => 'datetime',
            'transcript_fetched_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
```

- [ ] **Step 4: Schrijf de factory**

Maak `database/factories/MeetingVideoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingVideo>
 */
class MeetingVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'youtube_video_id' => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'video_url' => null,
            'match_confidence' => null,
            'match_reason' => null,
            'candidates' => null,
            'confirmed_at' => null,
            'transcript_text' => null,
            'transcript_source' => null,
            'transcript_error' => null,
            'transcript_fetched_at' => null,
            'status' => VideoStatus::Pending->value,
            'match_attempts' => 0,
            'transcript_attempts' => 0,
            'last_attempt_at' => null,
        ];
    }

    public function transcribed(): static
    {
        return $this->state([
            'status' => VideoStatus::Transcribed->value,
            'transcript_text' => 'Voorzitter: ik open de vergadering.',
            'transcript_source' => 'supadata:auto',
            'transcript_fetched_at' => now(),
            'confirmed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Voeg de relatie toe aan Meeting**

In `app/Models/Meeting.php`, ná de `newsletter()`-methode (regel 61), voeg toe:

```php
    /** @return HasOne<MeetingVideo, $this> */
    public function video(): HasOne
    {
        return $this->hasOne(MeetingVideo::class);
    }
```

(`use Illuminate\Database\Eloquent\Relations\HasOne;` staat al geïmporteerd, regel 13.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=MeetingVideoModelTest`
Expected: PASS (beide tests).

- [ ] **Step 7: Schrijf de failing test voor `transcriptResolved()`**

> Dit predicaat is het hart van "wachten vóór review": het zegt of de transcript-resolutie van een meeting klaar is, zodat de lifecycle-gate (Task 12) mag dispatchen. Niet-raadsvergaderingen hebben geen transcript-pijplijn en zijn dus direct resolved.

Maak `tests/Feature/Videos/MeetingTranscriptResolvedTest.php`:

```php
<?php

use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.transcript_wait_days' => 7,
    ]);
});

test('a non-council meeting is always resolved (no transcript expected)', function (): void {
    $meeting = Meeting::factory()->create(['type' => 'committee', 'starts_at' => now()]);

    expect($meeting->transcriptResolved())->toBeTrue();
});

test('a council meeting with a transcribed video is resolved', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()]);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    expect($meeting->fresh()->transcriptResolved())->toBeTrue();
});

test('a council meeting within the wait window without a transcript is not resolved', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDays(2)]);

    expect($meeting->transcriptResolved())->toBeFalse();
});

test('a council meeting is resolved once the wait window elapses without a transcript', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDays(8)]);

    expect($meeting->transcriptResolved())->toBeTrue();
});

test('a council meeting with a failed transcript at the attempt limit is resolved', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDay()]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 4,
    ]);

    expect($meeting->fresh()->transcriptResolved())->toBeTrue();
});

test('a council meeting with a failed transcript under the limit keeps waiting', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDay()]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 1,
    ]);

    expect($meeting->fresh()->transcriptResolved())->toBeFalse();
});
```

- [ ] **Step 8: Run test to verify it fails**

Run: `php artisan test --compact --filter=MeetingTranscriptResolvedTest`
Expected: FAIL met "Call to undefined method App\Models\Meeting::transcriptResolved()".

- [ ] **Step 9: Voeg `transcriptResolved()` toe aan Meeting**

In `app/Models/Meeting.php`, voeg de import toe (naast de bestaande `use App\Enums\MeetingType;`):

```php
use App\Enums\VideoStatus;
```

En voeg, ná de zojuist toegevoegde `video()`-methode, het predicaat toe:

```php
    /**
     * Is de transcript-resolutie klaar? Dat is zo wanneer er geen transcript wordt
     * verwacht (niet-raad), het transcript binnen is (Transcribed), het definitief is
     * opgegeven (Failed op de attempt-limiet), of de wachttijd is verstreken. De
     * lifecycle-gate (DispatchMeetingSummariesIfReady) leunt hierop.
     */
    public function transcriptResolved(): bool
    {
        if ($this->type !== MeetingType::Council) {
            return true;
        }

        $video = $this->video;

        if ($video?->status === VideoStatus::Transcribed) {
            return true;
        }

        if ($video?->status === VideoStatus::Failed
            && $video->transcript_attempts >= (int) config('volgjeraad.youtube.max_transcript_attempts')) {
            return true;
        }

        $waitDays = (int) config('volgjeraad.youtube.transcript_wait_days');

        return $this->starts_at !== null
            && now()->greaterThanOrEqualTo($this->starts_at->copy()->addDays($waitDays));
    }
```

- [ ] **Step 10: Run beide model-tests to verify they pass**

Run: `php artisan test --compact --filter="MeetingVideoModelTest|MeetingTranscriptResolvedTest"`
Expected: PASS.

- [ ] **Step 11: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/MeetingVideo.php database/factories/MeetingVideoFactory.php app/Models/Meeting.php tests/Feature/Videos/MeetingVideoModelTest.php tests/Feature/Videos/MeetingTranscriptResolvedTest.php
git commit -m "feat: MeetingVideo model/factory, Meeting::video en transcriptResolved-predicaat"
```

---

## Task 5: VideoCandidate DTO

> **Spec-afwijking (bewust, YAGNI):** de spec (§3) noemt `duur` op `VideoCandidate`. De YouTube `search`-endpoint levert geen duur; dat zou een extra `videos.list?part=contentDetails`-call vergen. Duur weegt niet mee in de match en valt hier weg. Genoteerd als toekomstige uitbreiding indien matchkwaliteit dat vraagt.

**Files:**
- Create: `app/Services/YouTube/VideoCandidate.php`
- Test: `tests/Unit/VideoCandidateTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Unit/VideoCandidateTest.php`:

```php
<?php

use App\Services\YouTube\VideoCandidate;
use Carbon\CarbonImmutable;

test('video candidate holds id, title and published date', function (): void {
    $candidate = new VideoCandidate(
        videoId: 'dQw4w9WgXcQ',
        title: 'Raadsvergadering 4 juni 2026',
        publishedAt: CarbonImmutable::parse('2026-06-04T19:00:00Z'),
    );

    expect($candidate->videoId)->toBe('dQw4w9WgXcQ');
    expect($candidate->title)->toBe('Raadsvergadering 4 juni 2026');
    expect($candidate->publishedAt->toDateString())->toBe('2026-06-04');
});

test('video candidate serialises to array for storage and the agent', function (): void {
    $candidate = new VideoCandidate(
        videoId: 'dQw4w9WgXcQ',
        title: 'Raadsvergadering',
        publishedAt: CarbonImmutable::parse('2026-06-04T19:00:00Z'),
    );

    expect($candidate->toArray())->toBe([
        'videoId' => 'dQw4w9WgXcQ',
        'title' => 'Raadsvergadering',
        'publishedAt' => '2026-06-04T19:00:00+00:00',
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=VideoCandidateTest`
Expected: FAIL met "Class App\Services\YouTube\VideoCandidate not found".

- [ ] **Step 3: Schrijf het DTO**

Maak `app/Services/YouTube/VideoCandidate.php`:

```php
<?php

namespace App\Services\YouTube;

use Carbon\CarbonImmutable;

final readonly class VideoCandidate
{
    public function __construct(
        public string $videoId,
        public string $title,
        public CarbonImmutable $publishedAt,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'videoId' => $this->videoId,
            'title' => $this->title,
            'publishedAt' => $this->publishedAt->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=VideoCandidateTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/YouTube/VideoCandidate.php tests/Unit/VideoCandidateTest.php
git commit -m "feat: VideoCandidate DTO"
```

---

## Task 6: YouTube Saloon-connector + SearchChannelVideosRequest

**Files:**
- Create: `app/Http/Integrations/YouTube/YouTubeConnector.php`
- Create: `app/Http/Integrations/YouTube/Requests/SearchChannelVideosRequest.php`

> Geen aparte test in deze taak; de request + connector worden end-to-end getest in Task 7 via `withMockClient()` (zelfde patroon als `tests/Feature/Ori/OriClientTest.php`).

- [ ] **Step 1: Schrijf de connector**

Maak `app/Http/Integrations/YouTube/YouTubeConnector.php`:

```php
<?php

namespace App\Http\Integrations\YouTube;

use Saloon\Http\Connector;

class YouTubeConnector extends Connector
{
    public ?int $tries = 3;

    public ?int $retryInterval = 250;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('volgjeraad.youtube.base_url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        // Stuur de API-key alleen mee wanneer die geconfigureerd is; geen stille `key=null`.
        $key = config('volgjeraad.youtube.api_key');

        return $key !== null ? ['key' => $key] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => config('volgjeraad.youtube.timeout'),
            'connect_timeout' => config('volgjeraad.youtube.connect_timeout'),
        ];
    }
}
```

- [ ] **Step 2: Schrijf de request**

Maak `app/Http/Integrations/YouTube/Requests/SearchChannelVideosRequest.php`:

```php
<?php

namespace App\Http\Integrations\YouTube\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchChannelVideosRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public string $channelId,
        public CarbonImmutable $publishedAfter,
        public CarbonImmutable $publishedBefore,
        public int $maxResults = 25,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/search';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return [
            'part' => 'snippet',
            'type' => 'video',
            'order' => 'date',
            'channelId' => $this->channelId,
            'maxResults' => $this->maxResults,
            'publishedAfter' => $this->publishedAfter->toIso8601ZuluString(),
            'publishedBefore' => $this->publishedBefore->toIso8601ZuluString(),
        ];
    }
}
```

- [ ] **Step 3: Verifieer dat de klassen laden**

Run: `php artisan tinker --execute 'new App\Http\Integrations\YouTube\YouTubeConnector(); echo "ok";'`
Expected: print `ok` zonder fatale fout.

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Integrations/YouTube/
git commit -m "feat: YouTube Saloon connector en SearchChannelVideosRequest"
```

---

## Task 7: YouTubeClient

**Files:**
- Create: `app/Services/YouTube/YouTubeClient.php`
- Test: `tests/Feature/YouTube/YouTubeClientTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/YouTube/YouTubeClientTest.php`:

```php
<?php

use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use App\Services\YouTube\YouTubeClient;
use Carbon\CarbonImmutable;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

test('searchChannel sends channel + date query and parses candidates', function (): void {
    $from = CarbonImmutable::parse('2026-06-01T00:00:00Z');
    $to = CarbonImmutable::parse('2026-06-07T00:00:00Z');

    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => ['videoId' => 'dQw4w9WgXcQ'],
                    'snippet' => [
                        'title' => 'Raadsvergadering 4 juni 2026',
                        'publishedAt' => '2026-06-04T19:30:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $candidates = $client->searchChannel('UC_brummen', $from, $to);

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]->videoId)->toBe('dQw4w9WgXcQ');
    expect($candidates[0]->title)->toBe('Raadsvergadering 4 juni 2026');
    expect($candidates[0]->publishedAt->toDateString())->toBe('2026-06-04');

    $mockClient->assertSent(SearchChannelVideosRequest::class);
    $sent = $mockClient->getLastPendingRequest();
    $query = $sent->query()->all();
    expect($query['channelId'])->toBe('UC_brummen');
    expect($query['publishedAfter'])->toBe('2026-06-01T00:00:00Z');
    expect($query['publishedBefore'])->toBe('2026-06-07T00:00:00Z');
});

test('searchChannel returns empty array when no items', function (): void {
    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make(['items' => []], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $candidates = $client->searchChannel(
        'UC_brummen',
        CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        CarbonImmutable::parse('2026-06-07T00:00:00Z'),
    );

    expect($candidates)->toBe([]);
});

test('searchChannel skips items without a videoId', function (): void {
    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make([
            'items' => [
                ['id' => ['kind' => 'youtube#channel'], 'snippet' => ['title' => 'Kanaal', 'publishedAt' => '2026-06-04T19:30:00Z']],
                ['id' => ['videoId' => 'abc12345678'], 'snippet' => ['title' => 'Raad', 'publishedAt' => '2026-06-04T19:30:00Z']],
            ],
        ], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $candidates = $client->searchChannel(
        'UC_brummen',
        CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        CarbonImmutable::parse('2026-06-07T00:00:00Z'),
    );

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]->videoId)->toBe('abc12345678');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=YouTubeClientTest`
Expected: FAIL met "Class App\Services\YouTube\YouTubeClient not found".

- [ ] **Step 3: Schrijf de client**

Maak `app/Services/YouTube/YouTubeClient.php`:

```php
<?php

namespace App\Services\YouTube;

use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use Carbon\CarbonImmutable;

class YouTubeClient
{
    public function __construct(private YouTubeConnector $connector) {}

    /**
     * @return array<int, VideoCandidate>
     */
    public function searchChannel(string $channelId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = $this->connector
            ->send(new SearchChannelVideosRequest($channelId, $from, $to))
            ->throw()
            ->json('items', []);

        $candidates = [];
        foreach ($items as $item) {
            $videoId = $item['id']['videoId'] ?? null;
            if ($videoId === null) {
                continue;
            }

            $candidates[] = new VideoCandidate(
                videoId: (string) $videoId,
                title: (string) ($item['snippet']['title'] ?? ''),
                publishedAt: CarbonImmutable::parse($item['snippet']['publishedAt']),
            );
        }

        return $candidates;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=YouTubeClientTest`
Expected: PASS (alle drie tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/YouTube/YouTubeClient.php tests/Feature/YouTube/YouTubeClientTest.php
git commit -m "feat: YouTubeClient zoekt kanaal-video's binnen datumvenster"
```

---

## Task 8: TranscriptResult DTO + TranscriptProvider interface + exception

> **Contract-correctie (review BLOCKER 1):** Supadata levert geen `captions|ai` `source`-veld. `TranscriptResult` draagt daarom een vrije `source`-string (bijv. `'supadata:auto'`) plus `lang`; `segments` is optioneel (gevuld wanneer de vendor `content` als array teruggeeft).

**Files:**
- Create: `app/Services/Transcript/TranscriptResult.php`
- Create: `app/Services/Transcript/TranscriptProvider.php`
- Create: `app/Services/Transcript/TranscriptJobFailedException.php`
- Test: `tests/Unit/TranscriptResultTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Unit/TranscriptResultTest.php`:

```php
<?php

use App\Services\Transcript\TranscriptResult;

test('transcript result holds text, source, lang and optional segments', function (): void {
    $result = new TranscriptResult(
        text: 'Voorzitter: ik open de vergadering.',
        source: 'supadata:auto',
        lang: 'nl',
        segments: [['start' => 0, 'text' => 'Voorzitter']],
    );

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->source)->toBe('supadata:auto');
    expect($result->lang)->toBe('nl');
    expect($result->segments)->toBe([['start' => 0, 'text' => 'Voorzitter']]);
});

test('transcript result lang and segments default to null', function (): void {
    $result = new TranscriptResult(text: 'tekst', source: 'supadata:auto');

    expect($result->lang)->toBeNull();
    expect($result->segments)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TranscriptResultTest`
Expected: FAIL met "Class App\Services\Transcript\TranscriptResult not found".

- [ ] **Step 3: Schrijf het DTO, de interface en de exception**

Maak `app/Services/Transcript/TranscriptResult.php`:

```php
<?php

namespace App\Services\Transcript;

final readonly class TranscriptResult
{
    /**
     * @param  array<int, array<string, mixed>>|null  $segments
     */
    public function __construct(
        public string $text,
        public string $source,
        public ?string $lang = null,
        public ?array $segments = null,
    ) {}
}
```

Maak `app/Services/Transcript/TranscriptProvider.php`:

```php
<?php

namespace App\Services\Transcript;

interface TranscriptProvider
{
    public function fetch(string $youtubeVideoId, string $language = 'nl'): TranscriptResult;
}
```

Maak `app/Services/Transcript/TranscriptJobFailedException.php`:

```php
<?php

namespace App\Services\Transcript;

use RuntimeException;

class TranscriptJobFailedException extends RuntimeException {}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TranscriptResultTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Transcript/TranscriptResult.php app/Services/Transcript/TranscriptProvider.php app/Services/Transcript/TranscriptJobFailedException.php tests/Unit/TranscriptResultTest.php
git commit -m "feat: TranscriptResult DTO, TranscriptProvider interface en job-exception"
```

---

## Task 9: SupadataTranscriptProvider (Universal endpoint + job-polling) + binding

> **Echt Supadata-contract (review BLOCKER 1).** Universal `GET /transcript` met `url=https://youtu.be/{videoId}`, `text=true`, `mode=auto`, `lang=nl`. Twee responsepaden:
> 1. **Synchroon** (200): body bevat `content` (string óf segment-array), `lang`, `availableLangs`.
> 2. **Async** (202 of body met `jobId` zonder `content`): poll `GET /transcript/{jobId}` tot `status=completed` (body met `content`) of `status=failed|error` → `TranscriptJobFailedException`; begrensd op `poll_max_attempts`.

**Files:**
- Create: `app/Http/Integrations/Supadata/SupadataConnector.php`
- Create: `app/Http/Integrations/Supadata/Requests/FetchTranscriptRequest.php`
- Create: `app/Http/Integrations/Supadata/Requests/GetTranscriptJobRequest.php`
- Create: `app/Services/Transcript/SupadataTranscriptProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Transcript/SupadataTranscriptProviderTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Transcript/SupadataTranscriptProviderTest.php`:

```php
<?php

use App\Http\Integrations\Supadata\Requests\FetchTranscriptRequest;
use App\Http\Integrations\Supadata\Requests\GetTranscriptJobRequest;
use App\Http\Integrations\Supadata\SupadataConnector;
use App\Services\Transcript\SupadataTranscriptProvider;
use App\Services\Transcript\TranscriptJobFailedException;
use App\Services\Transcript\TranscriptProvider;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    // Geen echte sleeps tussen poll-pogingen tijdens tests.
    config(['volgjeraad.transcript.supadata.poll_interval_ms' => 0]);
});

function supadataProvider(MockClient $mockClient): SupadataTranscriptProvider
{
    $connector = new SupadataConnector;
    $connector->withMockClient($mockClient);

    return new SupadataTranscriptProvider($connector);
}

test('synchronous 200 with text string returns transcript and youtu.be url query', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => 'Voorzitter: ik open de vergadering.',
            'lang' => 'nl',
            'availableLangs' => ['nl'],
        ], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ', 'nl');

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->source)->toBe('supadata:auto');
    expect($result->lang)->toBe('nl');
    expect($result->segments)->toBeNull();

    $sent = $mockClient->getLastPendingRequest();
    $query = $sent->query()->all();
    expect($query['url'])->toBe('https://youtu.be/dQw4w9WgXcQ');
    expect($query['lang'])->toBe('nl');
    expect($query['text'])->toBe('true');
    expect($query['mode'])->toBe('auto');
});

test('synchronous 200 with segment array joins text and keeps segments', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => [
                ['offset' => 0, 'text' => 'Voorzitter: ik open'],
                ['offset' => 3000, 'text' => 'de vergadering.'],
            ],
            'lang' => 'nl',
        ], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->segments)->toHaveCount(2);
});

test('async 202 with jobId polls until completed', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['jobId' => 'job-123'], 202),
        GetTranscriptJobRequest::class => MockResponse::make([
            'status' => 'completed',
            'content' => 'Async transcript klaar.',
            'lang' => 'nl',
        ], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('Async transcript klaar.');
    expect($result->source)->toBe('supadata:auto');
    $mockClient->assertSent(GetTranscriptJobRequest::class);
});

test('async job that fails throws TranscriptJobFailedException', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['jobId' => 'job-err'], 202),
        GetTranscriptJobRequest::class => MockResponse::make(['status' => 'failed'], 200),
    ]);

    supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');
})->throws(TranscriptJobFailedException::class);

test('404 from vendor bubbles up as a request exception', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['error' => 'not found'], 404),
    ]);

    supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');
})->throws(NotFoundException::class);

test('empty content returns empty text without throwing', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['content' => '', 'lang' => 'nl'], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('');
});

test('TranscriptProvider interface resolves to Supadata implementation', function (): void {
    expect(app(TranscriptProvider::class))->toBeInstanceOf(SupadataTranscriptProvider::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SupadataTranscriptProviderTest`
Expected: FAIL met "Class App\Http\Integrations\Supadata\SupadataConnector not found".

- [ ] **Step 3: Schrijf de connector**

Maak `app/Http/Integrations/Supadata/SupadataConnector.php`:

```php
<?php

namespace App\Http\Integrations\Supadata;

use Saloon\Http\Connector;

class SupadataConnector extends Connector
{
    public ?int $tries = 3;

    public ?int $retryInterval = 500;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('volgjeraad.transcript.supadata.base_url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'x-api-key' => config('volgjeraad.transcript.supadata.api_key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => config('volgjeraad.transcript.supadata.timeout'),
            'connect_timeout' => config('volgjeraad.transcript.supadata.connect_timeout'),
        ];
    }
}
```

- [ ] **Step 4: Schrijf de requests**

Maak `app/Http/Integrations/Supadata/Requests/FetchTranscriptRequest.php`:

```php
<?php

namespace App\Http\Integrations\Supadata\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class FetchTranscriptRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public string $youtubeVideoId,
        public string $language = 'nl',
        public string $mode = 'auto',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/transcript';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return [
            'url' => "https://youtu.be/{$this->youtubeVideoId}",
            'lang' => $this->language,
            'mode' => $this->mode,
            // Literal 'true' zoals de Supadata-querycontract verwacht (platte tekst i.p.v. segmenten).
            'text' => 'true',
        ];
    }
}
```

Maak `app/Http/Integrations/Supadata/Requests/GetTranscriptJobRequest.php`:

```php
<?php

namespace App\Http\Integrations\Supadata\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTranscriptJobRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public string $jobId) {}

    public function resolveEndpoint(): string
    {
        return "/transcript/{$this->jobId}";
    }
}
```

- [ ] **Step 5: Schrijf de provider**

Maak `app/Services/Transcript/SupadataTranscriptProvider.php`:

```php
<?php

namespace App\Services\Transcript;

use App\Http\Integrations\Supadata\Requests\FetchTranscriptRequest;
use App\Http\Integrations\Supadata\Requests\GetTranscriptJobRequest;
use App\Http\Integrations\Supadata\SupadataConnector;

class SupadataTranscriptProvider implements TranscriptProvider
{
    public function __construct(private SupadataConnector $connector) {}

    public function fetch(string $youtubeVideoId, string $language = 'nl'): TranscriptResult
    {
        $mode = (string) config('volgjeraad.transcript.supadata.mode', 'auto');

        $response = $this->connector
            ->send(new FetchTranscriptRequest($youtubeVideoId, $language, $mode))
            ->throw();

        $json = $response->json();

        // Async-pad: 202 of een jobId zonder directe content → pollen.
        if ($response->status() === 202 || (isset($json['jobId']) && ! isset($json['content']))) {
            $json = $this->pollJob((string) $json['jobId']);
        }

        return $this->toResult($json, $mode, $language);
    }

    /**
     * @return array<string, mixed>
     */
    private function pollJob(string $jobId): array
    {
        $maxAttempts = (int) config('volgjeraad.transcript.supadata.poll_max_attempts', 10);
        $intervalMs = (int) config('volgjeraad.transcript.supadata.poll_interval_ms', 2000);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $json = $this->connector
                ->send(new GetTranscriptJobRequest($jobId))
                ->throw()
                ->json();

            $status = $json['status'] ?? null;

            if ($status === 'completed') {
                return $json;
            }

            if ($status === 'failed' || $status === 'error') {
                throw new TranscriptJobFailedException("Supadata transcript job {$jobId} failed.");
            }

            if ($intervalMs > 0) {
                usleep($intervalMs * 1000);
            }
        }

        throw new TranscriptJobFailedException("Supadata transcript job {$jobId} did not complete in time.");
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function toResult(array $json, string $mode, string $language): TranscriptResult
    {
        $content = $json['content'] ?? '';
        $segments = null;

        if (is_array($content)) {
            $segments = $content;
            $text = implode(' ', array_map(
                fn (array $segment): string => (string) ($segment['text'] ?? ''),
                $content,
            ));
        } else {
            $text = (string) $content;
        }

        return new TranscriptResult(
            text: trim($text),
            source: "supadata:{$mode}",
            lang: $json['lang'] ?? $language,
            segments: $segments,
        );
    }
}
```

- [ ] **Step 6: Registreer de binding**

In `app/Providers/AppServiceProvider.php`, in de `register()`-methode (vervang de `//`-placeholder), voeg toe:

```php
        $this->app->bind(
            \App\Services\Transcript\TranscriptProvider::class,
            \App\Services\Transcript\SupadataTranscriptProvider::class,
        );
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=SupadataTranscriptProviderTest`
Expected: PASS (alle zeven tests).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Integrations/Supadata/ app/Services/Transcript/SupadataTranscriptProvider.php app/Providers/AppServiceProvider.php tests/Feature/Transcript/SupadataTranscriptProviderTest.php
git commit -m "feat: SupadataTranscriptProvider met Universal endpoint en job-polling"
```

---

## Task 10: VideoMatchAgent + prompt

**Files:**
- Create: `app/Ai/Agents/VideoMatchAgent.php`
- Create: `resources/prompts/video_match.v1.md`
- Test: `tests/Feature/Ai/VideoMatchAgentTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Ai/VideoMatchAgentTest.php`:

```php
<?php

use App\Ai\Agents\VideoMatchAgent;
use Laravel\Ai\Enums\Lab;

test('video match agent returns structured video_id, confidence and reason', function (): void {
    VideoMatchAgent::fake([[
        'video_id' => 'dQw4w9WgXcQ',
        'confidence' => 88,
        'reason' => 'Titel bevat "Raadsvergadering" en de datum komt overeen.',
    ]]);

    $agent = new VideoMatchAgent('gpt-4o-mini', 'v1');
    $response = $agent->prompt('meeting + kandidaten', provider: Lab::OpenAI, model: 'gpt-4o-mini');

    expect($response->structured['video_id'])->toBe('dQw4w9WgXcQ');
    expect($response->structured['confidence'])->toBe(88);
    expect($response->structured['reason'])->toBeString();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=VideoMatchAgentTest`
Expected: FAIL met "Class App\Ai\Agents\VideoMatchAgent not found".

- [ ] **Step 3: Schrijf de prompt**

Maak `resources/prompts/video_match.v1.md`:

```markdown
Je krijgt de gegevens van een Nederlandse gemeenteraadsvergadering (naam, datum, type)
en een lijst kandidaat-YouTube-video's (video_id, titel, publicatiedatum).

Kies de video die het meest waarschijnlijk de opname van deze raadsvergadering is.

Beoordeel op:
- Komt de titel overeen met een raadsvergadering (bijv. "raadsvergadering", "gemeenteraad")?
- Ligt de publicatiedatum dicht bij de vergaderdatum (zelfde dag of kort erna)?

Geef terug:
- `video_id`: het id van de gekozen video — KIES UITSLUITEND uit de aangeboden kandidaten
  en verzin geen id. Geef een lege string als geen enkele kandidaat past.
- `confidence`: 0-100, hoe zeker je bent. Geef < 75 bij twijfel of meerdere plausibele kandidaten.
- `reason`: korte Nederlandse onderbouwing.
```

- [ ] **Step 4: Schrijf de agent**

Maak `app/Ai/Agents/VideoMatchAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class VideoMatchAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        return PromptRepository::load('video_match', $this->promptVersion);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'video_id' => $schema->string()->required(),
            'confidence' => $schema->integer()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=VideoMatchAgentTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Agents/VideoMatchAgent.php resources/prompts/video_match.v1.md tests/Feature/Ai/VideoMatchAgentTest.php
git commit -m "feat: VideoMatchAgent met structured output en prompt"
```

---

## Task 11: FindMeetingVideo action

> Signatuur: `handle(Meeting $meeting): ?MeetingVideo`. Haalt `youtube_channel_id` uit `municipality->settings`, zoekt kandidaten via `YouTubeClient`, laat `VideoMatchAgent` kiezen. **Auto-match alleen als het gekozen `video_id` exact in de kandidatenlijst zit én de confidence ≥ drempel** (review MAJOR 7); anders `NeedsConfirmation`. Ontbrekend channel-id wordt gelogd, niet stil null (review MAJOR 5).

**Files:**
- Create: `app/Actions/Videos/FindMeetingVideo.php`
- Test: `tests/Feature/Videos/FindMeetingVideoTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Videos/FindMeetingVideoTest.php`:

```php
<?php

use App\Actions\Videos\FindMeetingVideo;
use App\Ai\Agents\VideoMatchAgent;
use App\Enums\VideoStatus;
use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

function makeCouncilMeetingWithChannel(): Meeting
{
    $municipality = Municipality::factory()->create([
        'settings' => ['youtube_channel_id' => 'UC_brummen'],
    ]);

    return Meeting::factory()->council()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Raadsvergadering 4 juni 2026',
        'starts_at' => '2026-06-04 19:00:00',
    ]);
}

/**
 * Bind een YouTubeConnector met een MockClient in de container, zodat de
 * container-resolved YouTubeClient dezelfde fake gebruikt (ORI-patroon, review MINOR).
 *
 * @param  array<string, mixed>  $response
 */
function bindFakeYouTube(array $response): void
{
    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make($response, 200),
    ]);

    app()->bind(YouTubeConnector::class, function () use ($mockClient): YouTubeConnector {
        $connector = new YouTubeConnector;
        $connector->withMockClient($mockClient);

        return $connector;
    });
}

function oneCandidateResponse(): array
{
    return [
        'items' => [
            [
                'id' => ['videoId' => 'dQw4w9WgXcQ'],
                'snippet' => [
                    'title' => 'Raadsvergadering 4 juni 2026',
                    'publishedAt' => '2026-06-04T21:00:00Z',
                ],
            ],
        ],
    ];
}

test('high confidence with a known video_id auto-matches and confirms', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([[
        'video_id' => 'dQw4w9WgXcQ',
        'confidence' => 90,
        'reason' => 'Titel en datum komen overeen.',
    ]]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video)->not->toBeNull();
    expect($video->status)->toBe(VideoStatus::Matched);
    expect($video->youtube_video_id)->toBe('dQw4w9WgXcQ');
    expect($video->match_confidence)->toBe(90);
    expect($video->confirmed_at)->not->toBeNull();
    expect($video->candidates)->toHaveCount(1);
});

test('low confidence stores needs_confirmation without confirming', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([[
        'video_id' => 'dQw4w9WgXcQ',
        'confidence' => 40,
        'reason' => 'Onzeker; titel wijkt af.',
    ]]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NeedsConfirmation);
    expect($video->confirmed_at)->toBeNull();
    expect($video->match_confidence)->toBe(40);
});

test('agent choosing an unknown video_id never auto-matches', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([[
        'video_id' => 'HALLUCINATED99',
        'confidence' => 99,
        'reason' => 'Hoge confidence maar verzonnen id.',
    ]]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NeedsConfirmation);
    expect($video->youtube_video_id)->toBeNull();
});

test('no candidates stores not_found and increments attempts', function (): void {
    bindFakeYouTube(['items' => []]);
    VideoMatchAgent::fake([]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NotFound);
    expect($video->match_attempts)->toBe(1);
    expect($video->youtube_video_id)->toBeNull();
});

test('missing channel id returns null, logs a warning and creates no video', function (): void {
    VideoMatchAgent::fake([]);
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $msg === 'find_meeting_video missing channel id' && isset($ctx['meeting_id']));

    $municipality = Municipality::factory()->create(['settings' => null]);
    $meeting = Meeting::factory()->council()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'starts_at' => '2026-06-04 19:00:00',
    ]);

    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video)->toBeNull();
    expect($meeting->fresh()->video)->toBeNull();
});

test('repeated find on same meeting updates the single video row', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([
        ['video_id' => 'dQw4w9WgXcQ', 'confidence' => 40, 'reason' => 'Onzeker.'],
        ['video_id' => 'dQw4w9WgXcQ', 'confidence' => 90, 'reason' => 'Nu zeker.'],
    ]);

    $meeting = makeCouncilMeetingWithChannel();
    $action = app(FindMeetingVideo::class);

    $action->handle($meeting);
    $action->handle($meeting);

    expect($meeting->fresh()->video->status)->toBe(VideoStatus::Matched);
    expect($meeting->fresh()->video->match_attempts)->toBe(2);
    expect(\App\Models\MeetingVideo::where('meeting_id', $meeting->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FindMeetingVideoTest`
Expected: FAIL met "Class App\Actions\Videos\FindMeetingVideo not found".

- [ ] **Step 3: Schrijf de action**

Maak `app/Actions/Videos/FindMeetingVideo.php`:

```php
<?php

namespace App\Actions\Videos;

use App\Ai\Agents\VideoMatchAgent;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Services\YouTube\VideoCandidate;
use App\Services\YouTube\YouTubeClient;
use App\Support\PromptRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class FindMeetingVideo
{
    public function __construct(
        private YouTubeClient $youTubeClient,
    ) {}

    public function handle(Meeting $meeting): ?MeetingVideo
    {
        $channelId = $meeting->municipality->settings['youtube_channel_id'] ?? null;
        if ($channelId === null) {
            Log::warning('find_meeting_video missing channel id', [
                'meeting_id' => $meeting->id,
                'municipality_id' => $meeting->municipality_id,
            ]);

            return null;
        }

        if ($meeting->starts_at === null) {
            return null;
        }

        $windowDays = (int) config('volgjeraad.youtube.search_window_days');
        $from = CarbonImmutable::instance($meeting->starts_at)->subDays($windowDays);
        $to = CarbonImmutable::instance($meeting->starts_at)->addDays($windowDays);

        try {
            $candidates = $this->youTubeClient->searchChannel($channelId, $from, $to);
        } catch (Throwable $e) {
            Log::warning('find_meeting_video search failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $candidates = [];
        }

        if ($candidates === []) {
            return $this->store($meeting, VideoStatus::NotFound, candidates: []);
        }

        $choice = $this->pick($meeting, $candidates);
        $threshold = (int) config('volgjeraad.youtube.match_confidence_threshold');
        $confidence = (int) ($choice['confidence'] ?? 0);
        $chosenId = (string) ($choice['video_id'] ?? '');

        $candidateIds = array_map(fn (VideoCandidate $c): string => $c->videoId, $candidates);
        $isKnown = $chosenId !== '' && in_array($chosenId, $candidateIds, true);

        if ($isKnown && $confidence >= $threshold) {
            return $this->store(
                $meeting,
                VideoStatus::Matched,
                candidates: $candidates,
                videoId: $chosenId,
                confidence: $confidence,
                reason: (string) ($choice['reason'] ?? ''),
            );
        }

        // Onbekend id of te lage confidence → menselijke bevestiging.
        $reason = $isKnown
            ? (string) ($choice['reason'] ?? '')
            : 'Agent koos een video_id buiten de kandidatenlijst; handmatige bevestiging vereist.';

        return $this->store(
            $meeting,
            VideoStatus::NeedsConfirmation,
            candidates: $candidates,
            videoId: $isKnown ? $chosenId : null,
            confidence: $confidence,
            reason: $reason,
        );
    }

    /**
     * @param  array<int, VideoCandidate>  $candidates
     * @return array<string, mixed>
     */
    private function pick(Meeting $meeting, array $candidates): array
    {
        $model = (string) config('volgjeraad.ai.default_summary_model');
        $promptVersion = PromptRepository::version();

        $input = json_encode([
            'meeting' => [
                'name' => $meeting->name,
                'starts_at' => CarbonImmutable::instance($meeting->starts_at)->toIso8601String(),
                'type' => $meeting->type->value,
            ],
            'candidates' => array_map(fn (VideoCandidate $c): array => $c->toArray(), $candidates),
        ], JSON_UNESCAPED_UNICODE);

        $agent = new VideoMatchAgent($model, $promptVersion);
        $response = $agent->prompt($input, provider: Lab::OpenAI, model: $model);

        return $response->structured ?? [];
    }

    /**
     * @param  array<int, VideoCandidate>  $candidates
     */
    private function store(
        Meeting $meeting,
        VideoStatus $status,
        array $candidates,
        ?string $videoId = null,
        ?int $confidence = null,
        string $reason = '',
    ): MeetingVideo {
        // Verse query (niet de mogelijk gecachete relatie) zodat attempts correct optelt
        // bij herhaalde aanroepen op hetzelfde in-memory Meeting-object.
        $existing = MeetingVideo::where('meeting_id', $meeting->id)->first();
        $confirmed = $status === VideoStatus::Matched;

        return MeetingVideo::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'youtube_video_id' => $videoId,
                'video_url' => $videoId !== null ? "https://www.youtube.com/watch?v={$videoId}" : null,
                'match_confidence' => $confidence,
                'match_reason' => $reason !== '' ? $reason : null,
                'candidates' => array_map(fn (VideoCandidate $c): array => $c->toArray(), $candidates),
                'status' => $status->value,
                'confirmed_at' => $confirmed ? now() : ($existing?->confirmed_at),
                // Alleen de zoek/match-teller; het transcript-retrybudget blijft onaangeroerd.
                'match_attempts' => ($existing?->match_attempts ?? 0) + 1,
                'last_attempt_at' => now(),
            ],
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FindMeetingVideoTest`
Expected: PASS (alle zes tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Videos/FindMeetingVideo.php tests/Feature/Videos/FindMeetingVideoTest.php
git commit -m "feat: FindMeetingVideo met confidence-gate en video_id-validatie"
```

---

## Task 12: DispatchMeetingSummariesIfReady — lifecycle-gate (wachten vóór review)

> **Lifecycle-besluit (review #114 BLOCKER).** De vergadering-samenvatting wordt **pas** gedispatcht zodra (a) alle media binnen is én (b) de transcript-resolutie klaar is — status `Transcribed`, of transcript definitief opgegeven (geen video / `max_transcript_attempts` bereikt / ná `youtube.transcript_wait_days`). De agendapunt-samenvattingen (PDF) blijven meteen draaien. Deze gating is **additief** bovenop de aparte samenvat-dispatch-fix (die `SummarizeMeetingJob` exact één keer per level laat draaien ná media-ingest); v3 voegt hier de transcript-resolutie-conditie aan toe. Re-entrant: zowel de media-ingest als de video-pijplijn roepen dit aan; pas wanneer beide condities waar zijn dispatcht het, één keer per `SummaryLevel`.
>
> **Bewuste grens:** een `NeedsConfirmation`-video wacht op een mens en wordt niet automatisch opgegeven; de wachttijd-timeout geldt voor de overige onresolved-toestanden (geen video / `NotFound` / `Matched`/`Failed` zonder transcript). `transcriptResolved()` (Task 4) bevat de predikaatlogica.

**Files:**
- Create: `app/Actions/Summaries/DispatchMeetingSummariesIfReady.php`
- Modify: `app/Actions/Ingest/IngestAgendaMediaObjects.php`
- Test: `tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php`:

```php
<?php

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.transcript_wait_days' => 7,
    ]);
});

/** Maak een raadsvergadering met alle media binnen (agendapunt mét attachments_fetched_at). */
function readyCouncilMeeting(string $startsAt = '-1 day'): Meeting
{
    $meeting = Meeting::factory()->council()->summarizable()->create([
        'starts_at' => now()->parse($startsAt),
    ]);
    AgendaItem::factory()->create([
        'meeting_id' => $meeting->id,
        'attachments_fetched_at' => now(),
    ]);

    return $meeting->fresh();
}

test('does not dispatch while the transcript is still unresolved', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-2 days'); // binnen wachttijd, geen video

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting);

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('dispatches one job per level once the transcript is transcribed', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-2 days');
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('dispatches without a transcript once the wait window elapses', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-8 days'); // wachttijd verstreken, nog steeds geen video

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting);

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('a non-council meeting is resolved immediately (no transcript expected)', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->summarizable()->create([
        'type' => 'committee',
        'starts_at' => now()->subDay(),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('waits for all media before dispatching, even with a transcript present', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => now()->subDay()]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('an already summarized meeting is not dispatched again', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-8 days');
    $meeting->update(['summarized_at' => now()]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('a non-summarizable meeting is skipped', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->create([
        'ingest_mode' => 'index',
        'starts_at' => now()->subDays(8),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DispatchMeetingSummariesIfReadyTest`
Expected: FAIL met "Class App\Actions\Summaries\DispatchMeetingSummariesIfReady not found".

- [ ] **Step 3: Schrijf de action**

Maak `app/Actions/Summaries/DispatchMeetingSummariesIfReady.php`:

```php
<?php

namespace App\Actions\Summaries;

use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\Meeting;

class DispatchMeetingSummariesIfReady
{
    /**
     * Dispatcht de meeting-samenvattingen uitsluitend wanneer (a) alle media binnen
     * is én (b) de transcript-resolutie klaar is (transcript binnen, of definitief
     * opgegeven). Re-entrant en idempotent: zowel de media-ingest als de
     * video-pijplijn roepen dit aan; pas wanneer beide condities waar zijn dispatcht
     * het, één keer per SummaryLevel. De exact-één-keer-garantie ná dispatch leeft
     * in de bestaande samenvat-dispatch-fix (die `summarized_at` zet); deze gate
     * voegt daar de transcript-resolutie-conditie aan toe.
     */
    public function handle(Meeting $meeting): void
    {
        if (! $meeting->shouldSummarize()) {
            return;
        }

        // (a) Media compleet: geen agendapunt zonder opgehaalde bijlagen.
        $pendingMedia = $meeting->agendaItems()
            ->whereNull('attachments_fetched_at')
            ->count();
        if ($pendingMedia > 0) {
            return;
        }

        // (b) Transcript-resolutie klaar (transcript binnen of definitief opgegeven).
        if (! $meeting->transcriptResolved()) {
            return;
        }

        // Idempotency: niet opnieuw dispatchen als de meeting al samengevat is.
        if ($meeting->summarized_at !== null) {
            return;
        }

        foreach (SummaryLevel::cases() as $level) {
            dispatch(new SummarizeMeetingJob($meeting->id, $level));
        }
    }
}
```

- [ ] **Step 4: Wire de gate in de media-ingest-dispatch**

In `app/Actions/Ingest/IngestAgendaMediaObjects.php`:

**(a)** Voeg de dependency toe aan de constructor:

```php
    public function __construct(
        private OriClient $client,
        private \App\Actions\Summaries\DispatchMeetingSummariesIfReady $dispatchMeetingSummaries,
    ) {}
```

**(b)** Vervang in `dispatchSummarizeIfComplete()` de meeting-summary-dispatch-lus (de `foreach (SummaryLevel::cases() ...)` die `SummarizeMeetingJob` dispatcht) door een aanroep van de gate. De agendapunt-samenvattingen-lus blijft ongewijzigd staan:

```php
        // Agendapunt-samenvattingen (PDF) draaien meteen — niet afhankelijk van transcript.
        foreach ($meeting->agendaItems as $agendaItem) {
            foreach (SummaryLevel::cases() as $level) {
                dispatch(new SummarizeAgendaItemJob($agendaItem->id, $level));
            }
        }

        // Meeting-samenvatting wacht op transcript-resolutie (wachten vóór review).
        $this->dispatchMeetingSummaries->handle($meeting);
```

> De `import use App\Jobs\SummarizeMeetingJob;` mag blijven staan (geen kwaad), maar wordt niet meer rechtstreeks in deze klasse gebruikt; de gate dispatcht hem. Pint laat ongebruikte imports staan tenzij geconfigureerd — verwijder de regel indien de bestaande Pint-config dat afdwingt.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=DispatchMeetingSummariesIfReadyTest`
Expected: PASS (alle zeven tests).

- [ ] **Step 6: Run de bestaande ingest-suite (regressie)**

Run: `php artisan test --compact --filter=IngestAgendaMediaObjects`
Expected: PASS — agendapunt-dispatch ongewijzigd; meeting-dispatch loopt nu via de gate (voor een net-geingeste meeting zonder video binnen de wachttijd betekent dat: geen meeting-summary tot resolutie). Pas eventueel een bestaande assertie aan die ervan uitging dat `SummarizeMeetingJob` onvoorwaardelijk gedispatcht werd.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Summaries/DispatchMeetingSummariesIfReady.php app/Actions/Ingest/IngestAgendaMediaObjects.php tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php
git commit -m "feat: gate meeting-samenvatting achter transcript-resolutie (wachten vóór review)"
```

---

## Task 13: FetchMeetingTranscript action

> Signatuur: `handle(MeetingVideo $video): void`. Roept `TranscriptProvider::fetch`, slaat het transcript op, zet status `Transcribed`, en roept de lifecycle-gate (`DispatchMeetingSummariesIfReady`, Task 12) aan die — indien de meeting klaar is — de meeting-samenvattingen dispatcht. Lege transcript → status `Failed` mét `transcript_error = 'empty_transcript'`; provider-fout → `Failed` mét de fout. Elke poging telt **`transcript_attempts`** op (review #114 MAJOR: het transcript-retrybudget leeft op een eigen teller, los van `match_attempts`).

**Files:**
- Create: `app/Actions/Videos/FetchMeetingTranscript.php`
- Test: `tests/Feature/Videos/FetchMeetingTranscriptTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Videos/FetchMeetingTranscriptTest.php`:

```php
<?php

use App\Actions\Videos\FetchMeetingTranscript;
use App\Enums\SummaryLevel;
use App\Enums\VideoStatus;
use App\Jobs\SummarizeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Services\Transcript\TranscriptProvider;
use App\Services\Transcript\TranscriptResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.transcript_wait_days' => 7,
    ]);
});

test('stores transcript, sets transcribed status and dispatches re-summarize per level', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->with('dQw4w9WgXcQ', 'nl')
        ->andReturn(new TranscriptResult('Voorzitter: open.', 'supadata:auto', 'nl'));

    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => now()->subDay()]);
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Transcribed);
    expect($video->transcript_text)->toBe('Voorzitter: open.');
    expect($video->transcript_source)->toBe('supadata:auto');
    expect($video->transcript_fetched_at)->not->toBeNull();
    expect($video->transcript_error)->toBeNull();
    expect($video->transcript_attempts)->toBe(1);

    // Transcript binnen → de gate ziet de meeting als resolved en dispatcht per level.
    Bus::assertDispatched(SummarizeMeetingJob::class, fn ($job) => $job->level === SummaryLevel::Standard);
    Bus::assertDispatched(SummarizeMeetingJob::class, fn ($job) => $job->level === SummaryLevel::Simple);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('provider failure sets failed status with error, increments transcript_attempts, dispatches nothing', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andThrow(new RuntimeException('vendor down'));

    // Council + recent → binnen de wachttijd en onder de attempt-limiet, dus nog niet resolved.
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => now()->subDay()]);
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 1,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Failed);
    expect($video->transcript_error)->toContain('vendor down');
    expect($video->transcript_attempts)->toBe(2);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('failed transcript at the attempt limit resolves and dispatches a PDF-only summary', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andThrow(new RuntimeException('vendor down'));

    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => now()->subDay()]);
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 3, // wordt 4 = limiet → definitief opgegeven
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    expect($video->fresh()->transcript_attempts)->toBe(4);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('empty transcript flags empty_transcript and leaves PDF summary untouched within the window', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andReturn(new TranscriptResult('', 'supadata:auto', 'nl'));

    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => now()->subDay()]);
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Failed);
    expect($video->transcript_error)->toBe('empty_transcript');
    expect($video->transcript_text)->toBeNull();
    expect($video->transcript_attempts)->toBe(1);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('no youtube_video_id is a no-op', function (): void {
    Bus::fake();
    $this->mock(TranscriptProvider::class)->shouldReceive('fetch')->never();

    $meeting = Meeting::factory()->council()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NeedsConfirmation->value,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    expect($video->fresh()->status)->toBe(VideoStatus::NeedsConfirmation);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FetchMeetingTranscriptTest`
Expected: FAIL met "Class App\Actions\Videos\FetchMeetingTranscript not found".

- [ ] **Step 3: Schrijf de action**

Maak `app/Actions/Videos/FetchMeetingTranscript.php`:

```php
<?php

namespace App\Actions\Videos;

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Enums\VideoStatus;
use App\Models\MeetingVideo;
use App\Services\Transcript\TranscriptProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchMeetingTranscript
{
    public function __construct(
        private TranscriptProvider $transcriptProvider,
        private DispatchMeetingSummariesIfReady $dispatchMeetingSummaries,
    ) {}

    public function handle(MeetingVideo $video): void
    {
        if ($video->youtube_video_id === null) {
            return;
        }

        try {
            $result = $this->transcriptProvider->fetch($video->youtube_video_id, 'nl');
        } catch (Throwable $e) {
            Log::warning('fetch_meeting_transcript failed', [
                'meeting_video_id' => $video->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $video->update([
                'status' => VideoStatus::Failed->value,
                'transcript_error' => $e->getMessage(),
                'transcript_attempts' => $video->transcript_attempts + 1,
                'last_attempt_at' => now(),
            ]);
            // Mogelijk definitief opgegeven (attempt-limiet) → laat de gate beslissen.
            $this->dispatchMeetingSummaries->handle($video->meeting);

            return;
        }

        if (trim($result->text) === '') {
            $video->update([
                'status' => VideoStatus::Failed->value,
                'transcript_error' => 'empty_transcript',
                'transcript_attempts' => $video->transcript_attempts + 1,
                'last_attempt_at' => now(),
            ]);
            $this->dispatchMeetingSummaries->handle($video->meeting);

            return;
        }

        $video->update([
            'transcript_text' => $result->text,
            'transcript_source' => $result->source,
            'transcript_error' => null,
            'transcript_fetched_at' => now(),
            'status' => VideoStatus::Transcribed->value,
            'transcript_attempts' => $video->transcript_attempts + 1,
            'last_attempt_at' => now(),
        ]);

        // Transcript binnen → resolutie klaar → meeting-samenvattingen (mét transcript).
        $this->dispatchMeetingSummaries->handle($video->meeting);
    }
}
```

> `$video->meeting` wordt vers ge-lazy-load (de `MeetingVideo` had de relatie niet geladen), en `transcriptResolved()` leest daarop de net-geüpdatete `video`-rij. Daardoor ziet de gate de actuele status.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FetchMeetingTranscriptTest`
Expected: PASS (alle vijf tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Videos/FetchMeetingTranscript.php tests/Feature/Videos/FetchMeetingTranscriptTest.php
git commit -m "feat: FetchMeetingTranscript met transcript_attempts en lifecycle-gate"
```

---

## Task 14: ProcessMeetingVideoJob (per-meeting werk + rate limiting)

> **Review #114 MAJOR + #113 MAJOR 8.** Eén job per meeting met `RateLimited('youtube')` + `ThrottlesExceptions`. Beslissingsboom op de **eigen** transcript-teller: bekende video (`Matched`, óf `Failed` mét `youtube_video_id`) met `transcript_attempts < max_transcript_attempts` → (re)transcribe; `NeedsConfirmation` → wacht op mens (maar evalueer alsnog de gate i.v.m. de wachttijd-timeout); `Failed` op de limiet → opgegeven, evalueer de gate; anders zoeken via `FindMeetingVideo`, en bij directe match meteen transcriben. Aan het einde van elke niet-fetch-tak roept de job de lifecycle-gate aan, zodat een uitgelopen wachttijd alsnog de PDF-only-samenvatting dispatcht.
>
> **Taakvolgorde (review #114 MINOR):** deze job wordt vóór de bevestigingsflow (Task 15) gemaakt, want `ConfirmMeetingVideo` importeert `ProcessMeetingVideoJob`. Daarmee zijn de taken lineair uitvoerbaar.

**Files:**
- Create: `app/Jobs/ProcessMeetingVideoJob.php`
- Modify: `app/Providers/AppServiceProvider.php` (youtube-limiter)
- Test: `tests/Feature/Videos/ProcessMeetingVideoJobTest.php`

- [ ] **Step 1: Registreer de youtube-rate-limiter**

In `app/Providers/AppServiceProvider.php`, in de `boot()`-methode, ná de bestaande `ori`-limiter, voeg toe:

```php
        RateLimiter::for('youtube', fn () => Limit::perMinute(30));
```

- [ ] **Step 2: Schrijf de failing test**

Maak `tests/Feature/Videos/ProcessMeetingVideoJobTest.php`:

```php
<?php

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['volgjeraad.youtube.max_transcript_attempts' => 4]);
});

test('matched video goes straight to transcript fetch', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with(\Mockery::on(fn ($v) => $v->id === $video->id));
    $dispatch = $this->mock(DispatchMeetingSummariesIfReady::class);
    $dispatch->shouldReceive('handle')->never();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])->handle($this->app->make(FindMeetingVideo::class), $fetch, $dispatch);
});

test('failed transcript with a known video under the limit retries the fetch', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 1,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with(\Mockery::on(fn ($v) => $v->id === $video->id));
    $dispatch = $this->mock(DispatchMeetingSummariesIfReady::class);
    $dispatch->shouldReceive('handle')->never();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])->handle($this->app->make(FindMeetingVideo::class), $fetch, $dispatch);
});

test('failed transcript at the attempt limit is skipped and re-evaluates the gate', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 4,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();
    $dispatch = $this->mock(DispatchMeetingSummariesIfReady::class);
    $dispatch->shouldReceive('handle')->once()->with(\Mockery::on(fn ($m) => $m->id === $meeting->id));

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])
        ->handle($this->app->make(FindMeetingVideo::class), $this->app->make(FetchMeetingTranscript::class), $dispatch);
});

test('needs_confirmation video awaits a human but still re-evaluates the gate', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NeedsConfirmation->value,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();
    $dispatch = $this->mock(DispatchMeetingSummariesIfReady::class);
    $dispatch->shouldReceive('handle')->once();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])
        ->handle($this->app->make(FindMeetingVideo::class), $this->app->make(FetchMeetingTranscript::class), $dispatch);
});

test('meeting without a video searches, and a fresh match is transcribed in the same run', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    $matched = new MeetingVideo([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
    ]);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->once()->with(\Mockery::on(fn ($m) => $m->id === $meeting->id))->andReturn($matched);
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with($matched);
    $dispatch = $this->mock(DispatchMeetingSummariesIfReady::class);
    $dispatch->shouldReceive('handle')->never();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])->handle($find, $fetch, $dispatch);
});

test('not_found video re-searches and re-evaluates the gate when no match is found', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NotFound->value,
        'match_attempts' => 1,
    ]);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->once()->andReturnNull();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();
    $dispatch = $this->mock(DispatchMeetingSummariesIfReady::class);
    $dispatch->shouldReceive('handle')->once();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])
        ->handle($find, $this->app->make(FetchMeetingTranscript::class), $dispatch);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProcessMeetingVideoJobTest`
Expected: FAIL met "Class App\Jobs\ProcessMeetingVideoJob not found".

- [ ] **Step 4: Schrijf de job**

Maak `app/Jobs/ProcessMeetingVideoJob.php`:

```php
<?php

namespace App\Jobs;

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

class ProcessMeetingVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId) {}

    public function handle(
        FindMeetingVideo $find,
        FetchMeetingTranscript $fetch,
        DispatchMeetingSummariesIfReady $dispatchSummaries,
    ): void {
        $meeting = Meeting::with('video')->findOrFail($this->meetingId);
        $video = $meeting->video;
        $maxAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');

        // Bekende video (matched, of failed-transcript mét video) met resterend
        // transcript-budget → (re)transcribe. FetchMeetingTranscript roept zelf de gate aan.
        if ($video !== null
            && $video->youtube_video_id !== null
            && in_array($video->status, [VideoStatus::Matched, VideoStatus::Failed], true)
            && $video->transcript_attempts < $maxAttempts) {
            $fetch->handle($video);

            return;
        }

        // Wacht op menselijke bevestiging; mogelijk is de wachttijd verstreken → gate.
        if ($video?->status === VideoStatus::NeedsConfirmation) {
            $dispatchSummaries->handle($meeting);

            return;
        }

        // Transcript definitief opgegeven (attempt-limiet) → gate (PDF-only indien klaar).
        if ($video?->status === VideoStatus::Failed && $video->transcript_attempts >= $maxAttempts) {
            $dispatchSummaries->handle($meeting);

            return;
        }

        // Nog geen bruikbare match (geen video / not_found / pending) → zoeken.
        $matched = $find->handle($meeting);
        if ($matched?->status === VideoStatus::Matched) {
            $fetch->handle($matched);

            return;
        }

        // Geen match → mogelijk wachttijd verstreken → gate (PDF-only indien klaar).
        $dispatchSummaries->handle($meeting);
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('youtube'),
            (new ThrottlesExceptions(5, 300))->backoff(60)->by('youtube'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void {}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=ProcessMeetingVideoJobTest`
Expected: PASS (alle zes tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Jobs/ProcessMeetingVideoJob.php app/Providers/AppServiceProvider.php tests/Feature/Videos/ProcessMeetingVideoJobTest.php
git commit -m "feat: ProcessMeetingVideoJob met transcript_attempts-retry en youtube rate limiting"
```

---

## Task 15: Bevestigingsflow — ConfirmMeetingVideo + admin-surface

> **Review #113 BLOCKER 4.** `needs_confirmation`-video's krijgen een admin-surface in de bestaande admin-groep (`auth` + `is_admin`, conform `routes/web.php`). De reviewer kiest een kandidaat; `ConfirmMeetingVideo` zet `status=Matched` + `confirmed_at`, valideert dat het gekozen id in `candidates` zit, en dispatcht `ProcessMeetingVideoJob` (Task 14) die de transcript-fetch uitvoert. `ProcessMeetingVideoJob` bestaat al (Task 14 staat hiervóór), dus deze taak is lineair uitvoerbaar.

**Files:**
- Create: `app/Actions/Videos/ConfirmMeetingVideo.php`
- Create: `app/Http/Controllers/Admin/VideoReviewController.php`
- Create: `resources/js/pages/admin/Videos/Index.tsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/Videos/ConfirmMeetingVideoTest.php`
- Test: `tests/Feature/Admin/VideoReviewControllerTest.php`

- [ ] **Step 1: Schrijf de failing test voor de action**

Maak `tests/Feature/Videos/ConfirmMeetingVideoTest.php`:

```php
<?php

use App\Actions\Videos\ConfirmMeetingVideo;
use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('confirming a candidate matches the video, sets confirmed_at and dispatches processing', function (): void {
    Bus::fake();

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [
            ['videoId' => 'aaa11111111', 'title' => 'Raad A', 'publishedAt' => '2026-06-04T19:00:00+00:00'],
            ['videoId' => 'bbb22222222', 'title' => 'Raad B', 'publishedAt' => '2026-06-04T20:00:00+00:00'],
        ],
    ]);

    $confirmed = app(ConfirmMeetingVideo::class)->handle($video, 'bbb22222222');

    expect($confirmed->status)->toBe(VideoStatus::Matched);
    expect($confirmed->youtube_video_id)->toBe('bbb22222222');
    expect($confirmed->confirmed_at)->not->toBeNull();
    expect($confirmed->video_url)->toBe('https://www.youtube.com/watch?v=bbb22222222');

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('confirming a video_id outside the candidate list is rejected', function (): void {
    Bus::fake();

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'aaa11111111', 'title' => 'Raad A', 'publishedAt' => '2026-06-04T19:00:00+00:00']],
    ]);

    expect(fn () => app(ConfirmMeetingVideo::class)->handle($video, 'zzz99999999'))
        ->toThrow(InvalidArgumentException::class);

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ConfirmMeetingVideoTest`
Expected: FAIL met "Class App\Actions\Videos\ConfirmMeetingVideo not found".

- [ ] **Step 3: Schrijf de action**

Maak `app/Actions/Videos/ConfirmMeetingVideo.php`:

```php
<?php

namespace App\Actions\Videos;

use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\MeetingVideo;
use InvalidArgumentException;

class ConfirmMeetingVideo
{
    public function handle(MeetingVideo $video, string $videoId): MeetingVideo
    {
        $candidateIds = array_map(
            fn (array $candidate): string => (string) ($candidate['videoId'] ?? ''),
            $video->candidates ?? [],
        );

        if (! in_array($videoId, $candidateIds, true)) {
            throw new InvalidArgumentException('Gekozen video_id zit niet in de kandidatenlijst.');
        }

        $video->update([
            'youtube_video_id' => $videoId,
            'video_url' => "https://www.youtube.com/watch?v={$videoId}",
            'status' => VideoStatus::Matched->value,
            'confirmed_at' => now(),
            'match_reason' => 'Handmatig bevestigd door reviewer.',
        ]);

        ProcessMeetingVideoJob::dispatch($video->meeting_id);

        return $video->refresh();
    }
}
```

- [ ] **Step 4: Run test to verify the action passes**

Run: `php artisan test --compact --filter=ConfirmMeetingVideoTest`
Expected: PASS (beide tests).

- [ ] **Step 5: Schrijf de failing test voor de controller**

Maak `tests/Feature/Admin/VideoReviewControllerTest.php`:

```php
<?php

use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('index lists needs_confirmation videos for an admin', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = Meeting::factory()->summarizable()->create(['name' => 'Raadsvergadering 4 juni']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'aaa11111111', 'title' => 'Raad A', 'publishedAt' => '2026-06-04T19:00:00+00:00']],
    ]);

    $this->actingAs($admin)
        ->get('/admin/videos')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Videos/Index')
            ->has('videos', 1)
            ->where('videos.0.meeting.name', 'Raadsvergadering 4 juni')
            ->has('videos.0.candidates', 1));
});

test('non-admin cannot access the video review queue', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/videos')->assertForbidden();
});

test('confirm endpoint matches the chosen candidate and dispatches processing', function (): void {
    Bus::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'bbb22222222', 'title' => 'Raad B', 'publishedAt' => '2026-06-04T20:00:00+00:00']],
    ]);

    $this->actingAs($admin)
        ->post("/admin/videos/{$video->id}/confirm", ['video_id' => 'bbb22222222'])
        ->assertRedirect('/admin/videos');

    expect($video->fresh()->status)->toBe(VideoStatus::Matched);
    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('confirm rejects a video_id outside the candidate list', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'bbb22222222', 'title' => 'Raad B', 'publishedAt' => '2026-06-04T20:00:00+00:00']],
    ]);

    $this->actingAs($admin)
        ->post("/admin/videos/{$video->id}/confirm", ['video_id' => 'zzz99999999'])
        ->assertSessionHasErrors('video_id');

    expect($video->fresh()->status)->toBe(VideoStatus::NeedsConfirmation);
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --compact --filter=VideoReviewControllerTest`
Expected: FAIL — route `/admin/videos` bestaat nog niet (404/NotFoundHttpException).

- [ ] **Step 7: Schrijf de controller**

Maak `app/Http/Controllers/Admin/VideoReviewController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Videos\ConfirmMeetingVideo;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\MeetingVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VideoReviewController extends Controller
{
    public function index(): Response
    {
        $videos = MeetingVideo::query()
            ->where('status', VideoStatus::NeedsConfirmation->value)
            ->with('meeting.municipality')
            ->get()
            ->map(fn (MeetingVideo $video): array => [
                'id' => $video->id,
                'match_confidence' => $video->match_confidence,
                'match_reason' => $video->match_reason,
                'candidates' => $video->candidates ?? [],
                'meeting' => $video->meeting ? [
                    'id' => $video->meeting->id,
                    'name' => $video->meeting->name,
                    'starts_at' => $video->meeting->starts_at?->toIso8601String(),
                    'municipality' => $video->meeting->municipality->only('id', 'name', 'slug'),
                ] : null,
            ]);

        return Inertia::render('admin/Videos/Index', [
            'videos' => $videos,
        ]);
    }

    public function confirm(Request $request, MeetingVideo $video, ConfirmMeetingVideo $action): RedirectResponse
    {
        $validated = $request->validate([
            'video_id' => ['required', 'string'],
        ]);

        try {
            $action->handle($video, $validated['video_id']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['video_id' => $e->getMessage()]);
        }

        return redirect('/admin/videos')->with('success', 'Video bevestigd; transcript wordt opgehaald.');
    }
}
```

- [ ] **Step 8: Schrijf de minimale Inertia-page**

Maak `resources/js/pages/admin/Videos/Index.tsx`:

```tsx
import AdminLayout from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';

interface Candidate {
    videoId: string;
    title: string;
    publishedAt: string;
}

interface VideoRow {
    id: number;
    match_confidence: number | null;
    match_reason: string | null;
    candidates: Candidate[];
    meeting: {
        id: number;
        name: string;
        starts_at: string | null;
        municipality: { id: number; name: string; slug: string };
    } | null;
}

interface Props {
    videos: VideoRow[];
}

export default function Index({ videos }: Props): JSX.Element {
    function confirm(videoRowId: number, videoId: string): void {
        router.post(`/admin/videos/${videoRowId}/confirm`, { video_id: videoId });
    }

    return (
        <AdminLayout>
            <div className="space-y-8">
                <h1 className="text-2xl font-bold">Video's bevestigen</h1>

                {videos.length === 0 && <p className="text-sm text-muted-foreground">Niets te bevestigen.</p>}

                <ul className="space-y-6">
                    {videos.map((video) => (
                        <li key={video.id} className="rounded-lg border border-border p-4">
                            <p className="font-semibold">{video.meeting?.name ?? 'Onbekende vergadering'}</p>
                            <p className="text-xs text-muted-foreground">
                                {video.meeting?.municipality.name} · confidence {video.match_confidence ?? '—'}
                            </p>
                            {video.match_reason && <p className="mt-1 text-sm">{video.match_reason}</p>}

                            <ul className="mt-3 space-y-2">
                                {video.candidates.map((candidate) => (
                                    <li key={candidate.videoId} className="flex items-center justify-between gap-4">
                                        <span className="text-sm">
                                            {candidate.title} ({candidate.publishedAt})
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => confirm(video.id, candidate.videoId)}
                                            className="rounded-md bg-primary px-3 py-1 text-sm text-primary-foreground hover:opacity-90"
                                        >
                                            Bevestig
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </div>
        </AdminLayout>
    );
}
```

- [ ] **Step 9: Voeg de routes toe**

In `routes/web.php`, voeg bovenaan bij de bestaande admin-imports toe:

```php
use App\Http\Controllers\Admin\VideoReviewController;
```

En binnen de bestaande `Route::middleware(['auth', 'is_admin'])->prefix('admin')->name('admin.')->group(...)`, ná de `subscribers`-routes, voeg toe:

```php
    Route::get('/videos', [VideoReviewController::class, 'index'])->name('videos.index');
    Route::post('/videos/{video}/confirm', [VideoReviewController::class, 'confirm'])->name('videos.confirm');
```

- [ ] **Step 10: Run test to verify it passes**

Run: `php artisan test --compact --filter=VideoReviewControllerTest`
Expected: PASS (alle vier tests).

- [ ] **Step 11: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Videos/ConfirmMeetingVideo.php app/Http/Controllers/Admin/VideoReviewController.php resources/js/pages/admin/Videos/Index.tsx routes/web.php tests/Feature/Videos/ConfirmMeetingVideoTest.php tests/Feature/Admin/VideoReviewControllerTest.php
git commit -m "feat: bevestigingsflow voor needs_confirmation video's"
```

---

## Task 16: MatchMeetingVideosJob orkestratie + dagelijkse schedule

> **Review #114 MAJOR (scheduler-scope) + #113 MAJOR 8.** Dagelijkse orkestrator selecteert in aanmerking komende council-meetings en dispatcht via `chunkById()` per meeting een `ProcessMeetingVideoJob`. Eligibility sluit `Transcribed` (klaar) en `NeedsConfirmation` (wacht op mens) uit. Scope-precisie: `NotFound`/geen-video respecteert `max_find_days`; maar `Matched` en `Failed` mét `youtube_video_id` blijven in scope tot `transcript_attempts` de limiet bereikt — **ook ná `max_find_days`** — zodat een tijdelijke transcript-storing rond dag 14 een bekende match niet permanent laat liggen.

**Files:**
- Create: `app/Jobs/MatchMeetingVideosJob.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Videos/MatchMeetingVideosJobTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Videos/MatchMeetingVideosJobTest.php`:

```php
<?php

use App\Enums\VideoStatus;
use App\Jobs\MatchMeetingVideosJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_find_days' => 14,
        'volgjeraad.youtube.max_transcript_attempts' => 4,
    ]);
    $this->travelTo('2026-06-10 06:30:00');
});

afterEach(function (): void {
    $this->travelBack();
});

test('dispatches a process job for an eligible past council meeting', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('meeting older than max_find_days with no known video is skipped', function (): void {
    Bus::fake();
    Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-05-01 19:00:00']);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('old meeting with a failed transcript on a known video under the limit stays in scope', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-05-01 19:00:00']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 2, // onder de limiet
    ]);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('old meeting with a known video at the transcript limit is dropped from scope', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-05-01 19:00:00']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 4, // limiet bereikt
    ]);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('future meeting is skipped', function (): void {
    Bus::fake();
    Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-06-20 19:00:00']);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('non-council meeting is skipped', function (): void {
    Bus::fake();
    Meeting::factory()->summarizable()->create(['type' => 'committee', 'starts_at' => '2026-06-04 19:00:00']);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('already transcribed meeting is skipped', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('needs_confirmation meeting is skipped (awaits human)', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
    ]);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('matched-but-not-yet-transcribed meeting stays in scope', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
    ]);

    app(MatchMeetingVideosJob::class)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MatchMeetingVideosJobTest`
Expected: FAIL met "Class App\Jobs\MatchMeetingVideosJob not found".

- [ ] **Step 3: Schrijf de job**

Maak `app/Jobs/MatchMeetingVideosJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Meeting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MatchMeetingVideosJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        $findCutoff = CarbonImmutable::now()->subDays((int) config('volgjeraad.youtube.max_find_days'));
        $maxTranscriptAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');

        Meeting::query()
            ->council()
            ->summarizable()
            ->where('starts_at', '<', now())
            // Klaar (transcribed) of wacht-op-mens (needs_confirmation) → buiten scope.
            ->whereDoesntHave('video', function (Builder $query): void {
                $query->whereIn('status', [
                    VideoStatus::Transcribed->value,
                    VideoStatus::NeedsConfirmation->value,
                ]);
            })
            ->where(function (Builder $query) use ($findCutoff, $maxTranscriptAttempts): void {
                // (1) Binnen het zoekvenster: re-search van NotFound / geen-video.
                $query->where('starts_at', '>=', $findCutoff)
                    // (2) Of: bekende video met resterend transcript-budget — ook ná max_find_days.
                    ->orWhereHas('video', function (Builder $video) use ($maxTranscriptAttempts): void {
                        $video->whereNotNull('youtube_video_id')
                            ->whereIn('status', [VideoStatus::Matched->value, VideoStatus::Failed->value])
                            ->where('transcript_attempts', '<', $maxTranscriptAttempts);
                    });
            })
            ->chunkById(100, function (Collection $meetings): void {
                foreach ($meetings as $meeting) {
                    ProcessMeetingVideoJob::dispatch($meeting->id);
                }
            });
    }

    public function failed(Throwable $exception): void {}
}
```

- [ ] **Step 4: Voeg de schedule toe**

In `routes/console.php`, voeg bovenaan bij de bestaande `use`-statements toe:

```php
use App\Jobs\MatchMeetingVideosJob;
```

En ná het bestaande `volgjeraad:daily-ingest`-blok, voeg toe:

```php
// YouTube-transcript: dagelijks video's matchen en transcripts ophalen
Schedule::job(new MatchMeetingVideosJob)
    ->dailyAt('06:30')
    ->name('volgjeraad:daily-video-match')
    ->withoutOverlapping();
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=MatchMeetingVideosJobTest`
Expected: PASS (alle negen tests).

- [ ] **Step 6: Verifieer de schedule-registratie**

Run: `php artisan schedule:list`
Expected: bevat een regel met `volgjeraad:daily-video-match` om 06:30.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Jobs/MatchMeetingVideosJob.php routes/console.php tests/Feature/Videos/MatchMeetingVideosJobTest.php
git commit -m "feat: MatchMeetingVideosJob met scope-precisie voor transcript-retries + dagelijkse schedule"
```

---

## Task 17: youtube_channel_id per gemeente configureren

> **Review #113 MAJOR 5.** Zonder `settings.youtube_channel_id` retourneert `FindMeetingVideo` `null` (en logt een waarschuwing, Task 11). Deze taak geeft een idempotente artisan-command om het kanaal per gemeente te zetten, plus een verifieer-stap voor Brummen.

**Files:**
- Create: `app/Console/Commands/SetMunicipalityYouTubeChannelCommand.php`
- Test: `tests/Feature/Console/SetMunicipalityYouTubeChannelCommandTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Console/SetMunicipalityYouTubeChannelCommandTest.php`:

```php
<?php

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command sets the youtube channel id and preserves other settings', function (): void {
    $municipality = Municipality::factory()->create([
        'slug' => 'brummen',
        'settings' => ['existing_key' => 'behouden'],
    ]);

    $this->artisan('volgjeraad:set-youtube-channel', [
        'municipality' => 'brummen',
        'channel_id' => 'UC_brummen_kanaal',
    ])->assertExitCode(0);

    $settings = $municipality->fresh()->settings;
    expect($settings['youtube_channel_id'])->toBe('UC_brummen_kanaal');
    expect($settings['existing_key'])->toBe('behouden');
});

test('command fails clearly for an unknown municipality', function (): void {
    $this->artisan('volgjeraad:set-youtube-channel', [
        'municipality' => 'bestaat-niet',
        'channel_id' => 'UC_x',
    ])->assertExitCode(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SetMunicipalityYouTubeChannelCommandTest`
Expected: FAIL — command `volgjeraad:set-youtube-channel` bestaat nog niet.

- [ ] **Step 3: Schrijf de command**

Maak `app/Console/Commands/SetMunicipalityYouTubeChannelCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use Illuminate\Console\Command;

class SetMunicipalityYouTubeChannelCommand extends Command
{
    protected $signature = 'volgjeraad:set-youtube-channel {municipality : slug of ori_index} {channel_id}';

    protected $description = 'Zet het YouTube-kanaal-id in de settings van een gemeente';

    public function handle(): int
    {
        $key = (string) $this->argument('municipality');

        $municipality = Municipality::query()
            ->where('slug', $key)
            ->orWhere('ori_index', $key)
            ->first();

        if ($municipality === null) {
            $this->error("Geen gemeente gevonden voor '{$key}'.");

            return self::FAILURE;
        }

        $settings = $municipality->settings ?? [];
        $settings['youtube_channel_id'] = (string) $this->argument('channel_id');
        $municipality->update(['settings' => $settings]);

        $this->info("youtube_channel_id gezet voor {$municipality->name}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SetMunicipalityYouTubeChannelCommandTest`
Expected: PASS (beide tests).

- [ ] **Step 5: Verifieer Brummen (operationele stap)**

> Verifieer eerst het kanaal-id van Brummen (RTV794/VoorstVeluwezoom) op youtube.com — open het kanaal en lees het `UC…`-id uit de kanaal-URL (`youtube.com/channel/UC…`). Controleer ook dat hun titels betrouwbaar "raadsvergadering" + datum bevatten (spec §10). Zet het daarna:

Run: `php artisan volgjeraad:set-youtube-channel brummen UC<echte-id-van-brummen>`
Expected: "youtube_channel_id gezet voor Brummen."

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/SetMunicipalityYouTubeChannelCommand.php tests/Feature/Console/SetMunicipalityYouTubeChannelCommandTest.php
git commit -m "feat: command om youtube_channel_id per gemeente te zetten"
```

---

## Task 18: GenerateMeetingSummary — transcript als extra bron

> **Lifecycle (review #114 BLOCKER).** Dankzij de gating (Task 12) draait `GenerateMeetingSummary` voor een meeting **één keer**, op het moment dat de transcript-resolutie klaar is — mét transcript indien beschikbaar, anders op de PDF-bronnen. Daarmee vervalt het v2-probleem "bestaande Approved/Published vervangen": de non-draft-skip- en draft-vervangingslogica van v2-Task-17 is **verwijderd**. `GenerateMeetingSummary` houdt zijn eenvoudige idempotency (return bestaande summary per (meeting, level, language)). Wat blijft uit review #113 BLOCKER 3: het transcript komt als **afzonderlijk-begrensd blok** in de AI-input (eigen budget `ai.max_transcript_chars`) zodat het nooit achter een lange agenda wegvalt, en de `source_hash` wordt over de daadwerkelijke AI-input berekend.
>
> **Acceptatiecriteria die groen moeten blijven:**
> 1. `SummarizeMeetingJob` dispatcht `ComposeNewsletterJob` nog steeds pas wanneer er per `SummaryLevel` exact één summary bestaat én er nog geen newsletter is. Deze taak raakt die job niet.
> 2. De "exact één summary per (meeting, level, language)"-invariant blijft: de eenvoudige per-level-idempotency geeft een bestaande summary terug i.p.v. een tweede rij te maken.
> 3. Gedrag rond `meeting->summarized_at` en nieuwsbrief-dispatch blijft ongewijzigd: deze taak schrijft die velden niet.
> 4. De bestaande summaries-suite (`php artisan test --filter=Summar`) blijft groen — voor een meeting zonder transcript is de uitkomst identiek aan vóór deze taak (alleen de bronopbouw is geherstructureerd in blokken, met dezelfde inhoud wanneer er geen transcript is).

**Files:**
- Modify: `app/Actions/Summaries/GenerateMeetingSummary.php`
- Test: `tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php`

Doel: wanneer `meeting->video->transcript_text` aanwezig is, wordt het transcript als afzonderlijk-begrensd blok aan de bron-tekst toegevoegd; de `source_hash` wordt over de daadwerkelijke AI-input berekend. De idempotency-vroege-return (return bestaande summary per level/language) blijft ongewijzigd bovenaan `handle()` staan.

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php`:

```php
<?php

use App\Actions\Summaries\GenerateMeetingSummary;
use App\Ai\Agents\MeetingSummaryAgent;
use App\Enums\SummaryLevel;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMeetingWithAgendaText(string $text = 'Besluit: het wijzigingsplan is vastgesteld.'): Meeting
{
    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id]);
    MediaObject::factory()->withText()->create([
        'agenda_item_id' => $item->id,
        'md_text' => $text,
        'has_text' => true,
    ]);

    return $meeting->fresh();
}

test('a meeting with a transcript gets a different source hash and uses the transcript-aware path', function (): void {
    MeetingSummaryAgent::fake([
        ['title' => 'PDF', 'body' => 'Alleen PDF.', 'impact_note' => 'x', 'confidence' => 70, 'flags' => []],
        ['title' => 'Met transcript', 'body' => 'PDF plus debat.', 'impact_note' => 'y', 'confidence' => 80, 'flags' => []],
    ]);

    $plain = makeMeetingWithAgendaText();
    $withTranscript = makeMeetingWithAgendaText();
    MeetingVideo::factory()->create([
        'meeting_id' => $withTranscript->id,
        'transcript_text' => 'Raadslid A: ik dien een motie in over duurzaamheid.',
        'transcript_source' => 'supadata:auto',
        'status' => 'transcribed',
    ]);

    $action = app(GenerateMeetingSummary::class);
    $a = $action->handle($plain, SummaryLevel::Standard);
    $b = $action->handle($withTranscript->fresh(), SummaryLevel::Standard);

    expect($a->title)->toBe('PDF');
    expect($b->title)->toBe('Met transcript');
    expect($b->source_hash)->not->toBe($a->source_hash);
});

test('same source returns the existing summary without a second AI call', function (): void {
    MeetingSummaryAgent::fake([
        ['title' => 'Eenmalig', 'body' => 'Body.', 'impact_note' => 'x', 'confidence' => 75, 'flags' => []],
    ]);

    $meeting = makeMeetingWithAgendaText();
    $action = app(GenerateMeetingSummary::class);

    $first = $action->handle($meeting, SummaryLevel::Standard);
    $second = $action->handle($meeting->fresh(), SummaryLevel::Standard);

    expect($second->id)->toBe($first->id);
    expect(Summary::count())->toBe(1);
});

test('meeting without a transcript summarizes from the PDF source only', function (): void {
    MeetingSummaryAgent::fake([
        ['title' => 'PDF', 'body' => 'Alleen PDF.', 'impact_note' => 'x', 'confidence' => 70, 'flags' => []],
    ]);

    $meeting = makeMeetingWithAgendaText();
    $summary = app(GenerateMeetingSummary::class)->handle($meeting, SummaryLevel::Standard);

    expect($summary->title)->toBe('PDF');
    expect($summary->flags)->not->toContain('source_text_missing');
});

test('a very long agenda still keeps the transcript block and flags truncation', function (): void {
    config(['volgjeraad.ai.max_source_chars' => 50, 'volgjeraad.ai.max_transcript_chars' => 60000]);
    MeetingSummaryAgent::fake([
        ['title' => 'Lange agenda + transcript', 'body' => 'Body.', 'impact_note' => 'x', 'confidence' => 80, 'flags' => []],
    ]);

    $meeting = makeMeetingWithAgendaText(str_repeat('Zeer lange agenda-tekst. ', 100));
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'transcript_text' => 'Raadslid A: ik dien een motie in.',
        'transcript_source' => 'supadata:auto',
        'status' => 'transcribed',
    ]);

    $summary = app(GenerateMeetingSummary::class)->handle($meeting->fresh(), SummaryLevel::Standard);

    // Het transcript krijgt zijn eigen budget en valt dus niet weg achter de lange agenda;
    // de hash verschilt van een no-transcript variant, en truncatie is geflagd.
    expect($summary->flags)->toContain('source_truncated');

    $sameWithoutTranscript = makeMeetingWithAgendaText(str_repeat('Zeer lange agenda-tekst. ', 100));
    MeetingSummaryAgent::fake([
        ['title' => 'Lange agenda', 'body' => 'Body.', 'impact_note' => 'x', 'confidence' => 80, 'flags' => []],
    ]);
    $baseline = app(GenerateMeetingSummary::class)->handle($sameWithoutTranscript, SummaryLevel::Standard);

    expect($summary->source_hash)->not->toBe($baseline->source_hash);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateMeetingSummaryTranscriptTest`
Expected: FAIL — zolang de bronopbouw nog geen transcript-blok kent, is `b.source_hash` gelijk aan `a.source_hash` (de transcript-meeting krijgt dezelfde hash als de plain-meeting) en faalt de eerste test.

- [ ] **Step 3: Pas `GenerateMeetingSummary::handle()` aan**

In `app/Actions/Summaries/GenerateMeetingSummary.php`:

**(a)** Laat de bestaande idempotency-vroege-return (huidige regels 26-34, het `$existing` per level dat wordt teruggegeven) **ongewijzigd** staan. De meeting-summary wordt door de gating één keer gegenereerd; een tweede aanroep geeft de bestaande rij terug.

**(b)** Voeg ná `$maxSourceChars = (int) config('volgjeraad.ai.max_source_chars', 24000);` toe:

```php
        $maxTranscriptChars = (int) config('volgjeraad.ai.max_transcript_chars', 60000);
```

**(c)** Vervang de source-text-opbouw + truncatie (huidige regels 42-77, vanaf `// Concat raw agenda texts...` t/m vóór de cost-check) door dit blok, dat de bron uit afzonderlijk-begrensde blokken opbouwt en de transcript-ruimte garandeert:

```php
        // Blok 1: besluitenlijst + agenda-tekst, met eigen budget.
        $agendaText = $meeting->agendaItems()
            ->orderBy('position')
            ->get()
            ->map(fn ($item) => $item->sourceText())
            ->filter(fn ($text) => $text !== '')
            ->implode("\n\n---\n\n");

        $truncated = false;
        if (mb_strlen($agendaText) > $maxSourceChars) {
            $agendaText = mb_substr($agendaText, 0, $maxSourceChars);
            $truncated = true;
        }

        $blocks = [];
        if ($agendaText !== '') {
            $blocks[] = "=== BESLUITENLIJST + AGENDA ===\n\n".$agendaText;
        }

        // Blok 2: transcript (debat), met eigen budget zodat het nooit wegvalt.
        $transcript = $meeting->video?->transcript_text;
        if ($transcript !== null && trim($transcript) !== '') {
            if (mb_strlen($transcript) > $maxTranscriptChars) {
                $transcript = mb_substr($transcript, 0, $maxTranscriptChars);
                $truncated = true;
            }
            $blocks[] = "=== TRANSCRIPT (debat) ===\n\n".$transcript;
        }

        $sourceText = implode("\n\n---\n\n", $blocks);
        $sourceHash = PayloadHasher::hash(['text' => $sourceText]);

        if ($sourceText === '') {
            return Summary::create([
                'summarizable_type' => $meeting->getMorphClass(),
                'summarizable_id' => $meeting->getKey(),
                'municipality_id' => $municipality->id,
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'language' => 'nl',
                'source_hash' => $sourceHash,
                'status' => SummaryStatus::Draft->value,
                'title' => '',
                'body' => '',
                'impact_note' => null,
                'confidence' => 0,
                'flags' => ['source_text_missing'],
                'input_tokens' => 0,
                'output_tokens' => 0,
                'prompt_version' => $promptVersion,
                'model' => $model,
            ]);
        }
```

**(d)** Vervang in de succesvolle `Summary::create([...])`-aanroep (huidige regel ~112) de inline hash door de berekende variabele:

```php
                'source_hash' => $sourceHash,
```

> De `source_truncated`-flag-logica (huidige regels 100-103) blijft ongewijzigd; `$truncated` wordt nu gezet door zowel agenda- als transcript-truncatie. `SummaryStatus` en `Log` zijn al geïmporteerd.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GenerateMeetingSummaryTranscriptTest`
Expected: PASS (alle vier tests).

- [ ] **Step 5: Run de volledige summaries-suite (regressie / acceptatiecriterium 4)**

Run: `php artisan test --compact --filter=Summar`
Expected: PASS — bestaande meeting/agenda-summary-tests blijven groen (zonder transcript is de bron-inhoud identiek; alleen de opbouw is in blokken).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Summaries/GenerateMeetingSummary.php tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php
git commit -m "feat: transcript als afzonderlijk-begrensd bronblok in GenerateMeetingSummary"
```

---

## Slotcontrole

- [ ] **Volledige testsuite**

Run: `php artisan test --compact`
Expected: alle tests groen.

- [ ] **Pint over de hele branch**

Run: `vendor/bin/pint --dirty --format agent`
Expected: geen openstaande stijl-fouten.

- [ ] **Frontend build (de nieuwe Inertia-page)**

Run: `npm run build`
Expected: build slaagt; `resources/js/pages/admin/Videos/Index.tsx` wordt meegenomen.

- [ ] **Lifecycle-rooktest (handmatig, optioneel)**

Controleer het eind-tot-eind-gedrag van "wachten vóór review": een net-geingeste raadsvergadering binnen de wachttijd zonder video krijgt agendapunt-samenvattingen maar **geen** meeting-samenvatting; ná `transcript_wait_days` (of zodra het transcript binnen is) verschijnt de meeting-samenvatting één keer, mét transcript indien beschikbaar.
