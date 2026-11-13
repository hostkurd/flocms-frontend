<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Http;

use FloCMS\Core\Controller;
use FloCMS\Core\HttpException;
use FloCMS\Core\Http\Response;
use FloCMS\Frontend\Assets\CacheFile;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Style\StyleCss;

/**
 * /site-assets/file/<name>: the generated Site Style and component files
 * (storage/cache/frontend), and /site-assets/font/<name>: the package's
 * fonts. Names change with their contents, so they are cached for a year.
 */
final class AssetController extends Controller
{
    public const FONTS = ['instrument-sans-latin-v1.woff2', 'instrument-sans-latin-ext-v1.woff2', 'noto-sans-arabic-v1.woff2'];

    public function file(): Response
    {
        $name = $this->name();
        $path = CacheFile::path($name);
        if ($path !== null) {
            return self::send((string) file_get_contents($path), str_ends_with($name, '.js') ? 'application/javascript; charset=utf-8' : 'text/css; charset=utf-8');
        }
        // The cache folder can't be written: the current style is built for each request
        if ($name === Style::fileName()) {
            return self::send(StyleCss::build(), 'text/css; charset=utf-8')->header('Cache-Control', 'public, max-age=300');
        }
        // … and a page's component files from the names in the address (?c=hero,slider)
        if (preg_match('/^bundle-[a-f0-9]{16}\.(css|js)$/D', $name, $m) === 1) {
            $names = array_filter(explode(',', (string) $this->request->queryValue('c', '')), static fn (string $n): bool => preg_match('/^[a-z][a-z0-9-]{0,35}$/', $n) === 1);
            $files = \FloCMS\Frontend\Assets\Assets::filesFor(array_values($names), $m[1], $name);
            if ($files !== null) {
                return self::send(\FloCMS\Frontend\Assets\Assets::bundleContents($files, $m[1]), $m[1] === 'js' ? 'application/javascript; charset=utf-8' : 'text/css; charset=utf-8')->header('Cache-Control', 'public, max-age=300');
            }
        }
        throw new HttpException(404);
    }

    public function font(): Response
    {
        $name = $this->name();
        if (!in_array($name, self::FONTS, true)) {
            throw new HttpException(404);
        }
        $path = dirname(__DIR__, 2) . '/resources/fonts/' . $name;
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        return self::send((string) file_get_contents($path), 'font/woff2')->header('Access-Control-Allow-Origin', '*');
    }

    private function name(): string
    {
        if (!$this->request->isMethod('GET', 'HEAD')) {
            throw new HttpException(405);
        }
        $params = $this->getParams();
        $name = is_array($params) && count($params) === 1 ? $params[0] : null;
        if (!is_string($name)) {
            throw new HttpException(404);
        }
        return $name;
    }

    private static function send(string $body, string $type): Response
    {
        return Response::make($body)
            ->header('Content-Type', $type)
            ->header('Cache-Control', 'public, max-age=31536000, immutable')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
