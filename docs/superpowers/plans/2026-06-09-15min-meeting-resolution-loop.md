# 15-minuten meeting-resolutie-loop — Implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verwerk meetings elke 15 minuten door één centrale bron-resolutie (transcript óf notule), met begrensde werkdag-rechecks, en toon de verwerkingsstatus in de beheer- en publieke UI.

**Architecture:** Een 15-min `volgjeraad:resolve`-sweep dispatcht per plaatsgevonden, nog-onopgeloste, summarizable meeting de actie `ResolveMeetingSummarySources` — de "brain" die de beslisboom uitvoert: raad+kanaal wacht 24u op video→transcript; anders/daarna een AI-gedetecteerde notule; geen bron na 2 werkdagen → meeting vastgelegd zonder samenvatting. Dezelfde brain wordt event-driven aangeroepen na transcript-/media-binnenkomst. Een afgeleide `MeetingProcessingStatus` voedt beide UI's.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v3 + React 19, Laravel AI (`Laravel\Ai`), MySQL.

**Spec:** `docs/superpowers/specs/2026-06-09-15min-meeting-resolution-loop-design.md`

---

## Bestandsoverzicht

**Nieuw:**
- `database/migrations/XXXX_add_source_resolution_to_meetings.php` — kolommen op `meetings`
- `app/Enums/MeetingProcessingStatus.php` — afgeleide status-enum + labels
- `app/Ai/Agents/NotuleDetectionAgent.php` — AI-agent voor notule-herkenning
- `resources/prompts/notule_detection.v2.md` — prompt voor die agent
- `app/Actions/Summaries/DetectMeetingNotule.php` — roept de agent aan, cachet resultaat
- `app/Actions/Summaries/ResolveMeetingSummarySources.php` — de beslisboom (brain)
- `app/Jobs/ResolveReadyMeetingsJob.php` — 15-min sweep
- Tests onder `tests/Feature/...` en `tests/Unit/...` per taak

**Gewijzigd:**
- `config/volgjeraad.php` — `transcript_wait_days` → `video_wait_hours`; nieuw `notule_recheck_working_days`
- `app/Models/Meeting.php` — casts, `summary_source`-constants, `processingStatus()`, `summaryStatusLabel()`
- `app/Actions/Summaries/DispatchMeetingSummariesIfReady.php` — gate op `summary_source` i.p.v. `transcriptResolved()`
- `app/Actions/Videos/FetchMeetingTranscript.php`, `app/Actions/Ingest/IngestAgendaMediaObjects.php`, `app/Jobs/ProcessMeetingVideoJob.php` — roepen `ResolveMeetingSummarySources` aan i.p.v. de gate direct
- `routes/console.php` — `volgjeraad:match-videos` → `volgjeraad:resolve` (elke 15 min)
- `app/Http/Controllers/Public/MunicipalityController.php` + `MeetingController.php` — publieke status
- `app/Http/Controllers/Admin/*` + React-pagina's — beheer-statusbadge
- `resources/js/pages/Municipality/Show.tsx`, `Meeting/Show.tsx` — publieke status-regel

---

## Fase 1 — Fundament

### Taak 1: Migratie — bron-resolutiekolommen op `meetings`

**Files:**
- Create: `database/migrations/2026_06_09_120000_add_source_resolution_to_meetings.php`
- Test: `tests/Feature/MigrationsTest.php` (bestaat al; draait alle migraties)

- [ ] **Stap 1: Schrijf de migratie**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('summary_source')->nullable()->after('summarized_at');
            $table->dateTime('source_resolved_at')->nullable()->after('summary_source');
            $table->dateTime('notule_detected_at')->nullable()->after('source_resolved_at');
            $table->foreignId('notule_media_object_id')->nullable()->after('notule_detected_at')
                ->constrained('media_objects')->nullOnDelete();
            $table->string('summary_skipped_reason')->nullable()->after('notule_media_object_id');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('notule_media_object_id');
            $table->dropColumn(['summary_source', 'source_resolved_at', 'notule_detected_at', 'summary_skipped_reason']);
        });
    }
};
```

- [ ] **Stap 2: Draai de migratie + bestaande migratietest**

Run: `php artisan migrate && php artisan test --compact --filter=MigrationsTest`
Expected: PASS

- [ ] **Stap 3: Commit**

```bash
git add database/migrations/2026_06_09_120000_add_source_resolution_to_meetings.php
git commit -m "feat: bron-resolutiekolommen op meetings"
```

---

### Taak 2: Config — video-wachttijd & notule-rechecks

**Files:**
- Modify: `config/volgjeraad.php` (`youtube`-blok)

- [ ] **Stap 1: Vervang `transcript_wait_days` en voeg recheck-config toe**

In het `'youtube' => [ ... ]`-blok: verwijder de regel `'transcript_wait_days' => 7,` en voeg toe:

```php
            'video_wait_hours' => 24,
            'notule_recheck_working_days' => 2,
```

- [ ] **Stap 2: Verifieer config laadt**

Run: `php artisan config:show volgjeraad.youtube.video_wait_hours`
Expected: `24`

- [ ] **Stap 3: Commit**

```bash
git add config/volgjeraad.php
git commit -m "feat: video_wait_hours + notule_recheck_working_days config"
```

---

### Taak 3: `MeetingProcessingStatus`-enum

**Files:**
- Create: `app/Enums/MeetingProcessingStatus.php`
- Test: `tests/Unit/Enums/MeetingProcessingStatusTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Enums\MeetingProcessingStatus;

test('each case has a non-empty admin label and public message', function (MeetingProcessingStatus $status): void {
    expect($status->adminLabel())->toBeString()->not->toBe('');
    expect($status->publicMessage())->toBeString();
})->with(MeetingProcessingStatus::cases());

test('no_source explains the missing besluitenlijst publicly', function (): void {
    expect(MeetingProcessingStatus::NoSource->publicMessage())
        ->toContain('besluitenlijst');
});

test('published has no public message (summary is shown instead)', function (): void {
    expect(MeetingProcessingStatus::Published->publicMessage())->toBe('');
});

