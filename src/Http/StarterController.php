<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Http;

use FloCMS\Core\Controller;
use FloCMS\Core\HttpException;
use FloCMS\Core\Http\Response;
use FloCMS\Core\Session;
use FloCMS\Core\TemplateEngine;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Starters\StarterSetup;
use FloCMS\Frontend\Starters\Starters;
use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Languages;

/**
 * /site-starter: the starter picker (D26). Only administrators (or anyone on
 * APP_ENV=local) see it, and only until a starter is chosen; everyone else
 * gets "not found". POST /site-starter/choose creates the chosen one.
 */
final class StarterController extends Controller
{
    public function index(): Response
    {
        if (!StarterSetup::pickerAllowed()) {
            throw new HttpException(404);
        }
        return Response::html(self::page())->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function choose(): Response
    {
        if (!$this->request->isMethod('POST') || !StarterSetup::pickerAllowed()) {
            throw new HttpException(404);
        }
        $name = (string) $this->request->input('starter', '');
        try {
            $result = StarterSetup::apply($name);
            $message = FrontendLang::get('starter.done') . ' ' . ($result['target'] === 'pages' ? FrontendLang::get('starter.in_pages') : FrontendLang::get('starter.in_view', ['file' => $result['where']]));
            if ($result['template']['changed']) {
                $message .= ' ' . FrontendLang::get('starter.template_on', ['template' => $result['template']['template']]);
            }
            if ($result['template']['problem'] !== null) {
                self::flash($message . ' ' . FrontendLang::get('starter.template_problem', ['template' => \FloCMS\Frontend\Support\SiteTemplate::NAME]), 'warning');
            } else {
                self::flash($message, 'success');
            }
        } catch (\RuntimeException $error) {
            self::flash(FrontendLang::get('starter.failed', ['reason' => $error->getMessage()]), 'danger');
            return Response::make('', 303)->header('Location', Languages::path('/site-starter'));
        }
        return Response::make('', 303)->header('Location', Languages::path('/'));
    }

    /** The picker's HTML: a page of its own, in the Site Style. */
    public static function page(): string
    {
        $starters = [];
        foreach (Starters::NAMES as $name) {
            $starters[] = ['name' => $name, 'label' => FrontendLang::get('starter.' . $name), 'text' => FrontendLang::get('starter.' . $name . '_text'), 'preview' => self::previewUrl($name)];
        }
        return TemplateEngine::renderFile(Frontend::path('resources/views/starter.html'), [
            'starters' => $starters,
            'action' => Languages::path('/site-starter/choose'),
            'target' => StarterSetup::pagesAvailable() ? 'pages' : 'view',
            'view_file' => Frontend::config()->starterView(),
            'app_url' => Frontend::suggestedAppUrl() === null ? null : ['current' => Frontend::siteUri(), 'suggested' => Frontend::suggestedAppUrl()],
        ]);
    }

    /** The starter's first screen in the page's language (en for any other), shot in the default Site Style. */
    public static function previewUrl(string $name): string
    {
        $lang = in_array(Languages::current(), ['ar', 'ku'], true) ? Languages::current() : 'en';
        return Frontend::sitePath() . '/site-assets/preview/' . $name . '-' . $lang . '-v1.webp';
    }

    private static function flash(string $message, string $type): void
    {
        try { Session::setFlash($message, $type); } catch (\Throwable) { /* no session */ }
    }
}
