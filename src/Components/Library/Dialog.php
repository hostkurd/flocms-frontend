<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/**
 * A button that opens a dialog with the content between the tags. It uses
 * <dialog>; without JavaScript the button is a link to the dialog, shown in
 * the page.
 */
final class Dialog extends Component
{
    protected static string $name = 'dialog';
    protected static string $icon = 'square';
    protected static string $category = 'content';

    public function props(): array
    {
        return [
            Prop::text('label')->required()->translatable(),
            Prop::text('title')->required()->translatable(),
            Prop::select('style', ['solid' => 'Solid', 'outline' => 'Outline', 'arrow' => 'Text with an arrow'])->default('outline'),
        ];
    }

    public function data(array $props): array
    {
        return ['dialog_id' => self::uid('fe-dialog', $props)];
    }
}
