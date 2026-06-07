<?php

namespace App\Services\YouTube;

use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\Requests\SearchRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use Carbon\CarbonImmutable;

class YouTubeClient
{
    public function __construct(private YouTubeConnector $connector) {}

    /**
     * Vrije zoekopdracht op YouTube. Geeft kanalen (default) of video's terug.
     *
     * @return array<int, array{id: string, title: string, description: string, url: string}>
     */
    public function search(string $query, string $type = 'channel', int $maxResults = 5): array
    {
        $items = $this->connector
            ->send(new SearchRequest($query, $type, $maxResults))
            ->throw()
            ->json('items', []);

        $results = [];
        foreach ($items as $item) {
            $channelId = $item['id']['channelId'] ?? null;
            $videoId = $item['id']['videoId'] ?? null;

            if ($channelId !== null) {
                $id = (string) $channelId;
                $url = "https://www.youtube.com/channel/{$id}";
            } elseif ($videoId !== null) {
                $id = (string) $videoId;
                $url = "https://www.youtube.com/watch?v={$id}";
            } else {
                continue;
            }

            $results[] = [
                'id' => $id,
                'title' => (string) ($item['snippet']['title'] ?? ''),
                'description' => (string) ($item['snippet']['description'] ?? ''),
                'url' => $url,
            ];
        }

        return $results;
    }

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
