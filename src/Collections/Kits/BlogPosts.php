<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Kits;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Item;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Seo\Seo;

/**
 * The blog kit's posts (D36). A site's class extends it, so the kit's
 * fields, pages and fixes come with each update:
 *
 *     final class Posts extends BlogPosts {}   // collections/Posts.php
 *
 * Change $path, add fields (call parent::fields()) or pages of your own.
 */
abstract class BlogPosts extends Collection
{
    protected static string $name = 'posts';
    protected static string|array $path = 'blog';
    protected static string $kit = 'blog';
    protected static string|array|null $label = 'frontend.collections.blog';
    protected static string|array|null $lead = 'frontend.collections.blog_lead';
    protected static bool $feed = true;
    protected static string $date = 'publish_at';
    /** The class of its categories. */
    protected static string $categories = 'App\\Collections\\PostCategories';
    /** Show the author's name (Q2). Off by default. */
    protected static bool $author = false;

    public function fields(): array
    {
        return [
            Prop::text('title')->required()->translatable()->searchable()->max(190),
            Prop::slug('slug')->from('title')->translatable(),
            Prop::textarea('excerpt')->translatable()->searchable()->max(300)->help('frontend.collections.help.excerpt'),
            Prop::richText('body')->translatable()->searchable(),
            Prop::image('cover'),
            Prop::select('category')->from(static::$categories)->filterable(),
            Prop::tags('tags')->translatable()->filterable(),
            Prop::toggle('featured'),
            Prop::text('meta_title')->translatable()->max(190),
            Prop::textarea('meta_description')->translatable()->max(300),
        ];
    }

    public function groups(): array
    {
        return ['category' => 'category', 'tag' => 'tags'];
    }

    public static function showsAuthor(): bool
    {
        return static::$author;
    }

    public function jsonLd(Item $item): ?array
    {
        $data = ['@type' => 'BlogPosting', 'headline' => $item->title(), 'url' => Seo::absolute($item->url()), 'inLanguage' => \FloCMS\Frontend\Support\Languages::current()];
        if ($item->date() !== null) {
            $data['datePublished'] = date('c', (int) strtotime((string) $item->date()));
        }
        $updated = $item->raw('updated_at');
        if (is_string($updated) && $updated !== '') {
            $data['dateModified'] = date('c', (int) strtotime($updated));
        }
        $image = $item->image();
        if ($image !== null) {
            $data['image'] = Seo::absolute($image['src']);
        }
        $excerpt = $item->get('excerpt');
        if (is_string($excerpt) && $excerpt !== '') {
            $data['description'] = $excerpt;
        }
        return $data;
    }
}
