<?php
declare(strict_types=1);

// A small site folder for the tests: views (the template cache), lang files, templates.
require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/flocms-frontend-tests-' . getmypid();
foreach (['views/cache', 'lang', 'templates/default', 'storage/cache', 'storage/logs'] as $folder) {
    if (!is_dir($root . '/' . $folder)) { mkdir($root . '/' . $folder, 0777, true); }
}
foreach (['en' => 'ltr', 'ar' => 'rtl', 'ku' => 'rtl'] as $lang => $dir) {
    file_put_contents($root . '/lang/' . $lang . '.php', "<?php return ['lng.dir' => '$dir'];");
}
defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('ROOT') || define('ROOT', $root);
defined('VIEWS_PATH') || define('VIEWS_PATH', $root . '/views');
defined('TEMPLATES_PATH') || define('TEMPLATES_PATH', $root . '/templates');
defined('SITE_URI') || define('SITE_URI', 'https://example.test');

\FloCMS\Core\Config::set('languages', ['en', 'ar', 'ku']);
\FloCMS\Core\Config::set('default_language', 'en');
\FloCMS\Core\Config::set('default_route', 'default');
\FloCMS\Core\Config::set('routes', ['default' => '', 'admin' => 'admin_']);
\FloCMS\Core\Lang::load('en');

register_shutdown_function(static function () use ($root): void {
    exec('rm -rf ' . escapeshellarg($root));
});
