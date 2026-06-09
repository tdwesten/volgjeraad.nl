<?php

namespace App\Actions\Municipalities;

use App\Ai\Agents\StreamFinderAgent;
use App\Support\PromptRepository;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class FindMunicipalityStream
{
    /**
     * Zoekt via de StreamFinderAgent (met YouTube-tool) het officiële kanaal
     * van een gemeente waarop de raadsvergaderingen worden uitgezonden.
     *
     * @return array{channel_id: ?string, channel_title: ?string, channel_url: ?string, confidence: int, reason: string}
     */
    public function handle(string $municipalityName): array
    {
        $model = (string) config('volgjeraad.ai.default_summary_model');
        $promptVersion = PromptRepository::version();

        try {
            $agent = new StreamFinderAgent($model, $promptVersion);
            $response = $agent->prompt(
                "Gemeente: {$municipalityName}",
                provider: Lab::OpenAI,
                model: $model,
            );
            $structured = $response->structured ?? [];
        } catch (StrayRequestException $e) {
            // Test-hermeticiteit: bestaat alléén onder Http::preventStrayRequests().
            // Production-no-op; in tests faalt een ongefaket pad hard i.p.v. stil.
            throw $e;
        } catch (Throwable $e) {
            Log::warning('find_municipality_stream failed', [
                'municipality' => $municipalityName,
                'error' => $e->getMessage(),
            ]);

            return [
                'channel_id' => null,
                'channel_title' => null,
                'channel_url' => null,
                'confidence' => 0,
                'reason' => 'Het zoeken naar een stream is mislukt. Probeer het later opnieuw of vul het kanaal handmatig in.',
            ];
        }

        $channelId = $this->stringOrNull($structured['channel_id'] ?? null);
        $channelTitle = $this->stringOrNull($structured['channel_title'] ?? null);
        $channelUrl = $this->stringOrNull($structured['channel_url'] ?? null);

        return [
            'channel_id' => $channelId,
            'channel_title' => $channelTitle,
            'channel_url' => $channelUrl,
            'confidence' => (int) ($structured['confidence'] ?? 0),
            'reason' => (string) ($structured['reason'] ?? ''),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
