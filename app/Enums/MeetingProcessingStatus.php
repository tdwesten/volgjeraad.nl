<?php

namespace App\Enums;

enum MeetingProcessingStatus: string
{
    case PreLaunch = 'pre_launch';
    case Scheduled = 'scheduled';
    case AwaitingVideo = 'awaiting_video';
    case Processing = 'processing';
    case AwaitingNotule = 'awaiting_notule';
    case InReview = 'in_review';
    case Published = 'published';
    case NoSource = 'no_source';

    public function adminLabel(): string
    {
        return match ($this) {
            self::PreLaunch => 'Voor livegang — niet samengevat',
            self::Scheduled => 'Gepland',
            self::AwaitingVideo => 'In afwachting van video',
            self::Processing => 'Bezig met verwerken',
            self::AwaitingNotule => 'In afwachting van notule',
            self::InReview => 'In review',
            self::Published => 'Gepubliceerd',
            self::NoSource => 'Geen bron — geen samenvatting',
        };
    }

    public function publicMessage(): string
    {
        return match ($this) {
            self::PreLaunch => 'Deze vergadering vond plaats vóór de livegang en is niet samengevat.',
            self::Scheduled => '',
            self::AwaitingVideo => 'Wordt verwerkt zodra de video beschikbaar is.',
            self::Processing => 'Bezig met verwerken.',
            self::AwaitingNotule => 'Wachten op de besluitenlijst.',
            self::InReview => 'Bezig met verwerken.',
            self::Published => '',
            self::NoSource => 'Geen samenvatting: er is geen besluitenlijst beschikbaar.',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this !== self::Scheduled;
    }
}
