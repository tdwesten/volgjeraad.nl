<?php

namespace App\Services\Transcript;

use App\Http\Integrations\Supadata\Requests\FetchTranscriptRequest;
use App\Http\Integrations\Supadata\Requests\GetTranscriptJobRequest;
use App\Http\Integrations\Supadata\SupadataConnector;

class SupadataTranscriptProvider implements TranscriptProvider
{
    public function __construct(private SupadataConnector $connector) {}

    public function fetch(string $youtubeVideoId, string $language = 'nl'): TranscriptResult
    {
        $mode = (string) config('volgjeraad.transcript.supadata.mode', 'auto');

        $response = $this->connector
            ->send(new FetchTranscriptRequest($youtubeVideoId, $language, $mode))
            ->throw();

        $json = $response->json();

        // Async-pad: 202 of een jobId zonder directe content → pollen.
        if ($response->status() === 202 || (isset($json['jobId']) && ! isset($json['content']))) {
            $json = $this->pollJob((string) $json['jobId']);
        }

        return $this->toResult($json, $mode, $language);
    }

    /**
     * @return array<string, mixed>
     */
    private function pollJob(string $jobId): array
    {
        $maxAttempts = (int) config('volgjeraad.transcript.supadata.poll_max_attempts', 10);
        $intervalMs = (int) config('volgjeraad.transcript.supadata.poll_interval_ms', 2000);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $json = $this->connector
                ->send(new GetTranscriptJobRequest($jobId))
                ->throw()
                ->json();

            $status = $json['status'] ?? null;

            if ($status === 'completed') {
                return $json;
            }

            if ($status === 'failed' || $status === 'error') {
                throw new TranscriptJobFailedException("Supadata transcript job {$jobId} failed.");
            }

            if ($intervalMs > 0) {
                usleep($intervalMs * 1000);
            }
        }

        throw new TranscriptJobFailedException("Supadata transcript job {$jobId} did not complete in time.");
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function toResult(array $json, string $mode, string $language): TranscriptResult
    {
        $content = $json['content'] ?? '';
        $segments = null;

        if (is_array($content)) {
            $segments = $content;
            $text = implode(' ', array_map(
                fn (array $segment): string => (string) ($segment['text'] ?? ''),
                $content,
            ));
        } else {
            $text = (string) $content;
        }

        return new TranscriptResult(
            text: trim($text),
            source: "supadata:{$mode}",
            lang: $json['lang'] ?? $language,
            segments: $segments,
        );
    }
}
