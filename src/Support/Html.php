<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

/** Small helpers for building markup safely. */
final class Html
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) || $value instanceof \Stringable ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Attributes from an array: true prints the name alone, false and null
     * leave it out, arrays under "class" are joined.
     *
     * @param array<string, mixed> $attributes
     */
    public static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || preg_match('/^[a-zA-Z_:][a-zA-Z0-9_.:-]*$/', $name) !== 1 || $value === null || $value === false) {
                continue;
            }
            if ($name === 'class' && is_array($value)) {
                $value = implode(' ', array_filter(array_map('strval', $value), static fn (string $c): bool => $c !== ''));
                if ($value === '') { continue; }
            }
            if (preg_match('/^on/i', $name) === 1) {
                continue; // no inline event handlers
            }
            $html .= $value === true ? ' ' . $name : ' ' . $name . '="' . self::e(is_array($value) ? implode(' ', $value) : $value) . '"';
        }
        return $html;
    }

    /** A link address that is safe to print: http(s), mailto, tel, a path, "#…" or "?…"; anything else becomes "#". */
    public static function safeUrl(mixed $url): string
    {
        $url = is_scalar($url) ? trim((string) $url) : '';
        if ($url === '') { return ''; }
        // Browsers drop tabs and line breaks in addresses: judge (and return) what they would open
        $plain = preg_replace('/[\x00-\x1F\x7F]+/', '', $url) ?? '';
        $check = str_replace(' ', '', $plain);
        if (str_starts_with($check, '/\\') || str_starts_with($check, '\\')) {
            return '#';
        }
        if (preg_match('#^(?:https?:|mailto:|tel:|/(?![/\\\\])|\#|\?)#i', $check) === 1 || (preg_match('#^[a-z0-9_./-]+$#i', $check) === 1 && !str_contains($check, ':'))) {
            return $plain;
        }
        return '#';
    }

    /**
     * Rich text with an allow-list of tags and attributes: what editors may
     * write (paragraphs, headings, lists, links, emphasis, quotes, tables,
     * images). Everything else is removed; links get safe addresses.
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') { return ''; }
        $allowed = ['p', 'br', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'small', 'mark',
            'a', 'ul', 'ol', 'li', 'blockquote', 'figure', 'figcaption', 'img', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'code', 'pre', 'span'];
        $attributes = ['a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading'], 'th' => ['scope', 'colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'ol' => ['start'], '*' => ['dir', 'lang']];
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        // A whole document, so a stray </div> in the text can't close a wrapper and cut the rest off
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementsByTagName('body')->item(0);
        if ($root === null) { return self::e(strip_tags($html)); }
        self::cleanNode($root, $allowed, $attributes);
        $out = '';
        foreach ($root->childNodes as $child) { $out .= $document->saveHTML($child); }
        return $out;
    }

    /**
     * @param list<string> $allowed
     * @param array<string, list<string>> $attributes
     */
    private static function cleanNode(\DOMNode $node, array $allowed, array $attributes): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) { continue; }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'template', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }
            self::cleanNode($child, $allowed, $attributes);
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild !== null) { $node->insertBefore($child->firstChild, $child); }
                $node->removeChild($child);
                continue;
            }
            $keep = array_merge($attributes[$tag] ?? [], $attributes['*']);
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, $keep, true)) { $child->removeAttribute($attribute->name); continue; }
                if (in_array($name, ['href', 'src'], true) && self::safeUrl($attribute->value) === '#') { $child->setAttribute($attribute->name, '#'); }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}
