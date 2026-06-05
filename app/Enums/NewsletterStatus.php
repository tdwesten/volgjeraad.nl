<?php

namespace App\Enums;

enum NewsletterStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
