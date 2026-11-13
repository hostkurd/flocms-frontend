<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Seo\Seo;

/** Questions and answers: a hairline list with +, and FAQPage JSON-LD for search engines. */
final class Faq extends Component
{
    protected static string $name = 'faq';
    protected static string $icon = 'info';
    protected static string $category = 'content';
    protected static bool $block = true;
    // Its FAQPage JSON-LD is added while it renders: never from the Builder's cache
    protected static bool $cacheable = false;

    public function props(): array
    {
        return [
            Prop::text('heading')->translatable(),
            Prop::textarea('intro')->translatable(),
            Prop::list('items', [
                Prop::text('question')->required()->translatable(),
                Prop::richText('answer')->required()->translatable(),
            ])->max(40),
            Prop::toggle('schema')->default(true),
        ];
    }

    public function data(array $props): array
    {
        if ($props['schema'] && $props['items'] !== []) {
            Seo::page()->jsonLd(['@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $item): array => [
                '@type' => 'Question', 'name' => $item['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($item['answer']))],
            ], $props['items'])]);
        }
        return [];
    }
}
