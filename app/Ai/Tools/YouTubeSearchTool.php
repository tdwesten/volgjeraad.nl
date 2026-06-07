<?php

namespace App\Ai\Tools;

use App\Services\YouTube\YouTubeClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class YouTubeSearchTool implements Tool
{
    public function __construct(private YouTubeClient $youTubeClient) {}

    public function name(): string
    {
        return 'youtube_search';
    }

    public function description(): Stringable|string
    {
        return 'Zoekt live op YouTube. Geef een zoekterm op; standaard worden kanalen '
            .'teruggegeven (type "channel"), of video\'s met type "video". Retourneert een '
            .'JSON-lijst met id, title, description en url per resultaat.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return json_encode(['error' => 'query is verplicht'], JSON_UNESCAPED_UNICODE);
        }

        $type = (string) ($request['type'] ?? 'channel');
        if (! in_array($type, ['channel', 'video'], true)) {
            $type = 'channel';
        }

        $maxResults = (int) ($request['max_results'] ?? 5);
        $maxResults = max(1, min($maxResults, 10));

        try {
            $results = $this->youTubeClient->search($query, $type, $maxResults);
        } catch (Throwable $e) {
            Log::warning('youtube_search tool failed', ['query' => $query, 'error' => $e->getMessage()]);

            return json_encode(['error' => 'YouTube-zoekopdracht mislukt', 'results' => []], JSON_UNESCAPED_UNICODE);
        }

        return json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('De YouTube-zoekterm, bijv. "gemeente Brummen raadsvergadering".')
                ->required(),
            'type' => $schema->string()
                ->description('Wat te zoeken: "channel" (default) of "video".'),
            'max_results' => $schema->integer()
                ->description('Aantal resultaten (1-10, default 5).'),
        ];
    }
}
