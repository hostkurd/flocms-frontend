<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Starters;

use FloCMS\Core\App;
use FloCMS\Frontend\Frontend;

/**
 * Creates a starter home page, once (D25, D26).
 *
 *   - flocms-admin's Pages on, and no home page yet: a published home page
 *     in Site → Pages, built from Builder blocks (one per section).
 *   - Otherwise: views/pages/index.html (config 'starter_view'), built from
 *     @component calls. The skeleton's welcome page that was there is kept
 *     as views/pages/index.welcome.html.
 *
 * The choice is kept in storage/frontend/starter.json, so the picker is
 * shown only once.
 */
final class StarterSetup
{
    /**
     * @return array{target: string, where: string} target "pages" or "view"; where: the page id or the file
     * @throws \RuntimeException when nothing could be created
     */
    public static function apply(string $name, bool $force = false): array
    {
        if (!in_array($name, Starters::NAMES, true)) {
            throw new \RuntimeException('There is no starter "' . $name . '". Starters: ' . implode(', ', Starters::NAMES) . '.');
        }
        if (!$force && self::chosen() !== null) {
            throw new \RuntimeException('A starter was already chosen (' . self::chosen()['name'] . '). Use --force to create another.');
        }
        $result = self::pagesAvailable() ? self::intoPages($name, $force) : self::intoView($name, $force);
        self::remember($name, $result);
        return $result;
    }

    /** @return array{name: string, target: string, where: string, time: int}|null */
    public static function chosen(): ?array
    {
        $file = self::markerFile();
        if (!is_file($file)) { return null; }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) && is_string($data['name'] ?? null) ? $data + ['target' => '', 'where' => '', 'time' => 0] : null;
    }

    /** Whether the picker may be shown to this visitor: an administrator, or APP_ENV=local (D26). */
    public static function pickerAllowed(): bool
    {
        if (!Frontend::config()->starter() || self::chosen() !== null) {
            return false;
        }
        if (\FloCMS\Core\Env::get('APP_ENV') === 'local') {
            return true;
        }
        try {
            if (!isset($_SESSION) || !\FloCMS\Core\Session::get('isloggedin') || !\FloCMS\Core\Session::get('admin_access')) {
                return false;
            }
            // As the admin panel does: the account's role and status read again, so a suspended or demoted admin is out
            if (!\FloCMS\Core\Auth::refresh() || !\FloCMS\Core\Session::get('admin_access')) {
                return false;
            }
            return \FloCMS\Core\Auth::can('settings.manage');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Whether flocms-admin's Pages are on and their table is there. */
    public static function pagesAvailable(): bool
    {
        if (!class_exists(\FloCMS\Admin\Pages\Pages::class)) { return false; }
        try {
            if (!\FloCMS\Admin\Pages\Pages::enabled()) { return false; }
            $db = App::db();
            if ($db === null) { return false; }
            $db->table(\FloCMS\Admin\Pages\Pages::table())->select(['id'])->limit(1)->get();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{target: string, where: string} */
    private static function intoPages(string $name, bool $force): array
    {
        $db = App::db();
        $table = \FloCMS\Admin\Pages\Pages::table();
        $home = $db->table($table)->select(['id'])->where('is_home', '=', 1)->first();
        if ($home !== null && !$force) {
            throw new \RuntimeException('The site already has a home page in Site → Pages.');
        }
        $titles = [];
        $slugs = [];
        foreach (\FloCMS\Frontend\Support\Languages::all() as $lang) {
            $titles[$lang] = \FloCMS\Frontend\Support\FrontendLang::file($lang)['menu.home'] ?? 'Home';
            $slugs[$lang] = 'home';
        }
        $now = date('Y-m-d H:i:s');
        if ($home !== null) {
            $db->table($table)->where('id', '=', (int) $home->id)->update(['is_home' => 0]);
        }
        $id = $db->table($table)->insert([
            'title' => json_encode($titles, JSON_UNESCAPED_UNICODE),
            'slug' => json_encode($slugs),
            'type' => 'builder',
            'blocks' => json_encode(Starters::blocks($name), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_home' => 1,
            'menu' => 0,
            'sort' => 0,
            'noindex' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (method_exists(\FloCMS\Admin\Pages\Pages::class, 'reset')) { \FloCMS\Admin\Pages\Pages::reset(); }
        return ['target' => 'pages', 'where' => (string) $id];
    }

    /** @return array{target: string, where: string} */
    private static function intoView(string $name, bool $force): array
    {
        $relative = Frontend::config()->starterView();
        $file = Frontend::root() . '/' . $relative;
        if (is_file($file)) {
            $current = (string) file_get_contents($file);
            $ours = str_contains($current, '@component(');
            if ($ours && !$force) {
                throw new \RuntimeException($relative . ' is already built from components. Use --force to replace it.');
            }
            if (!$ours) {
                // The skeleton's welcome page (or the site's own), kept
                $keep = preg_replace('/\.html$/', '', $file) . '.welcome.html';
                if (!is_file($keep) && !@copy($file, $keep)) {
                    throw new \RuntimeException('Could not keep a copy of ' . $relative . '.');
                }
            }
        }
        if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0775, true)) {
            throw new \RuntimeException('Could not create ' . dirname($relative) . '/.');
        }
        if (@file_put_contents($file, Starters::template($name)) === false) {
            throw new \RuntimeException('Could not write ' . $relative . '.');
        }
        return ['target' => 'view', 'where' => $relative];
    }

    /** @param array{target: string, where: string} $result */
    private static function remember(string $name, array $result): void
    {
        $file = self::markerFile();
        if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0775, true); }
        @file_put_contents($file, json_encode(['name' => $name, 'time' => time()] + $result, JSON_PRETTY_PRINT) . "\n");
    }

    private static function markerFile(): string
    {
        return Frontend::root() . '/storage/frontend/starter.json';
    }
}
