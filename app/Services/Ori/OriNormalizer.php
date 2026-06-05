<?php

namespace App\Services\Ori;

class OriNormalizer
{
    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public static function meeting(string $id, array $source): array
    {
        return [
            'ori_id' => $id,
            'name' => $source['name'] ?? null,
            'start_date' => $source['start_date'] ?? null,
            'status' => $source['motion_event_status'] ?? $source['status'] ?? null,
            'source_url' => $source['based_on_uri'] ?? $source['source_url'] ?? null,
            'committee_ori_id' => isset($source['committee']) ? self::firstId($source['committee']) : null,
            'agenda_ids' => self::ids($source['agenda'] ?? null),
            'raw_payload' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public static function agendaItem(string $id, array $source): array
    {
        return [
            'ori_id' => $id,
            'name' => $source['name'] ?? $source['description'] ?? null,
            'position' => $source['position'] ?? null,
            'attachment_ids' => self::ids($source['attachment'] ?? null),
            'raw_payload' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public static function mediaObject(string $id, array $source): array
    {
        $mdText = self::joinTextArray($source['media_type']['md_text'] ?? $source['md_text'] ?? null);
        $text = self::joinTextArray($source['media_type']['text'] ?? $source['text'] ?? null);
        $hasText = $mdText !== null && $mdText !== '';

        $textMissingReason = null;
        if (! $hasText) {
            $textMissingReason = $source['text_missing_reason'] ?? ($text === '' || $text === null ? 'ori_text_empty' : null);
        }

        return [
            'ori_id' => $id,
            'name' => $source['name'] ?? null,
            'position' => $source['position'] ?? null,
            'file_name' => $source['identifier'] ?? null,
            'content_type' => $source['format'] ?? $source['content_type'] ?? null,
            'size_in_bytes' => $source['byte_size'] ?? $source['size_in_bytes'] ?? null,
            'url' => $source['access_url'] ?? $source['url'] ?? null,
            'original_url' => $source['download_url'] ?? $source['original_url'] ?? $source['access_url'] ?? $source['url'] ?? null,
            'text' => $text,
            'md_text' => $mdText,
            'has_text' => $hasText,
            'text_missing_reason' => $textMissingReason,
            'raw_payload' => $source,
        ];
    }

    /**
     * Normalise a reference field to an array of ORI IDs.
     *
     * @param  string|array<mixed>|null  $refs
     * @return array<string>
     */
    public static function ids(string|array|null $refs): array
    {
        if ($refs === null) {
            return [];
        }

        if (is_string($refs)) {
            return [$refs];
        }

        // JSON-LD @list wrapper: {"@list": ["id1", "id2"]}
        if (isset($refs['@list'])) {
            return self::ids($refs['@list']);
        }

        $result = [];
        foreach ($refs as $ref) {
            if (is_string($ref)) {
                $result[] = $ref;
            } elseif (is_array($ref) && isset($ref['@id'])) {
                $result[] = $ref['@id'];
            }
        }

        return $result;
    }

    public static function organizationName(array $source): ?string
    {
        $committee = $source['committee'] ?? null;

        if (is_array($committee)) {
            return $committee['name'] ?? null;
        }

        return null;
    }

    private static function firstId(mixed $ref): ?string
    {
        $ids = self::ids($ref);

        return $ids[0] ?? null;
    }

    private static function joinTextArray(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $joined = implode("\n\n", array_filter($value, fn ($v) => is_string($v) && $v !== ''));

            return $joined !== '' ? $joined : null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
