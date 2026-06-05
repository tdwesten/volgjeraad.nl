# YouTube-transcript Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Per raadsvergadering de bijbehorende YouTube-uitzending vinden, het transcript ophalen, en dat transcript als extra debat-bron meenemen in de vergadering-samenvatting.

**Architecture:** Deterministische code (`YouTubeClient`, Saloon) haalt kandidaat-video's op binnen een kanaal + datumvenster; een `laravel/ai`-agent (`VideoMatchAgent`, structured output) kiest de beste match met confidence. Hoge confidence koppelt automatisch; lage confidence gaat naar de bestaande review-gate. Een betaalde transcript-API (`TranscriptProvider` → `SupadataTranscriptProvider`, Saloon) levert het transcript. Een dagelijkse `MatchMeetingVideosJob` orkestreert dit voor council-meetings waarvan `starts_at` voorbij is. Zodra het transcript binnen is verandert de bron-hash en regenereert `GenerateMeetingSummary` de draft met `[besluitenlijst + agenda-tekst + transcript]`.

**Tech Stack:** Laravel 13, PHP 8.5, Saloon (HTTP-integraties), `laravel/ai` (v0, OpenAI), Pest 4, MySQL.

---

## File Structure

**Nieuwe bestanden:**
- `app/Enums/VideoStatus.php` — statusmachine van een `MeetingVideo`.
- `database/migrations/2026_06_05_150000_create_meeting_videos_table.php` — 1-op-1 tabel met `meetings`.
- `app/Models/MeetingVideo.php` + `database/factories/MeetingVideoFactory.php` — model + factory.
- `app/Services/YouTube/VideoCandidate.php` — readonly DTO (videoId, title, publishedAt).
- `app/Http/Integrations/YouTube/YouTubeConnector.php` + `app/Http/Integrations/YouTube/Requests/SearchChannelVideosRequest.php` — Saloon-integratie YouTube Data API v3.
- `app/Services/YouTube/YouTubeClient.php` — zoekt video's op een kanaal binnen een venster.
- `app/Services/Transcript/TranscriptResult.php` — readonly DTO (text, source, segments).
- `app/Services/Transcript/TranscriptProvider.php` — interface.
- `app/Http/Integrations/Supadata/SupadataConnector.php` + `app/Http/Integrations/Supadata/Requests/FetchTranscriptRequest.php` — Saloon-integratie transcript-vendor.
- `app/Services/Transcript/SupadataTranscriptProvider.php` — default-implementatie.
- `app/Ai/Agents/VideoMatchAgent.php` + `resources/prompts/video_match.v1.md` — match-agent + prompt.
- `app/Actions/Videos/FindMeetingVideo.php` — kandidaten ophalen + matchen + `MeetingVideo` schrijven.
- `app/Actions/Videos/FetchMeetingTranscript.php` — transcript ophalen + re-summarize triggeren.
- `app/Jobs/MatchMeetingVideosJob.php` — dagelijkse orkestratie.
- Tests: `tests/Feature/YouTube/YouTubeClientTest.php`, `tests/Feature/Transcript/SupadataTranscriptProviderTest.php`, `tests/Feature/Videos/FindMeetingVideoTest.php`, `tests/Feature/Videos/FetchMeetingTranscriptTest.php`, `tests/Feature/Videos/MatchMeetingVideosJobTest.php`, `tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php`.

**Gewijzigde bestanden:**
- `config/volgjeraad.php` — `youtube`- en `transcript`-secties.
- `.env.example` — `YOUTUBE_API_KEY`, `SUPADATA_API_KEY`.
- `app/Models/Meeting.php` — `video()` hasOne-relatie.
- `app/Providers/AppServiceProvider.php` — binding `TranscriptProvider` → `SupadataTranscriptProvider`.
- `routes/console.php` — dagelijkse schedule.
- `app/Actions/Summaries/GenerateMeetingSummary.php` — transcript als extra bron + source_hash-aware idempotency (**Task 14, zie afhankelijkheid**).

---

## Task 1: Config + env-keys

**Files:**
- Modify: `config/volgjeraad.php`
- Modify: `.env.example`

- [ ] **Step 1: Voeg de config-secties toe**

