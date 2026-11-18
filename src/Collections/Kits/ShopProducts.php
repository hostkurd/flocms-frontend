<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Kits;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Item;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Seo;

/**
 * The shop kit's products (D37): a catalogue, no cart (D24). Prices in the
 * site's currency (config/frontend.php 'shop'), "Price on request" when
 * empty; "Ask about this product" opens WhatsApp or the contact page (Q4).
 *
 *     final class Products extends ShopProducts {}   // collections/Products.php
 *
 * Add a filter of your own with a filterable select:
 *
 *     public function fields(): array
 *     {
 *         return [...parent::fields(), Prop::select('material', ['oak' => 'Oak', 'linen' => 'Linen'])->filterable()];
 *     }
 */
abstract class ShopProducts extends Collection
{
    public const AVAILABILITY = ['in_stock' => 'frontend.collections.in_stock', 'made_to_order' => 'frontend.collections.made_to_order', 'out_of_stock' => 'frontend.collections.out_of_stock'];

    protected static string $name = 'products';
    protected static string|array $path = 'shop';
    protected static string $kit = 'shop';
    protected static string|array|null $label = 'frontend.collections.shop';
    protected static string|array|null $lead = 'frontend.collections.shop_lead';
    protected static string $date = 'publish_at';
    protected static string $categories = 'App\\Collections\\ProductCategories';

    public function fields(): array
    {
        return [
            Prop::text('title')->required()->translatable()->searchable()->max(190),
            Prop::slug('slug')->from('title')->translatable(),
            Prop::textarea('summary')->translatable()->searchable()->max(300),
            Prop::richText('body')->translatable(),
            Prop::images('images'),
            Prop::price('price')->filterable(),
            Prop::select('availability', self::AVAILABILITY)->default('in_stock')->filterable(),
            Prop::select('category')->from(static::$categories)->filterable(),
            Prop::pairs('specs')->translatable(),
            Prop::toggle('featured'),
            Prop::text('meta_title')->translatable()->max(190),
            Prop::textarea('meta_description')->translatable()->max(300),
        ];
    }

    public function groups(): array
    {
        return ['category' => 'category'];
    }

    public function sorts(): array
    {
        return [
            'newest' => ['publish_at', 'DESC'],
            'price_low' => ['price', 'ASC'],
            'price_high' => ['price', 'DESC'],
            'name' => ['title', 'ASC'],
        ];
    }

    /** The price as shown: "$240.00", or "Price on request". */
    public static function priceText(Item $item): string
    {
        $price = $item->get('price');
        if ($price === null) {
            return \FloCMS\Frontend\Support\FrontendLang::get('collections.on_request');
        }
        $shop = Frontend::config()->shop();
        return \FloCMS\Frontend\Support\Format::money($price, $shop['currency'], $shop['decimals']);
    }

    public function jsonLd(Item $item): ?array
    {
        $data = ['@type' => 'Product', 'name' => $item->title(), 'url' => Seo::absolute($item->url())];
        $summary = $item->get('summary');
        if (is_string($summary) && $summary !== '') {
            $data['description'] = $summary;
        }
        $images = $item->get('images');
        if (is_array($images) && $images !== []) {
            $data['image'] = array_map(static fn (array $i): string => Seo::absolute($i['src']), $images);
        }
        $price = $item->get('price');
        if ($price !== null) {
            $availability = ['in_stock' => 'InStock', 'made_to_order' => 'PreOrder', 'out_of_stock' => 'OutOfStock'][(string) $item->get('availability')] ?? 'InStock';
            $data['offers'] = ['@type' => 'Offer', 'price' => $price, 'priceCurrency' => Frontend::config()->shop()['currency'], 'availability' => 'https://schema.org/' . $availability, 'url' => Seo::absolute($item->url())];
        }
        return $data;
    }
}
