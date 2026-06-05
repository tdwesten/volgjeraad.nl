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
