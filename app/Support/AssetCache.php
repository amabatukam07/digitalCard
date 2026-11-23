<?php

namespace App\Support;

class AssetCache
{
    /** @var array<string, string> */
    private static array $svgs = [];

    /** @var array<string, bool> */
    private static array $files = [];

    public static function svg(string $name): string
    {
        $resolved = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?: 'package';

        if (isset(self::$svgs[$resolved])) {
            return self::$svgs[$resolved];
        }

        $path = resource_path("svg/icons/{$resolved}.svg");
        if (! self::fileExists($path)) {
            $path = resource_path('svg/icons/package.svg');
        }

        return self::$svgs[$resolved] = self::fileExists($path) ? (string) file_get_contents($path) : '';
    }

    public static function fileExists(string $path): bool
    {
        if (! array_key_exists($path, self::$files)) {
            self::$files[$path] = is_file($path);
        }

        return self::$files[$path];
    }
}
