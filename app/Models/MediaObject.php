<?php

namespace App\Models;

use Database\Factories\MediaObjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaObject extends Model
{
    /** @use HasFactory<MediaObjectFactory> */
    use HasFactory;

    protected $fillable = [
        'agenda_item_id',
        'position',
        'name',
        'file_name',
        'ori_id',
    ];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'text_pages' => 'array',
            'has_text' => 'boolean',
            'position' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    /** @param Builder<MediaObject> $query */
    public function scopeWithText(Builder $query): void
    {
        $query->where('has_text', true);
    }
}
