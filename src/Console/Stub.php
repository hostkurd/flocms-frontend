<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\Frontend\Frontend;

/** The package's file templates (resources/stubs), with {{ name }} placeholders. */
final class Stub
{
    /** @param array<string, string> $replace */
    public static function render(string $stub, array $replace = []): string
    {
        $text = (string) file_get_contents(Frontend::path('resources/stubs/' . $stub . '.stub'));
        foreach ($replace as $key => $value) {
            $text = str_replace('{{ ' . $key . ' }}', $value, $text);
        }
        return $text;
    }

    /** Write a file that isn't there yet (never overwrites). False when it exists or can't be written. */
    public static function create(string $path, string $contents): bool
    {
        if (is_file($path)) {
            return false;
        }
        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            return false;
        }
        return @file_put_contents($path, $contents) !== false;
    }
}
