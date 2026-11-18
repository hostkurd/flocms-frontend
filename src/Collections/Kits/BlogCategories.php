<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Kits;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Components\Prop;

/** The blog kit's categories (D34): flat, with pages at /<lang>/blog/category/<slug>. */
abstract class BlogCategories extends Collection
{
    protected static string $name = 'post_categories';
    protected static string $kit = 'blog';
    protected static string|array|null $label = 'frontend.collections.categories';
    protected static bool $publishing = false;
    protected static string $date = '';

    public function fields(): array
    {
        return [
            Prop::text('title')->required()->translatable()->max(120),
            Prop::slug('slug')->from('title')->translatable(),
            Prop::textarea('description')->translatable()->max(300),
        ];
    }

    public function sorts(): array
    {
        return ['order' => ['sort', 'ASC']];
    }
}
