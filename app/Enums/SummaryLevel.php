<?php

namespace App\Enums;

enum SummaryLevel: string
{
    case Standard = 'standard';
    case Simple = 'simple';

    public function label(): string
    {
        return match ($this) {
            SummaryLevel::Standard => 'Standaard',
            SummaryLevel::Simple => 'Eenvoudig (B1)',
        };
    }
}
