<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Seo;

use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Languages;

/**
 * What a page tells search engines and social networks. Set it from a
 * controller; site_head() prints it:
 *
 *     Seo::title('Our plans')->description('…')->image('/img/plans.jpg');
 *
 * Without it, the site's name and description are used. On a flocms-admin
 * page (current_page()) the page's own settings are printed instead.
 */
final class Seo
{
    private static ?self $instance = null;

    private ?string $title = null;
    private ?string $description = null;
    private ?string $image = null;
    private string $type = 'website';
    private ?string $canonical = null;
    private bool $noindex = false;
    /** @var list<array<string, mixed>> */
    private array $jsonLd = [];
    /** @var list<array{label: string, url: string}> */
    private array $breadcrumbs = [];
    /** @var array<string, string>|null lang => address, for pages whose address differs per language (0.2) */
    private ?array $alternates = null;

    public static function page(): self
    {
        return self::$instance ??= new self();
    }

    public static function title(string $title): self { $page = self::page(); $page->title = trim($title); return $page; }
    public static function reset(): void { self::$instance = null; }

    public function description(string $description): self { $this->description = trim((string) preg_replace('/\s+/u', ' ', strip_tags($description))); return $this; }
    /** An image address or a site path; shown when the page is shared. */
    public function image(string $image): self { $this->image = $image; return $this; }
    /** "website", "article" or "product". */
    public function type(string $type): self { $this->type = in_array($type, ['website', 'article', 'product', 'profile'], true) ? $type : 'website'; return $this; }
    public function canonical(string $url): self { $this->canonical = $url; return $this; }
    /** Keep the page out of search results (account pages, form results). */
    public function noindex(bool $noindex = true): self { $this->noindex = $noindex; return $this; }
    /** @param array<string, mixed> $data one JSON-LD object (with @type) */
    public function jsonLd(array $data): self { $this->jsonLd[] = $data; return $this; }
    /** @param list<array{label: string, url: string}> $trail */
    public function breadcrumbs(array $trail): self { $this->breadcrumbs = $trail; return $this; }