Voeg in `config/volgjeraad.php`, ná de `'ai' => [...]`-array en vóór `'launch_date'`, deze twee blokken toe:

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
        // Stop met zoeken voor meetings ouder dan N dagen.
        'max_find_days' => 14,
    ],

    'transcript' => [
        'provider' => env('TRANSCRIPT_PROVIDER', 'supadata'),
        'supadata' => [
            'api_key' => env('SUPADATA_API_KEY'),
            'base_url' => env('SUPADATA_BASE_URL', 'https://api.supadata.ai'),
            'timeout' => 60,
            'connect_timeout' => 5,
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
Expected: toont de `youtube`-array met `search_window_days => 3`, `match_confidence_threshold => 75`, `max_find_days => 14`.

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
- Test: `tests/Feature/Videos/FindMeetingVideoTest.php` (enum wordt impliciet getest via latere taken; hier alleen het bestand)

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
            $table->dateTime('transcript_fetched_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
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
Expected: toont kolommen `meeting_id` (unique), `youtube_video_id`, `transcript_text`, `status`, `attempts` etc.

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
- Modify: `app/Models/Meeting.php`
- Test: `tests/Feature/Videos/MeetingVideoModelTest.php`

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
            'transcript_fetched_at' => null,
            'status' => VideoStatus::Pending->value,
            'attempts' => 0,
            'last_attempt_at' => null,
        ];
    }

    public function transcribed(): static
    {
        return $this->state([
            'status' => VideoStatus::Transcribed->value,
            'transcript_text' => 'Voorzitter: ik open de vergadering.',
            'transcript_source' => 'captions',
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

(`use Illuminate\Database\Eloquent\Relations\HasOne;` staat al geïmporteerd.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=MeetingVideoModelTest`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/MeetingVideo.php database/factories/MeetingVideoFactory.php app/Models/Meeting.php tests/Feature/Videos/MeetingVideoModelTest.php
git commit -m "feat: MeetingVideo model, factory en Meeting::video relatie"
```

---

## Task 5: VideoCandidate DTO

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

> Geen aparte test in deze taak; de request + connector worden end-to-end getest in Task 7 via `MockClient` (zelfde patroon als `tests/Feature/Ori/OriClientTest.php`).

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

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return ['key' => config('volgjeraad.youtube.api_key')];
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

        return array_values(array_map(
            fn (array $item): VideoCandidate => new VideoCandidate(
                videoId: $item['id']['videoId'],
                title: $item['snippet']['title'],
                publishedAt: CarbonImmutable::parse($item['snippet']['publishedAt']),
            ),
            $items,
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=YouTubeClientTest`
Expected: PASS (beide tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/YouTube/YouTubeClient.php tests/Feature/YouTube/YouTubeClientTest.php
git commit -m "feat: YouTubeClient zoekt kanaal-video's binnen datumvenster"
```

---

## Task 8: TranscriptResult DTO + TranscriptProvider interface

**Files:**
- Create: `app/Services/Transcript/TranscriptResult.php`
- Create: `app/Services/Transcript/TranscriptProvider.php`
- Test: `tests/Unit/TranscriptResultTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Unit/TranscriptResultTest.php`:

```php
<?php

use App\Services\Transcript\TranscriptResult;

test('transcript result holds text, source and optional segments', function (): void {
    $result = new TranscriptResult(
        text: 'Voorzitter: ik open de vergadering.',
        source: 'captions',
        segments: [['start' => 0, 'text' => 'Voorzitter']],
    );

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->source)->toBe('captions');
    expect($result->segments)->toBe([['start' => 0, 'text' => 'Voorzitter']]);
});

test('transcript result segments default to null', function (): void {
    $result = new TranscriptResult(text: 'tekst', source: 'ai');

    expect($result->segments)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TranscriptResultTest`
Expected: FAIL met "Class App\Services\Transcript\TranscriptResult not found".

- [ ] **Step 3: Schrijf het DTO en de interface**

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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TranscriptResultTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Transcript/TranscriptResult.php app/Services/Transcript/TranscriptProvider.php tests/Unit/TranscriptResultTest.php
git commit -m "feat: TranscriptResult DTO en TranscriptProvider interface"
```

---

## Task 9: SupadataTranscriptProvider (Saloon) + binding

**Files:**
- Create: `app/Http/Integrations/Supadata/SupadataConnector.php`
- Create: `app/Http/Integrations/Supadata/Requests/FetchTranscriptRequest.php`
- Create: `app/Services/Transcript/SupadataTranscriptProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Transcript/SupadataTranscriptProviderTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Transcript/SupadataTranscriptProviderTest.php`:

```php
<?php

use App\Http\Integrations\Supadata\Requests\FetchTranscriptRequest;
use App\Http\Integrations\Supadata\SupadataConnector;
use App\Services\Transcript\SupadataTranscriptProvider;
use App\Services\Transcript\TranscriptProvider;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

test('fetch returns captions transcript with language query', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => 'Voorzitter: ik open de vergadering.',
            'source' => 'captions',
        ], 200),
    ]);

    $connector = new SupadataConnector;
    $connector->withMockClient($mockClient);
    $provider = new SupadataTranscriptProvider($connector);

    $result = $provider->fetch('dQw4w9WgXcQ', 'nl');

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->source)->toBe('captions');

    $sent = $mockClient->getLastPendingRequest();
    $query = $sent->query()->all();
    expect($query['videoId'])->toBe('dQw4w9WgXcQ');
    expect($query['lang'])->toBe('nl');
});

test('fetch falls back to ai source when captions absent', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => 'AI-gegenereerd transcript.',
            'source' => 'ai',
        ], 200),
    ]);

    $connector = new SupadataConnector;
    $connector->withMockClient($mockClient);
    $provider = new SupadataTranscriptProvider($connector);

    $result = $provider->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('AI-gegenereerd transcript.');
    expect($result->source)->toBe('ai');
});

