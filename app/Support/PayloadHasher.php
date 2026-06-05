<?php

namespace App\Support;

class PayloadHasher
{
    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode(self::sortRecursive($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function sortRecursive(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortRecursive($value);
            }
        }

        return $data;
    }
}
