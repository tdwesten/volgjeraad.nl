<?php

namespace App\Enums;

enum MeetingType: string
{
    case Council = 'council';
    case Committee = 'committee';
    case College = 'college';
    case Other = 'other';

    public static function fromCommitteeName(?string $name, string $raadPattern): self
    {
        if ($name === null) {
            return self::Other;
        }

        if (preg_match('/'.preg_quote($raadPattern, '/').'/i', $name)) {
            return self::Council;
        }

        if (stripos($name, 'commissie') !== false) {
            return self::Committee;
        }

        if (stripos($name, 'college') !== false) {
            return self::College;
        }

        return self::Other;
    }
}
