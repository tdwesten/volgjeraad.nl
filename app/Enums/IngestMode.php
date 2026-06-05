<?php

namespace App\Enums;

enum IngestMode: string
{
    case Summarize = 'summarize';
    case MetadataOnly = 'metadata_only';
}
