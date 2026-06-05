<?php

namespace App\Services\Transcript;

interface TranscriptProvider
{
    public function fetch(string $youtubeVideoId, string $language = 'nl'): TranscriptResult;
}