test('scheduled is hidden from the public list', function (): void {
    expect(MeetingProcessingStatus::Scheduled->isPubliclyVisible())->toBeFalse();
    expect(MeetingProcessingStatus::Processing->isPubliclyVisible())->toBeTrue();
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=MeetingProcessingStatusTest`
Expected: FAIL ("Class MeetingProcessingStatus not found")

- [ ] **Stap 3: Schrijf de enum**

```php
<?php

namespace App\Enums;

enum MeetingProcessingStatus: string
{
    case PreLaunch = 'pre_launch';
    case Scheduled = 'scheduled';
    case AwaitingVideo = 'awaiting_video';
    case Processing = 'processing';
    case AwaitingNotule = 'awaiting_notule';
    case InReview = 'in_review';
    case Published = 'published';
    case NoSource = 'no_source';

    public function adminLabel(): string
    {
        return match ($this) {
            self::PreLaunch => 'Voor livegang — niet samengevat',
            self::Scheduled => 'Gepland',
            self::AwaitingVideo => 'In afwachting van video',
            self::Processing => 'Bezig met verwerken',
            self::AwaitingNotule => 'In afwachting van notule',
            self::InReview => 'In review',
            self::Published => 'Gepubliceerd',
            self::NoSource => 'Geen bron — geen samenvatting',
        };
    }

    public function publicMessage(): string
    {
        return match ($this) {
            self::PreLaunch => 'Deze vergadering vond plaats vóór de livegang en is niet samengevat.',
            self::Scheduled => '',
            self::AwaitingVideo => 'Wordt verwerkt zodra de video beschikbaar is.',
            self::Processing => 'Bezig met verwerken.',
            self::AwaitingNotule => 'Wachten op de besluitenlijst.',
            self::InReview => 'Bezig met verwerken.',
            self::Published => '',
            self::NoSource => 'Geen samenvatting: er is geen besluitenlijst beschikbaar.',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this !== self::Scheduled;
    }
}
```

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=MeetingProcessingStatusTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Enums/MeetingProcessingStatus.php tests/Unit/Enums/MeetingProcessingStatusTest.php
git commit -m "feat: MeetingProcessingStatus enum met labels"
```

---

### Taak 4: `Meeting` — casts, constants & `processingStatus()`

**Files:**
- Modify: `app/Models/Meeting.php`
- Test: `tests/Unit/Models/MeetingProcessingStatusTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Enums\IngestMode;
use App\Enums\MeetingProcessingStatus;
use App\Enums\MeetingType;
use App\Enums\SummaryStatus;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['volgjeraad.youtube.video_wait_hours' => 24]));

function channelMunicipality(): Municipality
{
    return Municipality::factory()->create([
        'launch_date' => now()->subYear(),
        'settings' => ['youtube_channel_id' => 'UC123'],
    ]);
}

test('a future summarizable meeting is Scheduled', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->addDay(),
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::Scheduled);
});

test('a council+channel meeting within 24h is AwaitingVideo', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->subHours(2),
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::AwaitingVideo);
});

test('a meeting with a published summary is Published', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->subDays(3),
        'summarized_at' => now(),
    ]);
    Summary::factory()->create([
        'summarizable_type' => $m->getMorphClass(),
        'summarizable_id' => $m->id,
        'meeting_id' => $m->id,
        'municipality_id' => $m->municipality_id,
        'status' => SummaryStatus::Published->value,
    ]);
    expect($m->fresh()->processingStatus())->toBe(MeetingProcessingStatus::Published);
});

test('a summarized but unpublished meeting is InReview', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->subDays(3),
        'summarized_at' => now(),
        'summary_source' => 'notule',
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::InReview);
});

test('a skipped meeting is NoSource', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->subDays(3),
        'summary_skipped_reason' => 'no_source',
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::NoSource);
});

test('a pre-launch metadata-only meeting is PreLaunch', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subMonth()]);
    $m = Meeting::factory()->create([
        'municipality_id' => $muni->id,
        'ingest_mode' => IngestMode::MetadataOnly->value,
        'starts_at' => now()->subMonths(2),
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::PreLaunch);
});

test('a no-channel meeting past 24h with complete media awaiting notule is AwaitingNotule', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear(), 'settings' => []]);
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->subDays(1),
        'agenda_ingested_at' => now(),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $m->id, 'attachments_fetched_at' => now()]);
    expect($m->fresh()->processingStatus())->toBe(MeetingProcessingStatus::AwaitingNotule);
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=Models/MeetingProcessingStatusTest`
Expected: FAIL ("Call to undefined method ... processingStatus()")

- [ ] **Stap 3: Voeg casts, constants en de methode toe aan `Meeting`**

Voeg in `casts()` toe: `'source_resolved_at' => 'datetime'`, `'notule_detected_at' => 'datetime'`.

Voeg bovenin de class (na `protected $guarded = [];`) toe:

```php
    public const SOURCE_TRANSCRIPT = 'transcript';

    public const SOURCE_NOTULE = 'notule';

    public const SKIP_NO_SOURCE = 'no_source';
```

Voeg de afgeleide-status-methode toe (importeer `App\Enums\MeetingProcessingStatus` en `App\Enums\SummaryStatus` bovenin):

```php
    public function processingStatus(): MeetingProcessingStatus
    {
        $launchDate = $this->municipality->launch_date;
        $isPreLaunch = ! $this->shouldSummarize()
            && $launchDate !== null
            && $this->starts_at !== null
            && $this->starts_at->lessThan($launchDate);

        if ($isPreLaunch) {
            return MeetingProcessingStatus::PreLaunch;
        }

        if (! $this->shouldSummarize()) {
            return MeetingProcessingStatus::Scheduled;
        }

        $hasPublished = $this->summaries()
            ->where('status', SummaryStatus::Published->value)
            ->exists();
        if ($hasPublished) {
            return MeetingProcessingStatus::Published;
        }

        if ($this->summary_skipped_reason !== null) {
            return MeetingProcessingStatus::NoSource;
        }

        if ($this->summarized_at !== null) {
            return MeetingProcessingStatus::InReview;
        }

        if ($this->starts_at === null || now()->lessThan($this->starts_at)) {
            return MeetingProcessingStatus::Scheduled;
        }

        $channelId = $this->municipality->settings['youtube_channel_id'] ?? null;
        $isCouncilWithChannel = $this->type === MeetingType::Council && $channelId !== null;

        if ($isCouncilWithChannel
            && now()->lessThan($this->videoReadyAt())) {
            return MeetingProcessingStatus::AwaitingVideo;
        }

        if ($isCouncilWithChannel && $this->video !== null
            && ! in_array($this->video->status, [VideoStatus::NotFound, VideoStatus::Failed], true)) {
            return MeetingProcessingStatus::Processing;
        }

        return MeetingProcessingStatus::AwaitingNotule;
    }

    public function videoReadyAt(): ?\Carbon\CarbonInterface
    {
        return $this->starts_at?->copy()->addHours((int) config('volgjeraad.youtube.video_wait_hours'));
    }
```

(`MeetingType` en `VideoStatus` zijn al geïmporteerd in dit bestand.)

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=Models/MeetingProcessingStatusTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Models/Meeting.php tests/Unit/Models/MeetingProcessingStatusTest.php
git commit -m "feat: Meeting::processingStatus + bron-constants"
```

---

## Fase 2 — Resolutie-engine

### Taak 5: `NotuleDetectionAgent` + prompt

**Files:**
- Create: `app/Ai/Agents/NotuleDetectionAgent.php`
- Create: `resources/prompts/notule_detection.v2.md`
- Test: `tests/Feature/Ai/NotuleDetectionAgentTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Ai\Agents\NotuleDetectionAgent;
use Laravel\Ai\Enums\Lab;

test('notule detection agent returns structured presence, id and confidence', function (): void {
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => 42,
        'confidence' => 91,
    ]]);

    $agent = new NotuleDetectionAgent('gpt-5.4-mini', 'v2');
    $response = $agent->prompt('documentenlijst', provider: Lab::OpenAI, model: 'gpt-5.4-mini');

    expect($response->structured['is_notule_present'])->toBeTrue();
    expect($response->structured['media_object_id'])->toBe(42);
    expect($response->structured['confidence'])->toBe(91);
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=NotuleDetectionAgentTest`
Expected: FAIL ("Class NotuleDetectionAgent not found")

- [ ] **Stap 3: Schrijf de agent**

```php
<?php

namespace App\Ai\Agents;

use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class NotuleDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        return PromptRepository::load('notule_detection', $this->promptVersion);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'is_notule_present' => $schema->boolean()->required(),
            'media_object_id' => $schema->integer()->nullable(),
            'confidence' => $schema->integer()->required(),
        ];
    }
}
```

- [ ] **Stap 4: Schrijf de prompt** `resources/prompts/notule_detection.v2.md`

```markdown
Je bepaalt of er tussen de documenten van een Nederlandse gemeentelijke vergadering een
**notule** zit: een formeel verslag van wat besloten/besproken is. In ORI heet dit meestal
"besluitenlijst", "notulen", "verslag", "conceptverslag" of "concept-besluitenlijst".

