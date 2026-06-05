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
