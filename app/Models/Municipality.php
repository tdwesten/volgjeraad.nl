<?php

namespace App\Models;

use App\Enums\MeetingType;
use Database\Factories\MunicipalityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    /** @use HasFactory<MunicipalityFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'active' => 'boolean',
            'launch_date' => 'date',
        ];
    }

    /** @return HasMany<Meeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /** @return HasMany<Subscriber, $this> */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /** @return HasMany<Newsletter, $this> */
    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }

    /** @param Builder<Municipality> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** @return string[] */
    public function summarizeTypes(): array
    {
        $configured = ($this->settings ?? [])['summarize_types'] ?? null;

        if (is_array($configured) && count($configured) > 0) {
            return $configured;
        }

        return array_map(fn (MeetingType $t) => $t->value, MeetingType::cases());
    }
}
