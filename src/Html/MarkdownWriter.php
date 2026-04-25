<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/writers/markdown-writer.js
//
// Faithful port: each tag in HANDLERS returns a {start, end, list?,
// anchorPosition?} descriptor exactly as mammoth does, so the nested-list
// "share the trailing \n with the inner <li>" trick (the hasClosed flag) is
// preserved. We walk the HTML AST directly rather than going through a
// separate writer protocol -- the equivalent is inlined in writeNode.

namespace EndlessCreativity\ElephantPhp\Html;

use Closure;

final class MarkdownWriter
{
    /**
     * Tags whose markdown delimiters require a non-whitespace character
     * adjacent to the opening / closing marker to actually format the
     * content (CommonMark § 6.2). For these tags we hoist any leading or
     * trailing whitespace text out of the wrapper before writing, so
     * `__   bold   __` (broken markdown) becomes `   __bold__   ` (working).
     *
     * @var array<string, true>
     */
    private const EMPHASIS_TAGS = [
        'strong' => true,
        'em' => true,
        's' => true,
        'sub' => true,
        'sup' => true,
    ];

    /**
     * @param  list<Node>  $nodes
     */
    public static function write(array $nodes): string
    {
        $writer = new self();
        $writer->writeNodes(self::hoistEmphasisWhitespace($nodes));

        return $writer->output;
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private static function hoistEmphasisWhitespace(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            foreach (self::hoistOne($node) as $hoisted) {
                $result[] = $hoisted;
            }
        }

        return $result;
    }

    /**
     * @return list<Node>
     */
    private static function hoistOne(Node $node): array
    {
        if (! $node instanceof Element) {
            return [$node];
        }

        // Recurse first so nested emphasis is normalised bottom-up.
        $children = self::hoistEmphasisWhitespace($node->children);

        if (! isset(self::EMPHASIS_TAGS[$node->tag->tagName])) {
            return [$node->withChildren($children)];
        }

        $leading = '';
        while ($children !== [] && $children[0] instanceof Text) {
            $value = $children[0]->value;
            if (preg_match('/^(\s+)(.*)$/s', $value, $m) !== 1) {
                break;
            }
            $leading .= $m[1];
            if ($m[2] === '') {
                array_shift($children);

                continue;
            }
            $children[0] = new Text(value: $m[2]);
            break;
        }

        $trailing = '';
        while ($children !== []) {
            $lastIndex = count($children) - 1;
            $last = $children[$lastIndex];
            if (! $last instanceof Text) {
                break;
            }
            $value = $last->value;
            if (preg_match('/^(.*?)(\s+)$/s', $value, $m) !== 1) {
                break;
            }
            $trailing = $m[2].$trailing;
            if ($m[1] === '') {
                array_pop($children);

                continue;
            }
            $children[$lastIndex] = new Text(value: $m[1]);
            break;
        }

        $result = [];
        if ($leading !== '') {
            $result[] = new Text(value: $leading);
        }
        if ($children !== []) {
            $result[] = $node->withChildren($children);
        }
        if ($trailing !== '') {
            $result[] = new Text(value: $trailing);
        }

        return $result;
    }

    private string $output = '';

    /** @var list<array{end: string|Closure(): ?string, list: ?array{isOrdered: bool, indent: int, count: int}}> */
    private array $stack = [];

    /** @var ?array{isOrdered: bool, indent: int, count: int} */
    private ?array $list = null;

    /** Shared across list items so the outer <li> doesn't re-emit the inner <li>'s trailing newline. */
    /** @var array{hasClosed: bool} */
    private array $listItem = ['hasClosed' => false];