test('source defaults to captions when vendor omits it', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => 'Tekst zonder source-veld.',
        ], 200),
    ]);

    $connector = new SupadataConnector;
    $connector->withMockClient($mockClient);
    $provider = new SupadataTranscriptProvider($connector);

    $result = $provider->fetch('dQw4w9WgXcQ');

    expect($result->source)->toBe('captions');
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

- [ ] **Step 4: Schrijf de request**

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
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v1/transcript';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return [
            'videoId' => $this->youtubeVideoId,
            'lang' => $this->language,
            'text' => 'true',
        ];
    }
}
```

- [ ] **Step 5: Schrijf de provider**

Maak `app/Services/Transcript/SupadataTranscriptProvider.php`:

```php
<?php

namespace App\Services\Transcript;

use App\Http\Integrations\Supadata\Requests\FetchTranscriptRequest;
use App\Http\Integrations\Supadata\SupadataConnector;

class SupadataTranscriptProvider implements TranscriptProvider
{
    public function __construct(private SupadataConnector $connector) {}

    public function fetch(string $youtubeVideoId, string $language = 'nl'): TranscriptResult
    {
        $json = $this->connector
            ->send(new FetchTranscriptRequest($youtubeVideoId, $language))
            ->throw()
            ->json();

        return new TranscriptResult(
            text: (string) ($json['content'] ?? ''),
            source: (string) ($json['source'] ?? 'captions'),
            segments: $json['segments'] ?? null,
        );
    }
}
```

- [ ] **Step 6: Registreer de binding**

In `app/Providers/AppServiceProvider.php`, in de `register()`-methode, voeg toe:

```php
        $this->app->bind(
            \App\Services\Transcript\TranscriptProvider::class,
            \App\Services\Transcript\SupadataTranscriptProvider::class,
        );
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=SupadataTranscriptProviderTest`
Expected: PASS (alle vier tests).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Integrations/Supadata/ app/Services/Transcript/SupadataTranscriptProvider.php app/Providers/AppServiceProvider.php tests/Feature/Transcript/SupadataTranscriptProviderTest.php
git commit -m "feat: SupadataTranscriptProvider + TranscriptProvider binding"
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
- `video_id`: het id van de gekozen video, of een lege string als geen enkele kandidaat past.
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

**Files:**
- Create: `app/Actions/Videos/FindMeetingVideo.php`
- Test: `tests/Feature/Videos/FindMeetingVideoTest.php`

Signatuur: `handle(Meeting $meeting): ?MeetingVideo`. Haalt `youtube_channel_id` uit `municipality->settings`, zoekt kandidaten via `YouTubeClient`, laat `VideoMatchAgent` kiezen, schrijft een `MeetingVideo` (status afhankelijk van confidence vs. `match_confidence_threshold`).

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Videos/FindMeetingVideoTest.php`:

