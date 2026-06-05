<?php

namespace App\Enums;

enum EvaluationStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';
}
