<?php

namespace App\Services\Ori;

use App\Http\Integrations\Ori\OriConnector;
use App\Http\Integrations\Ori\Requests\FetchByIdsRequest;
use App\Http\Integrations\Ori\Requests\ProbeIndexRequest;
use App\Http\Integrations\Ori\Requests\SearchMeetingsRequest;
use App\Models\Municipality;
use Carbon\CarbonImmutable;
use Saloon\Http\Response;
use Throwable;

class OriClient
{
    public function __construct(private OriConnector $connector) {}

    public function searchMeetings(Municipality $m, CarbonImmutable $from, CarbonImmutable $to, int $size = 500): array
    {
        return $this->connector
            ->send(new SearchMeetingsRequest($m->ori_index, $from, $to, $size))
            ->throw()
            ->json('hits.hits', []);
    }

    /**
     * @param  array<string>  $oriIds
     * @return array<string, array<string, mixed>> _id => _source
     */
    public function fetchByIds(Municipality $m, array $oriIds, int $chunk = 100): array
    {
        $result = [];

        foreach (array_chunk($oriIds, $chunk) as $chunkIds) {
            $hits = $this->connector
                ->send(new FetchByIdsRequest($m->ori_index, $chunkIds, count($chunkIds)))
                ->throw()
                ->json('hits.hits', []);

            foreach ($hits as $hit) {
                $result[$hit['_id']] = $hit['_source'];
            }
        }

        return $result;
    }

    /**
     * Check whether an ORI index already exists and report its meeting stats.
     *
     * @return array{
     *     exists: bool,
     *     meeting_count: int|null,
     *     latest_meeting: array{name: ?string, date: ?string}|null,
     *     error: ?string,
     * }
     */
    public function probeIndex(string $oriIndex): array
    {
        // Een ontbrekende index (404) is een verwachte uitkomst; retry die niet.
        // Kloon de connector zodat de gedeelde tries-instelling onaangetast blijft.
        // Met tries=1 throwt Saloon niet automatisch — we beoordelen de status zelf.
        $connector = clone $this->connector;
        $connector->tries = 1;

        try {
            $response = $connector->send(new ProbeIndexRequest($oriIndex));
        } catch (Throwable $e) {
            return $this->probeResult(exists: false, error: $e->getMessage());
        }

        if ($response->status() === 404) {
            return $this->probeResult(exists: false);
        }

        if ($response->failed()) {
            return $this->probeResult(exists: false, error: $this->probeErrorMessage($response));
        }

        $total = $response->json('hits.total.value');
        $hits = $response->json('hits.hits', []);

        $latestMeeting = null;
        if ($hits !== []) {
            $first = $hits[0];
            $meeting = OriNormalizer::meeting($first['_id'] ?? '', $first['_source'] ?? []);
            $latestMeeting = [
                'name' => $meeting['name'],
                'date' => $meeting['start_date'],
            ];
        }

        return $this->probeResult(
            exists: true,
            meetingCount: $total !== null ? (int) $total : null,
            latestMeeting: $latestMeeting,
        );
    }

    /**
     * @param  array{name: ?string, date: ?string}|null  $latestMeeting
     * @return array{
     *     exists: bool,
     *     meeting_count: int|null,
     *     latest_meeting: array{name: ?string, date: ?string}|null,
     *     error: ?string,
     * }
     */
    private function probeResult(bool $exists, ?int $meetingCount = null, ?array $latestMeeting = null, ?string $error = null): array
    {
        return [
            'exists' => $exists,
            'meeting_count' => $meetingCount,
            'latest_meeting' => $latestMeeting,
            'error' => $error,
        ];
    }

    private function probeErrorMessage(Response $response): string
    {
        $status = $response->status();
        $type = $response->json('error.type');

        return $type !== null
            ? "ORI probe failed (HTTP {$status}): {$type}"
            : "ORI probe failed (HTTP {$status})";
    }
}
