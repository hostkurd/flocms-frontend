<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

use FloCMS\Core\App;

/** One line in the site's log (core's logger), never an error on the page. */
final class Log
{
    /** @var array<string, true> lines already written in this request */
    private static array $written = [];

    public static function warning(string $message): void
    {
        if (isset(self::$written[$message])) { return; }
        self::$written[$message] = true;
        try {
            App::logger()->warning('[flocms-frontend] ' . $message);
        } catch (\Throwable) {
            error_log('[flocms-frontend] ' . $message);
        }
    }
}
