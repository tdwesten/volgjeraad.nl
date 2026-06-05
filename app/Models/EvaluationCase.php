<?php

namespace App\Models;

use Database\Factories\EvaluationCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCase extends Model
{
    /** @use HasFactory<EvaluationCaseFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_facts' => 'array',
            'forbidden_claims' => 'array',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<EvaluationRun, $this> */
    public function evaluationRuns(): HasMany
    {
        return $this->hasMany(EvaluationRun::class);
    }
}
