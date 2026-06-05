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
