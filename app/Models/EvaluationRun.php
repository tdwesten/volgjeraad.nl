<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use Database\Factories\EvaluationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationRun extends Model
{
    /** @use HasFactory<EvaluationRunFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => EvaluationStatus::class,
            'checklist_results' => 'array',
        ];
    }

    /** @return BelongsTo<EvaluationCase, $this> */
    public function evaluationCase(): BelongsTo
    {
        return $this->belongsTo(EvaluationCase::class);
    }
}
