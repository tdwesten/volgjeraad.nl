<?php

namespace App\Models;

use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use Database\Factories\SummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Summary extends Model
{
    /** @use HasFactory<SummaryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'level' => SummaryLevel::class,
            'status' => SummaryStatus::class,
            'flags' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function summarizable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsToMany<Newsletter, $this> */
    public function newsletters(): BelongsToMany
    {
        return $this->belongsToMany(Newsletter::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
