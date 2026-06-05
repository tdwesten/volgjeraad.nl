<?php

namespace App\Support;

use RuntimeException;

class PromptRepository
{
    public static function load(string $key, string $version): string
    {
        $path = resource_path("prompts/{$key}.{$version}.md");

        if (! file_exists($path)) {
            throw new RuntimeException("Prompt file not found: {$path}");
        }

        return file_get_contents($path);
    }

    public static function version(): string
    {
        return (string) config('volgjeraad.ai.prompt_version', 'v1');
    }
}
