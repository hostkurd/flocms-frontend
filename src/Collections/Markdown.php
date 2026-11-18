<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Frontend\Support\Html;

/**
 * Markdown files for collections kept in folders (D31): front matter
 * between --- lines, then the text. A small converter, no third-party
 * package: headings, paragraphs, **bold**, *italic*, `code`, links, images,
 * lists, quotes, code blocks, rules and tables. The HTML is cleaned by
 * Html::clean() like any rich text.
 */
final class Markdown
{
    /**
     * Front matter and body of a file.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function parseFile(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (str_starts_with($text, "\xEF\xBB\xBF")) { $text = substr($text, 3); }
        if (preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $text, $m) !== 1) {
            return [[], $text];
        }
        return [self::frontMatter($m[1]), $m[2]];
    }

    /**
     * A small YAML subset: "key: value", "key: [a, b]", lists of "- item"
     * under a key, quoted strings, numbers, true/false and null.
     *
     * @return array<string, mixed>
     */
    public static function frontMatter(string $yaml): array
    {
        $out = [];
        $key = null;
        foreach (explode("\n", $yaml) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) { continue; }
            if ($key !== null && preg_match('/^\s+-\s*(.*)$/', $line, $m) === 1) {
                if (!is_array($out[$key])) { $out[$key] = []; }
                $item = trim($m[1]);
                if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $item, $kv) === 1) {
                    $out[$key][] = [$kv[1] => self::scalar($kv[2])];
                } elseif (preg_match('/^\s+([A-Za-z0-9_]+):\s*(.*)$/', $line, $kv) === 1 && is_array(end($out[$key]))) {
                    $last = array_key_last($out[$key]);
                    $out[$key][$last][$kv[1]] = self::scalar($kv[2]);
                } else {
                    $out[$key][] = self::scalar($item);
                }
                continue;
            }
            if ($key !== null && preg_match('/^\s{2,}([A-Za-z0-9_]+):\s*(.*)$/', $line, $kv) === 1 && is_array($out[$key]) && $out[$key] !== [] && is_array(end($out[$key]))) {
                $last = array_key_last($out[$key]);
                $out[$key][$last][$kv[1]] = self::scalar($kv[2]);
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $m) === 1) {
                $key = $m[1];
                $value = self::withoutComment(trim($m[2]));
                if ($value === '') {
                    $out[$key] = [];
                } elseif (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                    $inner = trim(substr($value, 1, -1));
                    $out[$key] = $inner === '' ? [] : array_map([self::class, 'scalar'], str_getcsv($inner, ',', '"', ''));
                } else {
                    $out[$key] = self::scalar($value);
                }
            }
        }
        return $out;
    }

    /** "draft  # a note" → "draft"; a quoted value keeps its "#". */
    private static function withoutComment(string $value): string
    {
        if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
            return (string) preg_replace('/^("(?:[^"\\\\]|\\\\.)*"|\'[^\']*\')\s+#.*$/', '$1', $value);
        }
        return trim((string) preg_replace('/\s#.*$/', '', $value));
    }

    private static function scalar(string $value): mixed
    {
        $value = self::withoutComment(trim($value));
        if ($value === '' || $value === '~' || $value === 'null') { return null; }
        if ($value === 'true') { return true; }
        if ($value === 'false') { return false; }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return strlen($value) >= 2 ? stripcslashes(substr($value, 1, -1)) : '';
        }
        if (is_numeric($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        return $value;
    }

    /** Markdown to clean HTML. */
    public static function html(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $html = '';
        $paragraph = [];
        $i = 0;
        $n = count($lines);
        $flush = static function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html .= '<p>' . self::inline(implode(' ', array_map('trim', $paragraph))) . "</p>\n";
                $paragraph = [];
            }
        };
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') { $flush(); $i++; continue; }
            if (preg_match('/^```/', $line) === 1) {
                $flush();
                $code = [];
                $i++;
                while ($i < $n && preg_match('/^```/', $lines[$i]) !== 1) { $code[] = $lines[$i]; $i++; }
                $i++;
                $html .= '<pre><code>' . Html::e(implode("\n", $code)) . "</code></pre>\n";
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.*?)\s*#*$/', $line, $m) === 1) {
                $flush();
                $level = max(2, strlen($m[1])); // the page's title is the h1
                $html .= '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . ">\n";
                $i++;
                continue;
            }
            if (preg_match('/^(\*{3,}|-{3,}|_{3,})\s*$/', $line) === 1) {
                $flush();
                $html .= "<hr>\n";
                $i++;
                continue;
            }
            if (str_starts_with($line, '>')) {
                $flush();
                $quote = [];
                while ($i < $n && str_starts_with($lines[$i], '>')) { $quote[] = ltrim(substr($lines[$i], 1)); $i++; }
                $html .= '<blockquote><p>' . self::inline(implode(' ', $quote)) . "</p></blockquote>\n";
                continue;
            }
            if (preg_match('/^\s*([-*+]|\d+[.)])\s+/', $line, $m) === 1) {
                $flush();
                $ordered = ctype_digit($m[1][0]);
                $items = [];
                while ($i < $n && preg_match('/^\s*([-*+]|\d+[.)])\s+(.*)$/', $lines[$i], $mm) === 1 && ctype_digit($mm[1][0]) === $ordered) {
                    $items[] = '<li>' . self::inline($mm[2]) . '</li>';
                    $i++;
                }
                $tag = $ordered ? 'ol' : 'ul';
                $html .= "<{$tag}>" . implode('', $items) . "</{$tag}>\n";
                continue;
            }
            if (str_contains($line, '|') && $i + 1 < $n && preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/', $lines[$i + 1]) === 1) {
                $flush();
                $cells = static fn (string $row): array => array_map('trim', explode('|', trim(trim($row), '|')));
                $head = $cells($line);
                $i += 2;
                $body = '';
                while ($i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                    $body .= '<tr>' . implode('', array_map(static fn (string $c): string => '<td>' . self::inline($c) . '</td>', $cells($lines[$i]))) . '</tr>';
                    $i++;
                }
                $html .= '<table><thead><tr>' . implode('', array_map(static fn (string $c): string => '<th>' . self::inline($c) . '</th>', $head)) . '</tr></thead><tbody>' . $body . "</tbody></table>\n";
                continue;
            }
            $paragraph[] = $line;
            $i++;
        }
        $flush();
        return Html::clean($html);
    }

    /** Bold, italic, code, links and images inside a line. */
    private static function inline(string $text): string
    {
        $codes = [];
        $text = (string) preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codes): string {
            $codes[] = '<code>' . Html::e($m[1]) . '</code>';
            return "\x01" . (count($codes) - 1) . "\x01";
        }, $text);
        $text = Html::e($text);
        $text = (string) preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', static fn (array $m): string => '<img src="' . Html::e(Html::safeUrl(html_entity_decode($m[2]))) . '" alt="' . $m[1] . '" loading="lazy">', $text);
        $text = (string) preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static fn (array $m): string => '<a href="' . Html::e(Html::safeUrl(html_entity_decode($m[2]))) . '">' . $m[1] . '</a>', $text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*|__(.+?)__/', '<strong>$1$2</strong>', $text);
        $text = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])|(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/u', '<em>$1$2</em>', $text);
        return (string) preg_replace_callback("/\x01(\d+)\x01/", static fn (array $m): string => $codes[(int) $m[1]] ?? '', $text);
    }
}
