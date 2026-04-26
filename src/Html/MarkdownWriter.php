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

        return self::stripLineLeadingSpaces($writer->output);
    }

    /**
     * Strips runs of leading spaces at the start of every line. CommonMark
     * § 4.4 turns any line that begins with four or more spaces into an
     * indented code block, which destroys formatting on indented paragraphs
     * (and on emphasis wrappers whose leading whitespace we hoist out).
     * Markdown has no paragraph-indent primitive, so the spaces are dropped
     * entirely rather than preserved as `&nbsp;` -- the user asked for pure
     * text. Tabs at line start are left alone: we use them ourselves for
     * nested list items, where the parser recognises the list before the
     * indented-code rule kicks in.
     */
    private static function stripLineLeadingSpaces(string $markdown): string
    {
        // Strip runs of leading spaces *or* tabs at the start of every line.
        // A tab at line start is treated as four spaces by CommonMark's
        // indented-code rule, so a paragraph that started with <w:tab/> in
        // Word would otherwise become a code block.
        //
        // The negative lookahead `(?!- |\d+\. )` protects our own nested
        // list items: convertList emits "\t- foo" / "\t1. foo" for nested
        // entries, and those tabs must remain so the markdown parser keeps
        // recognising them as nested list items.
        //
        // <br>'s "  \n" marker is unaffected because <br> always appears
        // after non-whitespace content inside a paragraph in normal use,
        // so the two spaces never sit at line start.
        return preg_replace('/(^|\n)[ \t]+(?!- |\d+\. )/', '$1', $markdown) ?? $markdown;
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
                $this->output .= $this->smartEscape($node->value);

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

        // Mammoth emits a literal `<a id="...">` here for any element
        // carrying an id (so bookmarks survive in markdown). We don't:
        // markdown should be markdown, not HTML inline. BookmarkStart
        // therefore renders to nothing in markdown output and the id is
        // only preserved by the HTML writer. The `anchorPosition` key on
        // a descriptor is now inert -- kept for parity with mammoth's
        // descriptor shape.
        $this->output .= $descriptor['start'] ?? '';
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
            'strong' => ['start' => '**', 'end' => '**'],
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

        return ['start' => '[', 'end' => ']('.$href.')'];
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

    /**
     * Context-aware Markdown escape. Mammoth's blanket escape (every
     * occurrence of `` ` * _ { } [ ] ( ) # + - . ! ``) creates a lot of
     * visual noise on docx-derived text -- e.g. fill-in fields like
     * `_______________` and inline punctuation like `c.c.` get escaped
     * even though CommonMark would parse them as literal text. Here we
     * escape only the characters that would actually be parsed as syntax
     * in their position:
     *
     * - `` \ ``, `` ` ``: always (rare in docx text, and an unescaped
     *   backtick can open a code span across chunks).
     * - `[`, `]`: only when forming the inline link pattern `[...](` --
     *   citation-style brackets `[1]`, `[Nota]`, `[sic]` are left as
     *   text because we never emit link reference definitions, so
     *   CommonMark renders them literally.
     * - `#`, `+`: only at line start (heading / list marker)
     * - `-`: only at line start when followed by space (list marker)
     * - `.`: only after a digit run that begins at line start (ordered
     *   list marker pattern `\d+. `)
     * - `!`: only when followed by `[` *and* that `[` is itself a link
     *   opener (image syntax `![alt](src)`).
     * - `*`, `_`, `(`, `)`, `{`, `}`, `<`, `>`: never. CommonMark's
     *   flanking-delimiter and intraword rules mean docx-derived runs
     *   like `_______` between space and punctuation don't open
     *   emphasis, and parens don't open links without a preceding `]`.
     *
     * Because the decision depends on `$this->output` (line position),
     * this is an instance method, not static.
     */
    private function smartEscape(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $atLineStart = $this->output === '' || str_ends_with($this->output, "\n");
        // Tracks the chars emitted on the current line so far, scoped to
        // this chunk -- reset only on internal `\n`. Used solely for the
        // `\d+. ` ordered-list-marker detection.
        $lineSoFar = $atLineStart ? '' : null;

        // Pre-pass: positions of `[` and `]` that participate in an
        // inline-link pattern `[...](`. Outside of these positions,
        // brackets are left as literal text. The pattern disallows
        // nested brackets, matching the CommonMark inline-link rule.
        $linkBrackets = [];
        if (preg_match_all('/\[[^\[\]]*\]\(/', $text, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as $match) {
                $openPos = $match[1];
                $closePos = $openPos + strlen($match[0]) - 2;
                $linkBrackets[$openPos] = true;
                $linkBrackets[$closePos] = true;
            }
        }

        $result = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];
            $next = $i + 1 < $len ? $text[$i + 1] : '';

            $escape = match ($char) {
                '\\', '`' => true,
                '[', ']' => isset($linkBrackets[$i]),
                '#', '+' => $atLineStart,
                '-' => $atLineStart && $next === ' ',
                '.' => $lineSoFar !== null
                    && $lineSoFar !== ''
                    && preg_match('/^\d+$/', $lineSoFar) === 1
                    && $next === ' ',
                '!' => $next === '[' && isset($linkBrackets[$i + 1]),
                default => false,
            };

            $emitted = $escape ? '\\'.$char : $char;
            $result .= $emitted;

            if ($char === "\n") {
                $atLineStart = true;
                $lineSoFar = '';
            } else {
                $atLineStart = false;
                if ($lineSoFar !== null) {
                    $lineSoFar .= $emitted;
                }
            }
        }

        return $result;
    }
}