```php
<?php

use App\Actions\Videos\FindMeetingVideo;
use App\Ai\Agents\VideoMatchAgent;
use App\Enums\VideoStatus;
use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

function makeCouncilMeetingWithChannel(): Meeting
{
    $municipality = Municipality::factory()->create([
        'settings' => ['youtube_channel_id' => 'UC_brummen'],
    ]);

    return Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Raadsvergadering 4 juni 2026',
        'starts_at' => '2026-06-04 19:00:00',
    ]);
}

function fakeYouTubeWithOneCandidate(): void
{
    MockClient::global([
        SearchChannelVideosRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => ['videoId' => 'dQw4w9WgXcQ'],
                    'snippet' => [
                        'title' => 'Raadsvergadering 4 juni 2026',
                        'publishedAt' => '2026-06-04T21:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);
}

test('high confidence auto-matches and stores confirmed video', function (): void {
    fakeYouTubeWithOneCandidate();
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
    fakeYouTubeWithOneCandidate();
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

test('no candidates stores not_found and increments attempts', function (): void {
    MockClient::global([
        SearchChannelVideosRequest::class => MockResponse::make(['items' => []], 200),
    ]);
    VideoMatchAgent::fake([]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NotFound);
    expect($video->attempts)->toBe(1);
    expect($video->youtube_video_id)->toBeNull();
});

test('missing channel id returns null and creates no video', function (): void {
    VideoMatchAgent::fake([]);
    $municipality = Municipality::factory()->create(['settings' => null]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'starts_at' => '2026-06-04 19:00:00',
    ]);

    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video)->toBeNull();
    expect($meeting->fresh()->video)->toBeNull();
});

test('repeated find on same meeting updates the single video row', function (): void {
    fakeYouTubeWithOneCandidate();
    VideoMatchAgent::fake([
        ['video_id' => 'dQw4w9WgXcQ', 'confidence' => 40, 'reason' => 'Onzeker.'],
        ['video_id' => 'dQw4w9WgXcQ', 'confidence' => 90, 'reason' => 'Nu zeker.'],
    ]);

    $meeting = makeCouncilMeetingWithChannel();
    $action = app(FindMeetingVideo::class);

    $action->handle($meeting);
    $action->handle($meeting);

    expect($meeting->fresh()->video->status)->toBe(VideoStatus::Matched);
    expect($meeting->fresh()->video->attempts)->toBe(2);
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
        if ($channelId === null || $meeting->starts_at === null) {
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
        $videoId = (string) ($choice['video_id'] ?? '');

        $status = ($videoId !== '' && $confidence >= $threshold)
            ? VideoStatus::Matched
            : VideoStatus::NeedsConfirmation;

        return $this->store(
            $meeting,
            $status,
            candidates: $candidates,
            videoId: $videoId !== '' ? $videoId : null,
            confidence: $confidence,
            reason: (string) ($choice['reason'] ?? ''),
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
            'candidates' => array_map(fn (VideoCandidate $c) => $c->toArray(), $candidates),
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
        // Fresh query (niet de mogelijk gecachete relatie) zodat attempts correct optelt
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
                'candidates' => array_map(fn (VideoCandidate $c) => $c->toArray(), $candidates),
                'status' => $status->value,
                'confirmed_at' => $confirmed ? now() : ($existing?->confirmed_at),
                'attempts' => ($existing?->attempts ?? 0) + 1,
                'last_attempt_at' => now(),
            ],
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FindMeetingVideoTest`
Expected: PASS (alle vijf tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Videos/FindMeetingVideo.php tests/Feature/Videos/FindMeetingVideoTest.php
git commit -m "feat: FindMeetingVideo action met confidence-gate"
```

---

## Task 12: FetchMeetingTranscript action

**Files:**
- Create: `app/Actions/Videos/FetchMeetingTranscript.php`
- Test: `tests/Feature/Videos/FetchMeetingTranscriptTest.php`

Signatuur: `handle(MeetingVideo $video): void`. Roept `TranscriptProvider::fetch`, slaat het transcript op, zet status `Transcribed`, en dispatcht `SummarizeMeetingJob` voor beide `SummaryLevel`-cases (re-summarize).

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
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('stores transcript, sets transcribed status and dispatches re-summarize per level', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->with('dQw4w9WgXcQ', 'nl')
        ->andReturn(new TranscriptResult('Voorzitter: open.', 'captions'));

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Transcribed);
    expect($video->transcript_text)->toBe('Voorzitter: open.');
    expect($video->transcript_source)->toBe('captions');
    expect($video->transcript_fetched_at)->not->toBeNull();

    Bus::assertDispatched(SummarizeMeetingJob::class, fn ($job) => $job->level === SummaryLevel::Standard);
    Bus::assertDispatched(SummarizeMeetingJob::class, fn ($job) => $job->level === SummaryLevel::Simple);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('provider failure sets failed status, logs, and dispatches nothing', function (): void {
    Bus::fake();

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $msg === 'fetch_meeting_transcript failed' && isset($ctx['meeting_video_id']));

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andThrow(new RuntimeException('vendor down'));

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('empty transcript sets failed status and dispatches nothing', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andReturn(new TranscriptResult('', 'ai'));

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
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

use App\Enums\SummaryLevel;
use App\Enums\VideoStatus;
use App\Jobs\SummarizeMeetingJob;
use App\Models\MeetingVideo;
use App\Services\Transcript\TranscriptProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchMeetingTranscript
{
    public function __construct(
        private TranscriptProvider $transcriptProvider,
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
                'last_attempt_at' => now(),
            ]);

            return;
        }

        if (trim($result->text) === '') {
            $video->update([
                'status' => VideoStatus::Failed->value,
                'last_attempt_at' => now(),
            ]);

            return;
        }

        $video->update([
            'transcript_text' => $result->text,
            'transcript_source' => $result->source,
            'transcript_fetched_at' => now(),
            'status' => VideoStatus::Transcribed->value,
            'last_attempt_at' => now(),
        ]);

        foreach (SummaryLevel::cases() as $level) {
            dispatch(new SummarizeMeetingJob($video->meeting_id, $level));
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FetchMeetingTranscriptTest`
Expected: PASS (alle drie tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Videos/FetchMeetingTranscript.php tests/Feature/Videos/FetchMeetingTranscriptTest.php
git commit -m "feat: FetchMeetingTranscript action met re-summarize dispatch"
```

---

## Task 13: MatchMeetingVideosJob + dagelijkse schedule

**Files:**
- Create: `app/Jobs/MatchMeetingVideosJob.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Videos/MatchMeetingVideosJobTest.php`

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/Videos/MatchMeetingVideosJobTest.php`:

```php
<?php

use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Jobs\MatchMeetingVideosJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-06-10 06:30:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('find is called for eligible past council meeting without video', function (): void {
    $meeting = Meeting::factory()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->once()->with(\Mockery::on(fn ($m) => $m->id === $meeting->id))->andReturnNull();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();

    app(MatchMeetingVideosJob::class)->handle($find, app(FetchMeetingTranscript::class));
});

test('meeting older than max_find_days is skipped', function (): void {
    config(['volgjeraad.youtube.max_find_days' => 14]);
    Meeting::factory()->summarizable()->create(['starts_at' => '2026-05-01 19:00:00']);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->never();

    app(MatchMeetingVideosJob::class)->handle($find, app(FetchMeetingTranscript::class));
});

test('future meeting is skipped', function (): void {
    Meeting::factory()->summarizable()->create(['starts_at' => '2026-06-20 19:00:00']);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->never();

    app(MatchMeetingVideosJob::class)->handle($find, app(FetchMeetingTranscript::class));
});

test('already transcribed meeting is skipped', function (): void {
    $meeting = Meeting::factory()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->never();

    app(MatchMeetingVideosJob::class)->handle($find, app(FetchMeetingTranscript::class));
});

test('needs_confirmation meeting is skipped (awaits human)', function (): void {
    $meeting = Meeting::factory()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
    ]);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->never();

    app(MatchMeetingVideosJob::class)->handle($find, app(FetchMeetingTranscript::class));
});

test('matched meeting goes straight to transcript fetch', function (): void {
    $meeting = Meeting::factory()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Matched->value,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with(\Mockery::on(fn ($v) => $v->id === $video->id));

    app(MatchMeetingVideosJob::class)->handle(app(FindMeetingVideo::class), $fetch);
});

test('find returning a matched video triggers transcript fetch in same run', function (): void {
    $meeting = Meeting::factory()->summarizable()->create(['starts_at' => '2026-06-04 19:00:00']);
    $matched = new MeetingVideo(['status' => VideoStatus::Matched->value]);
    $matched->setRelation('meeting', $meeting);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->once()->andReturn($matched);
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with($matched);

    app(MatchMeetingVideosJob::class)->handle($find, $fetch);
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

use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

class MatchMeetingVideosJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(FindMeetingVideo $find, FetchMeetingTranscript $fetch): void
    {
        $cutoff = CarbonImmutable::now()->subDays((int) config('volgjeraad.youtube.max_find_days'));

        $meetings = Meeting::query()
            ->council()
            ->summarizable()
            ->where('starts_at', '<', now())
            ->where('starts_at', '>=', $cutoff)
            ->whereDoesntHave('video', function ($query): void {
                $query->whereIn('status', [
                    VideoStatus::Transcribed->value,
                    VideoStatus::NeedsConfirmation->value,
                ]);
            })
            ->get();

        foreach ($meetings as $meeting) {
            $video = $meeting->video;

            if ($video?->status === VideoStatus::Matched) {
                $fetch->handle($video);

                continue;
            }

            $matched = $find->handle($meeting);
            if ($matched?->status === VideoStatus::Matched) {
                $fetch->handle($matched);
            }
        }
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
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

- [ ] **Step 4: Voeg de schedule toe**

In `routes/console.php`, ná het bestaande `volgjeraad:daily-ingest`-blok, voeg toe:

```php
use App\Jobs\MatchMeetingVideosJob;

// YouTube-transcript: dagelijks video's matchen en transcripts ophalen
Schedule::job(new MatchMeetingVideosJob)
    ->dailyAt('06:30')
    ->name('volgjeraad:daily-video-match')
    ->withoutOverlapping();
```

(Plaats de `use App\Jobs\MatchMeetingVideosJob;` bovenaan bij de bestaande `use`-statements.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=MatchMeetingVideosJobTest`
Expected: PASS (alle zeven tests).

- [ ] **Step 6: Verifieer de schedule-registratie**

Run: `php artisan schedule:list`
Expected: bevat een regel met `volgjeraad:daily-video-match` om 06:30.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Jobs/MatchMeetingVideosJob.php routes/console.php tests/Feature/Videos/MatchMeetingVideosJobTest.php
git commit -m "feat: MatchMeetingVideosJob + dagelijkse video-match schedule"
```

---

## Task 14: GenerateMeetingSummary — transcript als extra bron

> **⚠️ Afhankelijkheid:** Start deze taak pas nadat de aparte *meeting-summary dispatch-fix* gemerged is. Rebase deze wijziging daar bovenop; het is een afgebakende toevoeging op `GenerateMeetingSummary::handle()` en mag de dispatch-fix niet overschrijven.

**Files:**
- Modify: `app/Actions/Summaries/GenerateMeetingSummary.php`
- Test: `tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php`

Doel: wanneer `meeting->video->transcript_text` aanwezig is, wordt het transcript als extra blok aan de bron-tekst toegevoegd. Dat verandert de `source_hash`. De idempotency-check wordt `source_hash`-aware: bij een ongewijzigde bron wordt de bestaande summary teruggegeven; bij een gewijzigde bron (transcript erbij) wordt een bestaande **Draft** vervangen en opnieuw gegenereerd.

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

test('transcript is appended to source and changes the source hash, regenerating the draft', function (): void {
    MeetingSummaryAgent::fake([
        ['title' => 'Zonder transcript', 'body' => 'Alleen PDF.', 'impact_note' => 'x', 'confidence' => 70, 'flags' => []],
        ['title' => 'Met transcript', 'body' => 'PDF plus debat.', 'impact_note' => 'y', 'confidence' => 80, 'flags' => []],
    ]);

    $meeting = makeMeetingWithAgendaText();
    $action = app(GenerateMeetingSummary::class);

    $first = $action->handle($meeting, SummaryLevel::Standard);
    $hashWithoutTranscript = $first->source_hash;

    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'transcript_text' => 'Raadslid A: ik dien een motie in over duurzaamheid.',
        'transcript_source' => 'captions',
    ]);

    $second = $action->handle($meeting->fresh(), SummaryLevel::Standard);

    expect($second->source_hash)->not->toBe($hashWithoutTranscript);
    expect($second->title)->toBe('Met transcript');
    expect(Summary::where('meeting_id', $meeting->id)->where('level', SummaryLevel::Standard->value)->count())->toBe(1);
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateMeetingSummaryTranscriptTest`
Expected: FAIL — bij de eerste test komt de tweede `handle` terug met de oude summary (idempotency op `level`), dus `title` is "Zonder transcript" en `source_hash` ongewijzigd.

- [ ] **Step 3: Pas `GenerateMeetingSummary::handle()` aan**

In `app/Actions/Summaries/GenerateMeetingSummary.php`:

**(a)** Verwijder de bestaande idempotency-vroege-return bovenaan `handle()` (regels 26-34, het blok dat `$existing` per level ophaalt en teruggeeft). Deze wordt verplaatst naar ná de hash-berekening.

**(b)** Vervang de source-text-opbouw (regels 43-48) door dit blok, dat het transcript aanhangt:

```php
        // Concat raw agenda texts in position order
        $sourceText = $meeting->agendaItems()
            ->orderBy('position')
            ->get()
            ->map(fn ($item) => $item->sourceText())
            ->filter(fn ($text) => $text !== '')
            ->implode("\n\n---\n\n");

        // Transcript (debat) als extra bron, indien aanwezig
        $transcript = $meeting->video?->transcript_text;
        if ($transcript !== null && trim($transcript) !== '') {
            $transcriptBlock = "=== TRANSCRIPT (debat) ===\n\n".$transcript;
            $sourceText = $sourceText === ''
                ? $transcriptBlock
                : $sourceText."\n\n---\n\n".$transcriptBlock;
        }
```

**(c)** Direct ná de truncatie-stap (ná regel 77, waar `$truncated` bepaald wordt) en vóór de cost-check, voeg de `source_hash`-aware idempotency toe:

```php
        $sourceHash = PayloadHasher::hash(['text' => $sourceText]);

        // Idempotency op bron-hash: ongewijzigde bron → bestaande summary teruggeven.
        $existing = Summary::where('summarizable_type', $meeting->getMorphClass())
            ->where('summarizable_id', $meeting->getKey())
            ->where('level', $level->value)
            ->where('language', 'nl')
            ->first();

        if ($existing !== null && $existing->source_hash === $sourceHash) {
            return $existing;
        }

        // Gewijzigde bron (bijv. transcript erbij): vervang een bestaande draft.
        if ($existing !== null && $existing->status === SummaryStatus::Draft->value) {
            $existing->delete();
        }
```

**(d)** Vervang in de `Summary::create([...])`-aanroep (de succesvolle tak, regel ~112) de inline hash door de berekende variabele:

```php
                'source_hash' => $sourceHash,
```

**(e)** Doe hetzelfde in de empty-source-tak (regel ~58): dat blok wordt nu pas bereikt ná hash-berekening — verplaats de empty-source-`return` naar ná stap (c), zodat het ook de `source_hash`-aware idempotency respecteert. Concreet: laat de `if ($sourceText === '')`-tak ná het idempotency-blok staan en gebruik `'source_hash' => $sourceHash` (dat is `hash(['text' => ''])`).

> Resultaat-volgorde in `handle()`: bron-tekst opbouwen (incl. transcript) → truncatie → `$sourceHash` → idempotency/draft-vervanging → empty-source-tak → cost-check → AI-call → `Summary::create` met `$sourceHash`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GenerateMeetingSummaryTranscriptTest`
Expected: PASS (beide tests).

- [ ] **Step 5: Run de volledige summaries-suite (regressie)**

Run: `php artisan test --compact --filter=Summar`
Expected: PASS — bestaande meeting/agenda-summary-tests blijven groen (de `source_hash`-aware idempotency gedraagt zich gelijk voor ongewijzigde bron).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Summaries/GenerateMeetingSummary.php tests/Feature/Ai/GenerateMeetingSummaryTranscriptTest.php
git commit -m "feat: transcript als extra bron in GenerateMeetingSummary met source_hash re-summarize"
```

---

## Slotcontrole

- [ ] **Volledige testsuite**

Run: `php artisan test --compact`
Expected: alle tests groen.

- [ ] **Pint over de hele branch**

Run: `vendor/bin/pint --dirty --format agent`
Expected: geen openstaande stijl-fouten.
