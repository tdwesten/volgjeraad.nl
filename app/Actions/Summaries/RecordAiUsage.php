<?php

namespace App\Actions\Summaries;

use App\Models\AiUsageRecord;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Model;

class RecordAiUsage
{
    public function handle(
        Model $subject,
        Municipality $municipality,
        Meeting $meeting,
        string $operation,
        string $provider,
        string $model,
        string $promptVersion,
        int $inputTokens,
        int $outputTokens,
        int $costCents,
        string $status,
    ): AiUsageRecord {
        return AiUsageRecord::create([
            'municipality_id' => $municipality->id,
            'meeting_id' => $meeting->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'operation' => $operation,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'status' => $status,
            'raw_metadata' => null,
        ]);
    }
}
