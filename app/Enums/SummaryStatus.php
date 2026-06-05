<?php

namespace App\Enums;

enum SummaryStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';
}
