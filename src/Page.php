<?php
declare(strict_types=1);

namespace FloCMS\Frontend;

use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Seo\Scripts;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Style\Theme;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Languages;

/**
 * The three calls a layout makes:
 *
 *     {!! site_html() !!}
 *     <head>{!! site_head() !!}</head>
 *     <body> … {!! site_foot() !!}</body>
 *     </html>
 */
final class Page
{
    /**
     * The <html> tag: lang and dir from the page's language, the font of its
     * script, and the visitor's light/dark choice.
     *
     * @param array<string, mixed> $attributes more attributes (class is added to)
     */
    public static function html(array $attributes = []): string
    {
        $lang = Languages::current();
        $classes = ['fe-no-js', 'fe-script-' . Languages::script($lang)];
        if (isset($attributes['class'])) {
            $classes[] = (string) $attributes['class'];
            unset($attributes['class']);
        }
        $theme = Theme::chosen();
        return '<!doctype html>' . "\n" . '<html' . Html::attributes(['lang' => $lang, 'dir' => Languages::dir($lang), 'class' => $classes, 'data-theme' => $theme] + $attributes) . '>';
    }

    /** Everything <head> needs: charset, viewport, SEO tags, the Site Style and the page's component CSS. */
    public static function head(): string
    {
        self::checkOpenComponents();
        $html = '<meta charset="utf-8">' . "\n" . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        // Tells the components' CSS that scripts run (the slider, the dialog), before anything is drawn
        $html .= '<script>document.documentElement.classList.replace("fe-no-js","fe-js")</script>' . "\n";
        $html .= Seo::tags();
        $palette = Style::palette();
        $html .= '<meta name="theme-color" content="' . Html::e($palette['light']['background']) . '"' . (Style::values()['dark'] === 'light' ? '' : ' media="(prefers-color-scheme: light)"') . '>' . "\n";
        if (Style::values()['dark'] !== 'light') {
            $html .= '<meta name="theme-color" content="' . Html::e($palette['dark']['background']) . '" media="(prefers-color-scheme: dark)">' . "\n";
        }
        $favicon = Frontend::config()->all()['site']['favicon'] ?? null;
        if (is_string($favicon) && $favicon !== '') {
            $html .= '<link rel="icon" href="' . Html::e(Html::safeUrl($favicon)) . '">' . "\n";
        }
        $html .= Assets::head();
        // flocms-admin's block styles, when Builder blocks are on the page
        if (function_exists('blocks_assets')) {
            $html .= (string) blocks_assets();
        }
        return $html;
    }

    /** A @component without its @endcomponent loses its content: an error with APP_DEBUG on, a log line otherwise. */
    private static function checkOpenComponents(): void
    {
        $open = \FloCMS\Frontend\Components\Slots::open();
        if ($open === []) { return; }
        $message = '@component(\'' . implode('\'), @component(\'', $open) . '\') has no @endcomponent: its content is not shown.';
        if (Frontend::debug()) {
            \FloCMS\Frontend\Components\Slots::reset();
            throw new \FloCMS\Frontend\Components\ComponentException($message);
        }
        \FloCMS\Frontend\Support\Log::warning($message);
    }

    /** The end of <body>: the cookie notice, scripts allowed by it, and the components' combined script. */
    public static function foot(): string
    {
        self::checkOpenComponents();
        $html = '';
        if (Scripts::showNotice()) {
            $html .= Components::render('cookie-notice');
        }
        $html .= Scripts::html();
        $html .= Assets::foot();
        return $html;
    }
}
