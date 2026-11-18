<?php
declare(strict_types=1);

namespace FloCMS\Frontend;

use FloCMS\Core\Env;

/**
 * config/frontend.php, read once, with defaults for every key. A wrong value
 * falls back to the default, so a typo never breaks the site
 * (php flo site:doctor reports it).
 */
final class FrontendConfig
{
    /** @var list<string> */
    private array $problems = [];

    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * The site's name, short description, logo and social links, for the
     * header, the footer and the page head (Open Graph, JSON-LD).
     *
     * @return array{name: string, description: string, logo: ?string, email: string, phone: string, address: string, social: array<string, string>}
     */
    public function site(): array
    {
        $site = is_array($this->values['site'] ?? null) ? $this->values['site'] : [];
        $string = static fn (mixed $value, string $default = ''): string => is_scalar($value) ? trim((string) $value) : $default;
        $social = [];
        foreach ((array) ($site['social'] ?? []) as $network => $url) {
            if (is_string($network) && is_string($url) && preg_match('#^https?://#i', $url) === 1) {
                $social[strtolower($network)] = $url;
            }
        }
        $logo = $site['logo'] ?? null;
        return [
            'name' => $string($site['name'] ?? null) ?: $string(Env::get('SITE_NAME'), '') ?: $string(Env::get('APP_NAME'), 'FloCMS'),
            'description' => $string($site['description'] ?? null),
            'logo' => is_string($logo) && $logo !== '' ? $logo : null,
            'email' => $string($site['email'] ?? null) ?: $string(Env::get('PRIMARY_EMAIL')),
            'phone' => $string($site['phone'] ?? null),
            'address' => $string($site['address'] ?? null),
            'social' => $social,
        ];
    }

    /** @return array<string, callable> menu name => fn (Menu $menu) */
    public function menus(): array
    {
        $menus = [];
        foreach ((array) ($this->values['menus'] ?? []) as $name => $menu) {
            if (is_string($name) && is_callable($menu) && !is_string($menu)) {
                $menus[$name] = $menu;
            } else {
                $this->problems[] = "menus.$name must be a function: fn (Menu \$menu) => \$menu->link(…)";
            }
        }
        return $menus;
    }

    /**
     * Digits per language (D10): 'latin' (default) or 'arabic' (Arabic-Indic).
     */
    public function digits(string $lang): string
    {
        $digits = (array) ($this->values['digits'] ?? []);
        $value = $digits[$lang] ?? 'latin';
        return in_array($value, ['latin', 'arabic'], true) ? $value : 'latin';
    }

    /** The locale for PHP's intl per language (Kurdish Sorani is ckb in intl). */
    public function locale(string $lang): string
    {
        $defaults = ['en' => 'en_US', 'ar' => 'ar_IQ', 'ku' => 'ckb_IQ'];
        $set = (array) ($this->values['locales'] ?? []);
        $locale = $set[$lang] ?? $defaults[$lang] ?? $lang;
        return is_string($locale) && preg_match('/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $locale) === 1 ? $locale : $lang;
    }

    /**
     * The cookie notice (needed, statistics, marketing).
     *
     * @return array{notice: string, categories: list<string>, policy: ?string, days: int}
     */
    public function cookies(): array
    {
        $cookies = is_array($this->values['cookies'] ?? null) ? $this->values['cookies'] : [];
        $notice = $cookies['notice'] ?? 'auto';
        if ($notice === true) { $notice = 'on'; }
        if ($notice === false) { $notice = 'off'; }
        if (!in_array($notice, ['auto', 'on', 'off'], true)) {
            $this->problems[] = "cookies.notice must be 'auto', 'on' or 'off'";
            $notice = 'auto';
        }
        $categories = array_values(array_intersect((array) ($cookies['categories'] ?? ['statistics', 'marketing']), ['statistics', 'marketing']));
        $policy = $cookies['policy'] ?? null;
        $days = (int) ($cookies['days'] ?? 180);
        return [
            'notice' => $notice,
            'categories' => $categories,
            'policy' => is_string($policy) && $policy !== '' ? $policy : null,
            'days' => max(1, min(395, $days)),
        ];
    }

    /** Component classes the site adds besides its components/ folder. @return list<string> */
    public function components(): array
    {
        return array_values(array_filter((array) ($this->values['components'] ?? []), 'is_string'));
    }

    /** The site's collections (0.2): classes, usually App\Collections\<Name>. @return list<string> */
    public function collections(): array
    {
        return array_values(array_filter((array) ($this->values['collections'] ?? []), 'is_string'));
    }

    /**
     * The shop's settings (D38, Q3, Q4): its currency, and where "Ask about
     * this product" goes: a WhatsApp number (preferred) or a page.
     *
     * @return array{currency: string, decimals: int, whatsapp: string, ask_url: string}
     */
    public function shop(): array
    {
        $shop = is_array($this->values['shop'] ?? null) ? $this->values['shop'] : [];
        $currency = is_string($shop['currency'] ?? null) && preg_match('/^[A-Z]{3}$/', $shop['currency']) === 1 ? $shop['currency'] : 'USD';
        if (isset($shop['currency']) && $currency !== $shop['currency']) {
            $this->problems[] = 'shop.currency must be a three-letter code such as USD or IQD';
        }
        $decimals = is_int($shop['decimals'] ?? null) ? max(0, min(3, $shop['decimals'])) : 2;
        $ask = is_array($shop['ask'] ?? null) ? $shop['ask'] : [];
        $whatsapp = is_scalar($ask['whatsapp'] ?? null) ? (string) preg_replace('/\D+/', '', (string) $ask['whatsapp']) : '';
        $url = is_string($ask['url'] ?? null) ? trim($ask['url']) : '/contact';
        return ['currency' => $currency, 'decimals' => $decimals, 'whatsapp' => $whatsapp, 'ask_url' => $url];
    }

    /** Whether the starter picker may be shown (D26). */
    public function starter(): bool
    {
        return ($this->values['starter'] ?? true) !== false;
    }

    /**
     * Where a starter's home page goes without flocms-admin's Pages
     * (views/pages/index.html, the skeleton's home page view).
     */
    public function starterView(): string
    {
        $view = $this->values['starter_view'] ?? 'views/pages/index.html';
        return is_string($view) && $view !== '' && !str_contains($view, '..') ? ltrim($view, '/') : 'views/pages/index.html';
    }

    /** @return list<string> what config/frontend.php has wrong (for site:doctor) */
    public function problems(): array
    {
        $this->menus();
        $this->cookies();
        $this->shop();
        return array_values(array_unique($this->problems));
    }
}
