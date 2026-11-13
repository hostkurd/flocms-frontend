<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Packages side by side. A highlighted one gets a blue outline and a small label, never a coloured background. */
final class Pricing extends Component
{
    protected static string $name = 'pricing';
    protected static string $icon = 'tag';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('heading')->translatable(),
            Prop::textarea('intro')->translatable(),
            Prop::list('plans', [
                Prop::text('name')->required()->translatable(),
                Prop::text('price')->translatable(),
                Prop::text('period')->translatable(),
                Prop::textarea('features')->translatable(),
                Prop::link('button'),
                Prop::toggle('highlight'),
            ])->max(4),
            Prop::text('note')->translatable(),
            Prop::text('highlight_label')->translatable(),
            Prop::select('tone', ['plain' => 'Plain', 'surface' => 'Grey row'])->default('surface'),
        ];
    }

    public function data(array $props): array
    {
        $plans = [];
        foreach ($props['plans'] as $plan) {
            $plan['feature_list'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $plan['features']) ?: []), static fn (string $line): bool => $line !== ''));
            $plans[] = $plan;
        }
        return ['plan_list' => $plans];
    }
}