Een agenda, raadsvoorstel, raadsinformatiebrief, ingekomen stuk of bijlage is GEEN notule.

Je krijgt een lijst documenten met `id`, `name` en `file_name`. Bepaal:
- `is_notule_present`: true als minstens één document een notule/besluitenlijst is.
- `media_object_id`: het `id` van dat document (de meest definitieve als er meerdere zijn;
  een vastgestelde notule heeft voorrang boven een concept). `null` als geen notule.
- `confidence`: 0-100, hoe zeker je bent.

Antwoord uitsluitend in het gevraagde gestructureerde formaat.
```

- [ ] **Stap 5: Draai test tot groen**

Run: `php artisan test --compact --filter=NotuleDetectionAgentTest`
Expected: PASS

- [ ] **Stap 6: Commit**

```bash
git add app/Ai/Agents/NotuleDetectionAgent.php resources/prompts/notule_detection.v2.md tests/Feature/Ai/NotuleDetectionAgentTest.php
git commit -m "feat: NotuleDetectionAgent + prompt"
```

---

### Taak 6: `DetectMeetingNotule`-actie

**Files:**
- Create: `app/Actions/Summaries/DetectMeetingNotule.php`
- Test: `tests/Feature/Summaries/DetectMeetingNotuleTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Actions\Summaries\DetectMeetingNotule;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MediaObject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['volgjeraad.ai.notule_confidence_threshold' => 70]));

function meetingWithDocs(): array
{
    $meeting = Meeting::factory()->summarizable()->create(['agenda_ingested_at' => now()]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);
    $media = MediaObject::factory()->create([
        'agenda_item_id' => $item->id,
        'name' => 'Besluitenlijst 3 juni 2026',
    ]);

    return [$meeting->fresh(), $media];
}

test('stores the notule when the agent finds one above threshold', function (): void {
    [$meeting, $media] = meetingWithDocs();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => $media->id,
        'confidence' => 88,
    ]]);

    app(DetectMeetingNotule::class)->handle($meeting);

    expect($meeting->fresh()->notule_detected_at)->not->toBeNull();
    expect($meeting->fresh()->notule_media_object_id)->toBe($media->id);
});

test('does not store when below the confidence threshold', function (): void {
    [$meeting] = meetingWithDocs();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => null,
        'confidence' => 40,
    ]]);

    app(DetectMeetingNotule::class)->handle($meeting);

    expect($meeting->fresh()->notule_detected_at)->toBeNull();
});

