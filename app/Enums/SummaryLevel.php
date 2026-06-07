<?php

namespace App\Enums;

enum SummaryLevel: string
{
    case Standard = 'standard';
    case Simple = 'simple';
    case Plain = 'plain';

    public function label(): string
    {
        return match ($this) {
            SummaryLevel::Standard => 'Standaard',
            SummaryLevel::Simple => 'Eenvoudig (B1)',
            SummaryLevel::Plain => 'Korte samenvatting',
        };
    }
}