    /**
     * @param  list<Node>  $nodes
     */
    private function writeNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Text) {
                $this->output .= self::escape($node->value);

                continue;
            }
            if ($node instanceof Element) {
                if ($node->isVoid()) {
                    $this->openElement($node);
                    $this->closeElement();
                } else {
                    $this->openElement($node);
                    $this->writeNodes($node->children);
                    $this->closeElement();
                }
            }
        }
    }

    private function openElement(Element $element): void
    {
        $descriptor = $this->buildDescriptor($element->tag->tagName, $element->tag->attributes);
        $this->stack[] = ['end' => $descriptor['end'], 'list' => $this->list];

        if (isset($descriptor['list'])) {
            $this->list = $descriptor['list'];
        }

        $anchorBefore = ($descriptor['anchorPosition'] ?? null) === 'before';
        if ($anchorBefore) {
            $this->writeAnchor($element->tag->attributes);
        }
        $this->output .= $descriptor['start'] ?? '';
        if (! $anchorBefore) {
            $this->writeAnchor($element->tag->attributes);
        }
    }

    private function closeElement(): void
    {
        $frame = array_pop($this->stack);
        if ($frame === null) {
            return;
        }
        $this->list = $frame['list'];
        $end = $frame['end'];
        $rendered = $end instanceof Closure ? $end() : $end;
        $this->output .= $rendered ?? '';
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function writeAnchor(array $attributes): void
    {
        if (isset($attributes['id'])) {
            $this->output .= '<a id="'.$attributes['id'].'"></a>';
        }
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{start?: string, end: string|Closure(): ?string, list?: array{isOrdered: bool, indent: int, count: int}, anchorPosition?: string}
     */
    private function buildDescriptor(string $tagName, array $attributes): array
    {
        // Heading levels h1..h6 share one rule.
        if (preg_match('/^h([1-6])$/', $tagName, $matches) === 1) {
            return ['start' => str_repeat('#', (int) $matches[1]).' ', 'end' => "\n\n"];
        }

        return match ($tagName) {
            'p' => ['start' => '', 'end' => "\n\n"],
            'br' => ['start' => '', 'end' => "  \n"],
            'strong' => ['start' => '__', 'end' => '__'],
            'em' => ['start' => '*', 'end' => '*'],
            'a' => self::linkDescriptor($attributes),
            'img' => self::imageDescriptor($attributes),
            'ul' => $this->listDescriptor(isOrdered: false),
            'ol' => $this->listDescriptor(isOrdered: true),
            'li' => $this->listItemDescriptor(),
            default => ['end' => ''],
        };
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{start?: string, end: string, anchorPosition?: string}
     */
    private static function linkDescriptor(array $attributes): array
    {
        $href = $attributes['href'] ?? '';
        if ($href === '') {
            return ['end' => ''];
        }

        return ['start' => '[', 'end' => ']('.$href.')', 'anchorPosition' => 'before'];
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{start?: string, end: string}
     */
    private static function imageDescriptor(array $attributes): array
    {
        $src = $attributes['src'] ?? '';
        $alt = $attributes['alt'] ?? '';
        if ($src === '' && $alt === '') {
            return ['end' => ''];
        }

        return ['start' => '!['.$alt.']('.$src.')', 'end' => ''];
    }

    /**
     * @return array{start: string, end: string, list: array{isOrdered: bool, indent: int, count: int}}
     */
    private function listDescriptor(bool $isOrdered): array
    {
        $nested = $this->list !== null;

        return [
            'start' => $nested ? "\n" : '',
            'end' => $nested ? '' : "\n",
            'list' => [
                'isOrdered' => $isOrdered,
                'indent' => $nested ? $this->list['indent'] + 1 : 0,
                'count' => 0,
            ],
        ];
    }

    /**
     * @return array{start: string, end: Closure(): ?string}
     */
    private function listItemDescriptor(): array
    {
        $list = $this->list ?? ['indent' => 0, 'isOrdered' => false, 'count' => 0];
        $list['count']++;
        $this->list = $list;
        $this->listItem['hasClosed'] = false;

        $bullet = $list['isOrdered'] ? $list['count'].'.' : '-';
        $start = str_repeat("\t", $list['indent']).$bullet.' ';

        $listItem = &$this->listItem;

        return [
            'start' => $start,
            'end' => static function () use (&$listItem): ?string {
                if (! $listItem['hasClosed']) {
                    $listItem['hasClosed'] = true;

                    return "\n";
                }

                return null;
            },
        ];
    }

    private static function escape(string $value): string
    {
        // Backslash first so we don't double-escape the backslashes we add.
        $value = str_replace('\\', '\\\\', $value);

        return preg_replace('/([`*_{}\[\]()#+\-.!])/', '\\\\$1', $value) ?? $value;
    }
}
