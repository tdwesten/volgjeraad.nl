<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Pending = 'pending';
    case NeedsConfirmation = 'needs_confirmation';
    case Matched = 'matched';
    case Transcribed = 'transcribed';
    case NotFound = 'not_found';
    case Failed = 'failed';
}