test('is a no-op when a notule was already detected', function (): void {
    [$meeting, $media] = meetingWithDocs();
    $meeting->update(['notule_detected_at' => now()->subDay(), 'notule_media_object_id' => $media->id]);
    NotuleDetectionAgent::fake([['is_notule_present' => false, 'media_object_id' => null, 'confidence' => 0]]);

    app(DetectMeetingNotule::class)->handle($meeting->fresh());

    // detected_at blijft de oude waarde (agent niet bepalend)
    expect($meeting->fresh()->notule_media_object_id)->toBe($media->id);
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=DetectMeetingNotuleTest`
Expected: FAIL ("Class DetectMeetingNotule not found")

- [ ] **Stap 3: Voeg confidence-config toe**

In `config/volgjeraad.php` onder `'ai' => [ ... ]` voeg toe: `'notule_confidence_threshold' => 70,`.

- [ ] **Stap 4: Schrijf de actie**

```php
<?php

namespace App\Actions\Summaries;

use App\Actions\Logging\RecordProcessingEvent;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Models\Meeting;
use App\Support\PromptRepository;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class DetectMeetingNotule
{
    public function __construct(private RecordProcessingEvent $log) {}

    public function handle(Meeting $meeting): void
    {
        if ($meeting->notule_detected_at !== null) {
            return;
        }

        $docs = [];
        foreach ($meeting->agendaItems()->with('mediaObjects')->get() as $item) {
            foreach ($item->mediaObjects as $media) {
                $docs[] = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                ];
            }
        }

        if ($docs === []) {
            return;
        }

        $model = (string) config('volgjeraad.ai.default_summary_model');
        $threshold = (int) config('volgjeraad.ai.notule_confidence_threshold');
        $agent = new NotuleDetectionAgent($model, PromptRepository::version());

        try {
            $response = $agent->prompt(
                json_encode(['documents' => $docs], JSON_UNESCAPED_UNICODE),
                provider: Lab::OpenAI,
                model: $model,
            );
        } catch (Throwable $e) {
            Log::warning('detect_meeting_notule failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $structured = $response->structured ?? [];
        $present = (bool) ($structured['is_notule_present'] ?? false);
        $confidence = (int) ($structured['confidence'] ?? 0);

        if (! $present || $confidence < $threshold) {
            return;
        }

        $meeting->update([
            'notule_detected_at' => now(),
            'notule_media_object_id' => $structured['media_object_id'] ?? null,
        ]);

        $this->log->handle($meeting, 'notule', 'success', "Notule gevonden (confidence: {$confidence}%)");
    }
}
```

- [ ] **Stap 5: Draai test tot groen**

Run: `php artisan test --compact --filter=DetectMeetingNotuleTest`
Expected: PASS

- [ ] **Stap 6: Commit**

```bash
git add app/Actions/Summaries/DetectMeetingNotule.php config/volgjeraad.php tests/Feature/Summaries/DetectMeetingNotuleTest.php
git commit -m "feat: DetectMeetingNotule actie"
```

---

### Taak 7: Gate omschakelen naar `summary_source`

**Files:**
- Modify: `app/Actions/Summaries/DispatchMeetingSummariesIfReady.php`
- Test: `tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php` (bestaand — aanpassen)

- [ ] **Stap 1: Pas de bestaande test aan op de nieuwe gate**

Vervang in `tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php` de transcript-georiënteerde verwachtingen: de gate dispatcht nu uitsluitend wanneer `summary_source` gezet is. Vervang het hele bestand door:

```php
<?php

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function readyMeeting(?string $source = 'notule'): Meeting
{
    $meeting = Meeting::factory()->summarizable()->create([
        'starts_at' => now()->subDay(),
        'summary_source' => $source,
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    return $meeting->fresh();
}

test('does not dispatch while no source has been resolved', function (): void {
    Bus::fake();
    app(DispatchMeetingSummariesIfReady::class)->handle(readyMeeting(null));
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('dispatches one job per level once a source is resolved', function (): void {
    Bus::fake();
    app(DispatchMeetingSummariesIfReady::class)->handle(readyMeeting('notule'));
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('waits for all media before dispatching', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->summarizable()->create([
        'starts_at' => now()->subDay(), 'summary_source' => 'notule',
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);
    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('an already summarized meeting is not dispatched again', function (): void {
    Bus::fake();
    $meeting = readyMeeting('notule');
    $meeting->update(['summarized_at' => now()]);
    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('a non-summarizable meeting is skipped', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->create([
        'ingest_mode' => 'metadata_only', 'starts_at' => now()->subDay(), 'summary_source' => 'notule',
    ]);
    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=DispatchMeetingSummariesIfReadyTest`
Expected: FAIL (gate gebruikt nog `transcriptResolved()`)

- [ ] **Stap 3: Pas de gate aan**

In `DispatchMeetingSummariesIfReady::handle()` vervang het transcript-blok:

```php
        // (b) Transcript-resolutie klaar (transcript binnen of definitief opgegeven).
        if (! $meeting->transcriptResolved()) {
            return;
        }
```

door:

```php
        // (b) Een bron is geresolveerd (transcript óf notule) door ResolveMeetingSummarySources.
        if ($meeting->summary_source === null) {
            return;
        }
```

Werk de class-PHPDoc bij zodat die naar de bron-resolutie verwijst i.p.v. de transcript-gate.

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=DispatchMeetingSummariesIfReadyTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Actions/Summaries/DispatchMeetingSummariesIfReady.php tests/Feature/Summaries/DispatchMeetingSummariesIfReadyTest.php
git commit -m "refactor: summary-gate op summary_source i.p.v. transcriptResolved"
```

---

### Taak 8: `ResolveMeetingSummarySources` — de brain

**Files:**
- Create: `app/Actions/Summaries/ResolveMeetingSummarySources.php`
- Test: `tests/Feature/Summaries/ResolveMeetingSummarySourcesTest.php`

- [ ] **Stap 1: Schrijf de falende test (alle takken)**

```php
<?php

use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Enums\MeetingType;
use App\Enums\SummaryLevel;
use App\Enums\VideoStatus;
use App\Jobs\IngestMeetingAgendaJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MediaObject;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.video_wait_hours' => 24,
        'volgjeraad.youtube.notule_recheck_working_days' => 2,
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.ai.notule_confidence_threshold' => 70,
    ]);
});

function channelCouncilMeeting(string $startsAt): Meeting
{
    $muni = Municipality::factory()->create([
        'launch_date' => now()->subYear(),
        'settings' => ['youtube_channel_id' => 'UC123'],
    ]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->parse($startsAt),
        'agenda_ingested_at' => now(),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    return $meeting->fresh();
}

test('council+channel within 24h does nothing', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 hours');
    app(ResolveMeetingSummarySources::class)->handle($m);
    Bus::assertNothingDispatched();
    expect($m->fresh()->summary_source)->toBeNull();
});

test('council+channel past 24h without a video dispatches the video job', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days');
    app(ResolveMeetingSummarySources::class)->handle($m);
    Bus::assertDispatched(ProcessMeetingVideoJob::class);
});

test('a transcribed video resolves the transcript source and dispatches summaries', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days');
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $m->id]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    expect($m->fresh()->summary_source)->toBe(Meeting::SOURCE_TRANSCRIPT);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('a detected notule resolves the notule source', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days');
    $m->video()->create(['status' => VideoStatus::NotFound->value, 'transcript_attempts' => 0]);
    $item = $m->agendaItems()->first();
    $media = MediaObject::factory()->create(['agenda_item_id' => $item->id, 'name' => 'Besluitenlijst']);
    $m->update(['notule_detected_at' => now(), 'notule_media_object_id' => $media->id]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    expect($m->fresh()->summary_source)->toBe(Meeting::SOURCE_NOTULE);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('no source before the recheck deadline re-ingests the agenda', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days'); // binnen 2 werkdagen
    $m->video()->create(['status' => VideoStatus::NotFound->value, 'transcript_attempts' => 0]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    Bus::assertDispatched(IngestMeetingAgendaJob::class);
    expect($m->fresh()->summary_skipped_reason)->toBeNull();
});

test('no source past the recheck deadline marks no_source and dispatches nothing', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-10 days'); // ruim voorbij 2 werkdagen
    $m->video()->create(['status' => VideoStatus::NotFound->value, 'transcript_attempts' => 0]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    expect($m->fresh()->summary_skipped_reason)->toBe(Meeting::SKIP_NO_SOURCE);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('a future meeting is ignored', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('+1 day');
    app(ResolveMeetingSummarySources::class)->handle($m);
    Bus::assertNothingDispatched();
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=ResolveMeetingSummarySourcesTest`
Expected: FAIL ("Class ResolveMeetingSummarySources not found")

- [ ] **Stap 3: Schrijf de actie**

```php
<?php

namespace App\Actions\Summaries;

use App\Actions\Logging\RecordProcessingEvent;
use App\Enums\MeetingType;
use App\Enums\VideoStatus;
use App\Jobs\IngestMeetingAgendaJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;

class ResolveMeetingSummarySources
{
    public function __construct(
        private DetectMeetingNotule $detectNotule,
        private DispatchMeetingSummariesIfReady $dispatchSummaries,
        private RecordProcessingEvent $log,
    ) {}

    public function handle(Meeting $meeting): void
    {
        if (! $meeting->shouldSummarize()
            || $meeting->summarized_at !== null
            || $meeting->summary_skipped_reason !== null
            || $meeting->starts_at === null
            || now()->lessThan($meeting->starts_at)) {
            return;
        }

        $channelId = $meeting->municipality->settings['youtube_channel_id'] ?? null;
        $isCouncilWithChannel = $meeting->type === MeetingType::Council && $channelId !== null;

        // 1) Transcript-pad
        if ($isCouncilWithChannel) {
            if (now()->lessThan($meeting->videoReadyAt())) {
                return; // video staat nog niet online
            }

            $video = $meeting->video;
            if ($video?->status === VideoStatus::Transcribed) {
                $this->summarizeWith($meeting, Meeting::SOURCE_TRANSCRIPT);

                return;
            }

            if ($video?->status === VideoStatus::NeedsConfirmation) {
                return; // wacht op handmatige bevestiging
            }

            $maxAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');
            $exhausted = $video !== null && (
                $video->status === VideoStatus::NotFound
                || ($video->status === VideoStatus::Failed && $video->transcript_attempts >= $maxAttempts)
            );

            if (! $exhausted) {
                ProcessMeetingVideoJob::dispatch($meeting->id);

                return; // video/transcript loopt; opnieuw geëvalueerd na de job
            }
            // video uitgeput → notule-pad
        }

        // 2) Notule-pad
        if ($meeting->notule_detected_at !== null) {
            $this->summarizeWith($meeting, Meeting::SOURCE_NOTULE);

            return;
        }

        if ($this->mediaComplete($meeting)) {
            $this->detectNotule->handle($meeting);
            $meeting->refresh();
            if ($meeting->notule_detected_at !== null) {
                $this->summarizeWith($meeting, Meeting::SOURCE_NOTULE);

                return;
            }
        }

        // 3) Geen bron → begrensde werkdag-rechecks
        $deadline = $meeting->starts_at->copy()->addWeekdays(
            (int) config('volgjeraad.youtube.notule_recheck_working_days'),
        );

        if (now()->greaterThanOrEqualTo($deadline)) {
            $meeting->update(['summary_skipped_reason' => Meeting::SKIP_NO_SOURCE]);
            $this->log->handle($meeting, 'resolve', 'warning', 'Geen bron (transcript/notule) — meeting zonder samenvatting vastgelegd');

            return;
        }

        // Notule kan later in ORI verschijnen → agenda opnieuw ophalen.
        IngestMeetingAgendaJob::dispatch($meeting->id);
    }

    private function summarizeWith(Meeting $meeting, string $source): void
    {
        if ($meeting->summary_source === null) {
            $meeting->update(['summary_source' => $source, 'source_resolved_at' => now()]);
        }

        $this->dispatchSummaries->handle($meeting->fresh());
    }

    private function mediaComplete(Meeting $meeting): bool
    {
        if ($meeting->agenda_ingested_at === null) {
            return false;
        }

        return $meeting->agendaItems()->whereNull('attachments_fetched_at')->count() === 0;
    }
}
```

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=ResolveMeetingSummarySourcesTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Actions/Summaries/ResolveMeetingSummarySources.php tests/Feature/Summaries/ResolveMeetingSummarySourcesTest.php
git commit -m "feat: ResolveMeetingSummarySources beslisboom"
```

---

### Taak 9: Event-call-sites naar de resolver leiden

**Files:**
- Modify: `app/Actions/Videos/FetchMeetingTranscript.php`
- Modify: `app/Actions/Ingest/IngestAgendaMediaObjects.php`
- Modify: `app/Jobs/ProcessMeetingVideoJob.php`
- Test: `tests/Feature/Summaries/ResolverCallSitesTest.php`

Reden: na de gate-omschakeling (Taak 7) dispatcht `DispatchMeetingSummariesIfReady` alleen
nog bij een gezette `summary_source`. De transcript-/media-binnenkomst moet daarom de brain
aanroepen (die zet de bron en dispatcht), niet meer de gate direct.

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Actions\Ingest\IngestAgendaMediaObjects;
use App\Actions\Videos\FetchMeetingTranscript;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'volgjeraad.youtube.video_wait_hours' => 24,
    'volgjeraad.youtube.notule_recheck_working_days' => 2,
]));

test('completing media on a no-channel meeting drives the resolver and (with a notule) summarizes', function (): void {
    Bus::fake([SummarizeMeetingJob::class]);
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear(), 'settings' => []]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDay(),
        'agenda_ingested_at' => now(),
        'notule_detected_at' => now(),
    ]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);

    app(IngestAgendaMediaObjects::class)->handle($item->fresh());

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});
```

(Deze test leunt op het ORI-vrije pad: `IngestAgendaMediaObjects` zet `attachments_fetched_at`
en roept de resolver aan; de meeting heeft al een notule, dus er volgt een samenvatting.)

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=ResolverCallSitesTest`
Expected: FAIL (media-actie roept nog de oude gate aan → geen dispatch)

- [ ] **Stap 3: Pas de drie call-sites aan**

In `app/Actions/Ingest/IngestAgendaMediaObjects.php`:
- Vervang de constructor-dependency `DispatchMeetingSummariesIfReady $dispatchMeetingSummaries` door `ResolveMeetingSummarySources $resolveSources` (pas de `use`-import aan).
- In `dispatchSummarizeIfComplete()` vervang `$this->dispatchMeetingSummaries->handle($meeting);` door `$this->resolveSources->handle($meeting);`.

In `app/Actions/Videos/FetchMeetingTranscript.php`:
- Vervang de constructor-dependency `DispatchMeetingSummariesIfReady $dispatchMeetingSummaries` door `ResolveMeetingSummarySources $resolveSources` (pas `use` aan).
- Vervang elke `$this->dispatchMeetingSummaries->handle($video->meeting);` (3 plekken) door `$this->resolveSources->handle($video->meeting->fresh());`.

In `app/Jobs/ProcessMeetingVideoJob.php`:
- Vervang de geïnjecteerde `DispatchMeetingSummariesIfReady $dispatchSummaries` door `ResolveMeetingSummarySources $resolve` (pas `use` aan).
- Vervang elke `$dispatchSummaries->handle($meeting);` (3 plekken) door `$resolve->handle($meeting->fresh());`.

- [ ] **Stap 4: Draai test + de aanpalende suites tot groen**

Run: `php artisan test --compact --filter="ResolverCallSitesTest|FetchMeetingTranscript|ProcessMeetingVideoJob|IngestMeetingAgenda"`
Expected: PASS (werk eventuele meegekomen verwachtingen in die suites bij naar de resolver-flow)

- [ ] **Stap 5: Commit**

```bash
git add app/Actions/Videos/FetchMeetingTranscript.php app/Actions/Ingest/IngestAgendaMediaObjects.php app/Jobs/ProcessMeetingVideoJob.php tests/Feature/Summaries/ResolverCallSitesTest.php
git commit -m "refactor: transcript/media/video call sites naar ResolveMeetingSummarySources"
```

---

### Taak 10: `ResolveReadyMeetingsJob` (15-min sweep)

**Files:**
- Create: `app/Jobs/ResolveReadyMeetingsJob.php`
- Test: `tests/Feature/Jobs/ResolveReadyMeetingsJobTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Jobs\ResolveReadyMeetingsJob;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it resolves summarizable meetings that have taken place and are unresolved', function (): void {
    $eligible = Meeting::factory()->summarizable()->create(['starts_at' => now()->subDay()]);
    Meeting::factory()->summarizable()->create(['starts_at' => now()->addDay()]);           // toekomst
    Meeting::factory()->summarizable()->create(['starts_at' => now()->subDay(), 'summarized_at' => now()]); // al klaar
    Meeting::factory()->summarizable()->create(['starts_at' => now()->subDay(), 'summary_skipped_reason' => 'no_source']); // skip
    Meeting::factory()->create(['ingest_mode' => 'metadata_only', 'starts_at' => now()->subDay()]); // niet-summarizable

    $resolver = Mockery::mock(ResolveMeetingSummarySources::class);
    $resolver->shouldReceive('handle')->once()->with(Mockery::on(fn (Meeting $m) => $m->id === $eligible->id));
    app()->instance(ResolveMeetingSummarySources::class, $resolver);

    (new ResolveReadyMeetingsJob)->handle($resolver);
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=ResolveReadyMeetingsJobTest`
Expected: FAIL ("Class ResolveReadyMeetingsJob not found")

- [ ] **Stap 3: Schrijf de job**

```php
<?php

namespace App\Jobs;

use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Enums\IngestMode;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveReadyMeetingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ResolveMeetingSummarySources $resolve): void
    {
        Log::info('ResolveReadyMeetingsJob gestart');

        $resolved = 0;

        Meeting::query()
            ->where('ingest_mode', IngestMode::Summarize->value)
            ->whereNull('summarized_at')
            ->whereNull('summary_skipped_reason')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($meetings) use ($resolve, &$resolved): void {
                foreach ($meetings as $row) {
                    $meeting = Meeting::with(['municipality', 'video'])->find($row->id);
                    if ($meeting === null) {
                        continue;
                    }

                    try {
                        $resolve->handle($meeting);
                        $resolved++;
                    } catch (Throwable $e) {
                        Log::warning('ResolveReadyMeetingsJob: meeting mislukt', [
                            'meeting_id' => $meeting->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('ResolveReadyMeetingsJob klaar', ['resolved' => $resolved]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ResolveReadyMeetingsJob mislukt', ['exception' => $exception->getMessage()]);
    }
}
```

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=ResolveReadyMeetingsJobTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Jobs/ResolveReadyMeetingsJob.php tests/Feature/Jobs/ResolveReadyMeetingsJobTest.php
git commit -m "feat: ResolveReadyMeetingsJob sweep"
```

---

### Taak 11: Scheduler omzetten naar 15 min

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/ScheduleTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use Illuminate\Console\Scheduling\Schedule;

test('the resolve sweep is scheduled every fifteen minutes', function (): void {
    $events = collect(app(Schedule::class)->events());

    $resolve = $events->first(fn ($e) => $e->description === 'volgjeraad:resolve');

    expect($resolve)->not->toBeNull();
    expect($resolve->expression)->toBe('*/15 * * * *');
});

test('the old daily match-videos schedule is gone', function (): void {
    $events = collect(app(Schedule::class)->events());
    expect($events->contains(fn ($e) => $e->description === 'volgjeraad:match-videos'))->toBeFalse();
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=ScheduleTest`
Expected: FAIL

- [ ] **Stap 3: Pas `routes/console.php` aan**

Vervang het `MatchMeetingVideosJob`-blok:

```php
Schedule::job(new MatchMeetingVideosJob)
    ->dailyAt('06:30')
    ->name('volgjeraad:match-videos')
    ->withoutOverlapping();
```

door:

```php
Schedule::job(new ResolveReadyMeetingsJob)
    ->everyFifteenMinutes()
    ->name('volgjeraad:resolve')
    ->withoutOverlapping();
```

Pas de `use`-imports bovenin aan: verwijder `use App\Jobs\MatchMeetingVideosJob;`, voeg `use App\Jobs\ResolveReadyMeetingsJob;` toe.

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=ScheduleTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add routes/console.php tests/Feature/ScheduleTest.php
git commit -m "feat: volgjeraad:resolve elke 15 min i.p.v. dagelijkse video-batch"
```

---

### Taak 12: `MatchMeetingVideosJob` opruimen

**Files:**
- Delete: `app/Jobs/MatchMeetingVideosJob.php`
- Delete: `tests/Feature/MatchMeetingVideosJobTest.php`
- Modify: `app/Models/Meeting.php` (verwijder ongebruikte `transcriptResolved()`)

- [ ] **Stap 1: Controleer dat niets `MatchMeetingVideosJob` of `transcriptResolved` nog gebruikt**

Run: `grep -rn "MatchMeetingVideosJob\|transcriptResolved" app routes tests`
Expected: alleen de te verwijderen bestanden/regels. Als andere code het nog gebruikt, eerst daar afhandelen.

- [ ] **Stap 2: Verwijder de job, de test en de methode**

```bash
git rm app/Jobs/MatchMeetingVideosJob.php tests/Feature/MatchMeetingVideosJobTest.php
git rm tests/Feature/Videos/MeetingTranscriptResolvedTest.php
```

Verwijder de methode `transcriptResolved()` uit `app/Models/Meeting.php`. Werk
`summaryStatusLabel()` bij: vervang de tak

```php
            if ($this->type === MeetingType::Council && ! $this->transcriptResolved()) {
                return 'Wacht op verwerking';
            }
```

door

```php
            if ($this->summary_skipped_reason !== null) {
                return 'Geen';
            }

            if ($this->processingStatus() !== MeetingProcessingStatus::Published) {
                return 'Wacht op verwerking';
            }
```

(importeer `App\Enums\MeetingProcessingStatus` indien nog niet aanwezig.)

- [ ] **Stap 3: Draai de volledige suite**

Run: `php artisan test --compact`
Expected: PASS (los eventuele resterende verwijzingen op)

- [ ] **Stap 4: Commit**

```bash
git add -A
git commit -m "chore: verwijder MatchMeetingVideosJob + transcriptResolved"
```

---

## Fase 3 — Beheer-UI

### Taak 13: Status mee-exposen in de beheercontrollers

**Files:**
- Modify: `app/Http/Controllers/Admin/MunicipalityOverviewController.php` (+ evt. `DashboardController.php`, `ReviewController.php`)
- Test: `tests/Feature/Admin/MunicipalityOverviewTest.php` (bestaand — uitbreiden)

Eerst de huidige prop-structuur lezen om aan te sluiten (de controller bepaalt welke
meeting-velden naar Inertia gaan). Voeg per gelijste meeting twee velden toe.

- [ ] **Stap 1: Schrijf de falende test (breid bestaande uit)**

Voeg toe aan `tests/Feature/Admin/MunicipalityOverviewTest.php`:

```php
test('the overview exposes a processing status label per meeting', function (): void {
    $user = \App\Models\User::factory()->create();
    $muni = \App\Models\Municipality::factory()->create(['launch_date' => now()->subYear()]);
    $meeting = \App\Models\Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDays(10),
        'summary_skipped_reason' => 'no_source',
    ]);

    $this->actingAs($user)
        ->get(route('admin.municipalities.show', $muni))
        ->assertInertia(fn ($page) => $page
            ->where('meetings.0.processing_status', 'no_source')
            ->where('meetings.0.processing_label', 'Geen bron — geen samenvatting'));
});
```

(Pas de route-naam/prop-pad aan op wat de controller daadwerkelijk rendert; verifieer met
`php artisan route:list --name=municipalities`.)

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=MunicipalityOverviewTest`
Expected: FAIL (prop ontbreekt)

- [ ] **Stap 3: Voeg de velden toe in de controller-mapping**

In de meeting-`map()` van de overzicht-controller, voeg per meeting toe:

```php
                'processing_status' => $meeting->processingStatus()->value,
                'processing_label' => $meeting->processingStatus()->adminLabel(),
```

Zorg dat de query `municipality` en (waar nodig) `summaries`/`video` eager-load zodat
`processingStatus()` geen N+1 veroorzaakt (`->with(['municipality', 'video', 'summaries'])`).

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=MunicipalityOverviewTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Http/Controllers/Admin/MunicipalityOverviewController.php tests/Feature/Admin/MunicipalityOverviewTest.php
git commit -m "feat: verwerkingsstatus in beheer-overzicht"
```

---

### Taak 14: Statusbadge in de beheer-React-pagina

**Files:**
- Modify: `resources/js/pages/admin/Municipalities/Show.tsx`

- [ ] **Stap 1: Voeg het type en de badge toe**

Breid het meeting-type uit met `processing_status: string` en `processing_label: string`.
Render naast de bestaande meetingnaam een badge:

```tsx
<span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
    {meeting.processing_label}
</span>
```

(Gebruik een bestaande badge-component als die er is; volg de stijl van de huidige lijst.)

- [ ] **Stap 2: Bouw de frontend en controleer visueel**

Run: `npm run build`
Expected: build slaagt; de statusbadge is zichtbaar op de gemeentepagina in beheer.

- [ ] **Stap 3: Commit**

```bash
git add resources/js/pages/admin/Municipalities/Show.tsx
git commit -m "feat: statusbadge in beheer-gemeentepagina"
```

---

## Fase 4 — Publieke UI

### Taak 15: Publieke gemeentelijst — plaatsgevonden meetings met status

**Files:**
- Modify: `app/Http/Controllers/Public/MunicipalityController.php`
- Test: `tests/Feature/Public/MunicipalityStatusTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Enums\SummaryStatus;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public list includes a past meeting without a summary, with a public message', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'name' => 'Raad zonder besluitenlijst',
        'starts_at' => now()->subDays(10),
        'summary_skipped_reason' => 'no_source',
    ]);

    $this->get(route('municipalities.show', $muni))
        ->assertInertia(fn ($page) => $page
            ->where('meetings.0.processing_status', 'no_source')
            ->where('meetings.0.status_message', 'Geen samenvatting: er is geen besluitenlijst beschikbaar.'));
});

test('the public list hides future meetings', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->addDays(3),
    ]);

    $this->get(route('municipalities.show', $muni))
        ->assertInertia(fn ($page) => $page->where('meetings', []));
});
```

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=MunicipalityStatusTest`
Expected: FAIL

- [ ] **Stap 3: Verruim de query + map in `MunicipalityController::show`**

Vervang de `$meetings`-query/-map door:

```php
        $meetings = $municipality->meetings()
            ->with(['municipality', 'video', 'summaries' => fn ($q) => $q->where('status', SummaryStatus::Published)])
            ->where(function ($q): void {
                $q->whereHas('summaries', fn ($s) => $s->where('status', SummaryStatus::Published))
                    ->orWhere('starts_at', '<=', now());
            })
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get()
            ->filter(fn (Meeting $m) => $m->processingStatus()->isPubliclyVisible())
            ->values();

        return Inertia::render('Municipality/Show', [
            'municipality' => $municipality->only('id', 'slug', 'name'),
            'meetings' => $meetings->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'processing_status' => $meeting->processingStatus()->value,
                'status_message' => $meeting->processingStatus()->publicMessage(),
                'summaries' => $meeting->summaries->map(fn ($s) => [
                    'id' => $s->id,
                    'level' => $s->level->value,
                    'title' => $s->title,
                    'body' => $s->body,
                ])->values(),
            ])->values(),
        ]);
```

(Importeer `App\Models\Meeting` bovenin de controller.)

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=MunicipalityStatusTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Http/Controllers/Public/MunicipalityController.php tests/Feature/Public/MunicipalityStatusTest.php
git commit -m "feat: publieke gemeentelijst toont plaatsgevonden meetings met status"
```

---

### Taak 16: Publieke meetingpagina — status-prop

**Files:**
- Modify: `app/Http/Controllers/Public/MeetingController.php`
- Test: `tests/Feature/Public/PagesTest.php` (bestaand — uitbreiden) of nieuw `MeetingStatusTest.php`

- [ ] **Stap 1: Schrijf de falende test**

```php
<?php

use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public meeting page exposes a status message when there is no published summary', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDays(10),
        'summary_skipped_reason' => 'no_source',
    ]);

    $this->get(route('meetings.show', [$muni, $meeting]))
        ->assertInertia(fn ($page) => $page
            ->where('meeting.processing_status', 'no_source')
            ->where('meeting.status_message', 'Geen samenvatting: er is geen besluitenlijst beschikbaar.'));
});
```

(Verifieer de route-naam met `php artisan route:list --path=meeting`.)

- [ ] **Stap 2: Draai om falen te zien**

Run: `php artisan test --compact --filter=MeetingStatusTest`
Expected: FAIL

- [ ] **Stap 3: Voeg de props toe in `MeetingController::show`**

Voeg binnen de `meeting`-array (na `starts_at`) toe:

```php
                'processing_status' => $meeting->processingStatus()->value,
                'status_message' => $meeting->processingStatus()->publicMessage(),
```

Zorg dat `municipality` en `video`/`summaries` geladen zijn voor `processingStatus()` (de
`load([...])` bovenin laadt al `summaries` en `video`; voeg `$meeting->loadMissing('municipality')` toe indien nodig).

- [ ] **Stap 4: Draai test tot groen**

Run: `php artisan test --compact --filter=MeetingStatusTest`
Expected: PASS

- [ ] **Stap 5: Commit**

```bash
git add app/Http/Controllers/Public/MeetingController.php tests/Feature/Public/MeetingStatusTest.php
git commit -m "feat: status-prop op publieke meetingpagina"
```

---

### Taak 17: Publieke React-weergave van de statusregel

**Files:**
- Modify: `resources/js/pages/Municipality/Show.tsx`
- Modify: `resources/js/pages/Meeting/Show.tsx`

- [ ] **Stap 1: Toon de statusregel waar geen samenvatting is**

In `Municipality/Show.tsx`: breid het meeting-type uit met `processing_status: string` en
`status_message: string`. Render, wanneer `meeting.summaries.length === 0` en
`meeting.status_message !== ''`, de regel i.p.v. de samenvatting:

```tsx
{meeting.summaries.length === 0 && meeting.status_message ? (
    <p className="text-sm text-slate-500 italic">{meeting.status_message}</p>
) : (
    /* bestaande samenvatting-weergave */
)}
```

In `Meeting/Show.tsx`: breid het `meeting`-type uit met dezelfde twee velden en toon
`status_message` bovenaan wanneer er geen `standard_summary`/`simple_summary` is.

- [ ] **Stap 2: Bouw de frontend en controleer visueel**

Run: `npm run build`
Expected: build slaagt; op een gemeente met een `no_source`-meeting verschijnt de regel
"Geen samenvatting: er is geen besluitenlijst beschikbaar."

- [ ] **Stap 3: Commit**

```bash
git add resources/js/pages/Municipality/Show.tsx resources/js/pages/Meeting/Show.tsx
git commit -m "feat: publieke statusregel voor meetings zonder samenvatting"
```

---

## Afronding

- [ ] **Volledige suite + Pint**

```bash
vendor/bin/pint --format agent
php artisan test --compact
```
Expected: groen, geen stijlproblemen.

- [ ] **Handmatige rooktest (lokaal)**

Run: `php artisan queue:work --once` na het seeden van een plaatsgevonden summarizable
meeting; verifieer via `php artisan volgjeraad:status` en de DB dat `summary_source` of
`summary_skipped_reason` gezet wordt.

---

## Self-review-notities (dekking t.o.v. spec)

- Bron-eis transcript/notule → Taken 5-8. 24u-video-gate → Taak 8 + `videoReadyAt()` (Taak 4).
- Werkdag-rechecks + `no_source` → Taak 8 (`addWeekdays`). Her-ingest agenda → Taak 8.
- AI-notule-detectie met gating (compleet/gewijzigd + rechecks) → Taken 5/6 + aanroep in Taak 8.
- 15-min sweep + scheduler-swap → Taken 10-12.
- Afgeleide status + beheer + publieke UI (incl. pre-launch & no_source-melding) → Taken 3/4, 13-17.
- Config-swap `transcript_wait_days` → `video_wait_hours` + `notule_recheck_working_days` → Taak 2.
- Buiten scope (review verwijderen, feestdagen, video-retry-marge) — niet ingepland, conform spec.
