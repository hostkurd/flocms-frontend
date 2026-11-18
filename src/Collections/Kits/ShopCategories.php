<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Kits;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Components\Prop;

/** The shop kit's categories (D34): up to 3 levels (Lighting → Pendants), with a photo for "Shop by category". */
abstract class ShopCategories extends Collection
{
    protected static string $name = 'product_categories';
    protected static string $kit = 'shop';
    protected static string|array|null $label = 'frontend.collections.categories';
    protected static bool $publishing = false;
    protected static string $date = '';

    public function fields(): array
    {
        return [
            Prop::text('title')->required()->translatable()->max(120),
            Prop::slug('slug')->from('title')->translatable(),
            Prop::textarea('description')->translatable()->max(300),
            Prop::image('image'),
            Prop::select('parent')->from(static::class),
        ];
    }

    public function sorts(): array
    {
        return ['order' => ['sort', 'ASC']];
    }
}
