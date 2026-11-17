<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Assets;

use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Log;

/** Files the package generates in storage/cache/frontend, written so no request reads half a file. */
final class CacheFile
{
    /** Generated files are named "style-" or "bundle-" + 16 hex characters. */
    public const NAME = '/^(?:style|bundle)-[a-f0-9]{16}\.(?:css|js)$/D';

    public static function write(string $path, string $contents): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            Log::warning('Can\'t create ' . $directory . ': make storage/cache writable.');
            return false;
        }
        $temp = @tempnam($directory, 'fe');
        if ($temp === false || @file_put_contents($temp, $contents) === false || !@rename($temp, $path)) {
            if (is_string($temp) && is_file($temp)) { @unlink($temp); }
            Log::warning('Can\'t write ' . $path . ': make storage/cache/frontend writable.');
            return false;
        }
        @chmod($path, 0664);
        self::prune($directory, basename($path));
        return true;
    }

    public static function path(string $name): ?string
    {
        if (preg_match(self::NAME, $name) !== 1) { return null; }
        $path = Frontend::cachePath() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /** Old generated files are removed after a week, so the folder doesn't grow forever. */
    private static function prune(string $directory, string $keep): void
    {
        if (random_int(1, 20) !== 1) { return; }
        foreach (glob($directory . '/{style,bundle}-*.{css,js}', GLOB_BRACE) ?: [] as $file) {
            if (basename($file) !== $keep && (int) @filemtime($file) < time() - 7 * 86400) {
                @unlink($file);
            }
        }
    }
}
