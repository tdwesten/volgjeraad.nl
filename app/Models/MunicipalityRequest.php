<?php

namespace App\Models;

use Database\Factories\MunicipalityRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class MunicipalityRequest extends Model
{
    /** @use HasFactory<MunicipalityRequestFactory> */
    use HasFactory, Prunable;

    protected $guarded = [];

    /**
     * @return Builder<MunicipalityRequest>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subMonths(3));
    }
}