    /**
     * The page's address in each language it is written in (a post with its
     * own slug per language). hreflang lists only these; x-default is the
     * default language's, or the first.
     *
     * @param array<string, string> $urls lang => address
     */
    public function alternates(array $urls): self { $this->alternates = $urls; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function isNoindex(): bool { return $this->noindex; }
    /** @return list<array{label: string, url: string}> */
    public function getBreadcrumbs(): array { return $this->breadcrumbs; }

    /** The tags for <head>. */
    public static function tags(): string
    {
        $page = self::page();
        $site = Frontend::config()->site();
        $adminPage = function_exists('current_page') && current_page() !== null;
        $html = '';
        if ($adminPage) {
            // The admin's page settings: title, description, canonical, hreflang, social tags, robots
            $html .= (string) page_meta() . "\n";
        } else {
            $title = $page->title !== null && $page->title !== '' ? $page->title . ' – ' . $site['name'] : $site['name'];
            $description = $page->description ?? $site['description'];
            $canonical = $page->canonical ?? self::currentUrl();
            $html .= '<title>' . Html::e($title) . '</title>' . "\n";
            if ($description !== '') {
                $html .= '<meta name="description" content="' . Html::e($description) . '">' . "\n";
            }
            if ($page->noindex) {
                $html .= '<meta name="robots" content="noindex, follow">' . "\n";
            }
            $html .= '<link rel="canonical" href="' . Html::e($canonical) . '">' . "\n";
            $languages = Languages::all();
            if ($page->alternates !== null && count($page->alternates) > 1 && !$page->noindex) {
                foreach ($page->alternates as $lang => $url) {
                    $html .= '<link rel="alternate" hreflang="' . Html::e($lang) . '" href="' . Html::e(self::absolute($url)) . '">' . "\n";
                }
                $default = $page->alternates[Languages::default()] ?? reset($page->alternates);
                $html .= '<link rel="alternate" hreflang="x-default" href="' . Html::e(self::absolute((string) $default)) . '">' . "\n";
            } elseif ($page->alternates === null && count($languages) > 1 && !$page->noindex) {
                foreach ($languages as $lang) {
                    $html .= '<link rel="alternate" hreflang="' . Html::e($lang) . '" href="' . Html::e(self::withoutQuery(Languages::switchUrl($lang))) . '">' . "\n";
                }
                $html .= '<link rel="alternate" hreflang="x-default" href="' . Html::e(self::withoutQuery(Languages::switchUrl(Languages::default()))) . '">' . "\n";
            }
            $image = $page->image ?? $site['logo'];
            $html .= '<meta property="og:type" content="' . Html::e($page->type) . '">' . "\n";
            $html .= '<meta property="og:title" content="' . Html::e($page->title ?? $site['name']) . '">' . "\n";
            $html .= '<meta property="og:site_name" content="' . Html::e($site['name']) . '">' . "\n";
            $html .= '<meta property="og:url" content="' . Html::e($canonical) . '">' . "\n";
            $html .= '<meta property="og:locale" content="' . Html::e(str_replace('-', '_', Frontend::config()->locale(Languages::current()))) . '">' . "\n";
            if ($description !== '') {
                $html .= '<meta property="og:description" content="' . Html::e($description) . '">' . "\n";
            }
            if ($image !== null && $image !== '') {
                $html .= '<meta property="og:image" content="' . Html::e(self::absolute($image)) . '">' . "\n";
                $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
            } else {
                $html .= '<meta name="twitter:card" content="summary">' . "\n";
            }
        }
        foreach (self::structuredData() as $data) {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            if (is_string($json)) {
                $html .= '<script type="application/ld+json">' . $json . '</script>' . "\n";
            }
        }
        return $html;
    }

    /** @return list<array<string, mixed>> Organization, WebSite, BreadcrumbList (when breadcrumbs are shown) and the page's own */
    public static function structuredData(): array
    {
        $page = self::page();
        $site = Frontend::config()->site();
        $home = Languages::url('/');
        $organization = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $site['name'], 'url' => $home];
        if ($site['logo'] !== null) { $organization['logo'] = self::absolute($site['logo']); }
        if ($site['email'] !== '') { $organization['email'] = $site['email']; }
        if ($site['social'] !== []) { $organization['sameAs'] = array_values($site['social']); }
        $data = [$organization, ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site['name'], 'url' => $home, 'inLanguage' => Languages::current()]];
        if (count($page->breadcrumbs) > 1) {
            $items = [];
            foreach ($page->breadcrumbs as $i => $crumb) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb['label'], 'item' => self::absolute($crumb['url'])];
            }
            $data[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        foreach ($page->jsonLd as $extra) {
            $data[] = ['@context' => 'https://schema.org'] + $extra;
        }
        return $data;
    }

    /** The page's address without tracking parameters. */
    private static function currentUrl(): string
    {
        return self::withoutQuery(Languages::switchUrl(Languages::current()));
    }

    private static function withoutQuery(string $url): string
    {
        $question = strpos($url, '?');
        return $question === false ? $url : substr($url, 0, $question);
    }

    /** A full address for a path ("/img/a.jpg" → https://site/img/a.jpg). */
    public static function absolute(string $url): string
    {
        if (preg_match('#^https?://#i', $url) === 1) { return $url; }
        $site = Frontend::siteUri();
        $origin = preg_match('#^(https?://[^/]+)#i', $site, $m) === 1 ? $m[1] : $site;
        if (str_starts_with($url, '/')) {
            // A path under the site's own folder keeps it (SITE_URI may include one)
            $base = rtrim((string) parse_url($site, PHP_URL_PATH), '/');
            return $origin . ($base !== '' && !str_starts_with($url, $base . '/') ? $base : '') . $url;
        }
        return $site . '/' . $url;
    }
}
