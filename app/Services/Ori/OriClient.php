<?php

namespace App\Services\Ori;

use App\Http\Integrations\Ori\OriConnector;
use App\Http\Integrations\Ori\Requests\FetchByIdsRequest;
use App\Http\Integrations\Ori\Requests\SearchMeetingsRequest;
use App\Models\Municipality;
use Carbon\CarbonImmutable;

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
}
