<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Html\Element;
use EndlessCreativity\ElephantPhp\Html\MarkdownWriter;
use EndlessCreativity\ElephantPhp\Html\Tag;
use EndlessCreativity\ElephantPhp\Html\Text;

/**
 * @param  array<string, string>  $attributes
 * @param  list<\EndlessCreativity\ElephantPhp\Html\Node>  $children
 */
function el(string $tag, array $attributes = [], array $children = []): Element
{
    return new Element(tag: new Tag(tagName: $tag, attributes: $attributes), children: $children);
}

function txt(string $value): Text
{
    return new Text(value: $value);
}

it('renders a paragraph as text followed by two newlines, with no escape on a trailing period', function (): void {
    // The trailing period is *not* escaped: it would only matter at line
    // start after a digit run (`1. `), which is not the case here. Mammoth
    // would have escaped it; we don't.
    expect(MarkdownWriter::write([el('p', children: [txt('Hello.')])]))
        ->toBe("Hello.\n\n");
});

it('hoists leading whitespace out of <strong> and drops it at line start', function (): void {
    // The hoist moves the spaces outside the wrapper so the markdown is
    // valid; the line-start strip then keeps the output as pure text without
    // triggering CommonMark's indented-code-block rule (4+ leading spaces).
    $node = el('p', children: [
        el('strong', children: [txt('    Bau')]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("**Bau**\n\n");
});

it('hoists trailing whitespace out of <strong> and keeps it after the wrapper', function (): void {
    // Trailing whitespace lands mid-line, where it does not trigger any
    // CommonMark rule, so it is preserved as-is.
    $node = el('p', children: [
        el('strong', children: [txt('Bau    ')]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("**Bau**    \n\n");
});

it('strips line-leading whitespace from an <em> wrapper while keeping trailing', function (): void {
    $node = el('p', children: [
        el('em', children: [txt('  italic  ')]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("*italic*  \n\n");
});

it('drops a wrapper that contained only whitespace, leaving an empty paragraph', function (): void {
    $node = el('p', children: [
        el('strong', children: [txt('   ')]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("\n\n");
});

it('hoists whitespace through nested emphasis recursively then strips at line start', function (): void {
    // <strong>  <em>  hi  </em>  </strong>
    $node = el('p', children: [
        el('strong', children: [
            txt('  '),
            el('em', children: [txt('  hi  ')]),
            txt('  '),
        ]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("***hi***    \n\n");
});

it('strips a run of leading spaces inside a paragraph, not just at doc start', function (): void {
    $first = el('p', children: [txt('one')]);
    $indented = el('p', children: [txt('    two')]);

    expect(MarkdownWriter::write([$first, $indented]))->toBe("one\n\ntwo\n\n");
});

it('strips a leading tab so paragraphs indented with <w:tab> are not code blocks', function (): void {
    // mammoth/our reader emits <w:tab/> as "\t". Twelve tabs at line start
    // would become an indented code block under CommonMark; strip them.
    $node = el('p', children: [txt("\t\t\t\t\t\t\t\t\t\t\t\tcontro")]);

    expect(MarkdownWriter::write([$node]))->toBe("contro\n\n");
});

it('strips mixed leading tabs and spaces from a paragraph', function (): void {
    $node = el('p', children: [txt("\t \t  contro")]);

    expect(MarkdownWriter::write([$node]))->toBe("contro\n\n");
});

it('does not emit an HTML anchor for an element carrying an id', function (): void {
    // Word fills documents with bookmarks (`_Toc...`, `_Hlk...`,
    // `_Ref...`) which the converter renders as <a id> in HTML. Markdown
    // output must stay HTML-free, so the id is silently dropped here.
    $node = el('h1', children: [
        el('a', attributes: ['id' => '_Toc0']),
        txt('Heading'),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("# Heading\n\n");
});

it('still renders an <a href> as a normal markdown link even when it carries an id', function (): void {
    // A real link with both id and href: drop the id, keep the link.
    $node = el('p', children: [
        el('a', attributes: ['id' => 'comment-ref-1', 'href' => '#comment-1'], children: [
            txt('see'),
        ]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("[see](#comment-1)\n\n");
});

it('keeps the leading tab on a nested list item so the parser sees the nesting', function (): void {
    $list = el('ul', children: [
        el('li', children: [
            txt('A'),
            el('ul', children: [el('li', children: [txt('a1')])]),
        ]),
        el('li', children: [txt('B')]),
    ]);

    // Same expectation as the existing nested-list test: the "\t- a1" line
    // must keep its leading tab so it's recognised as a nested list item.
    expect(MarkdownWriter::write([$list]))->toBe("- A\n\t- a1\n- B\n\n");
});

it('renders <strong> as ** wrappers (asterisks, to avoid clash with literal underscores)', function (): void {
    expect(MarkdownWriter::write([el('p', children: [
        el('strong', children: [txt('bold')]),
    ])]))->toBe("**bold**\n\n");
});

it('renders <em> as * wrappers', function (): void {
    expect(MarkdownWriter::write([el('p', children: [
        el('em', children: [txt('italic')]),
    ])]))->toBe("*italic*\n\n");
});

it('renders heading levels with the right number of #', function (): void {
    expect(MarkdownWriter::write([el('h1', children: [txt('one')])]))->toBe("# one\n\n");
    expect(MarkdownWriter::write([el('h3', children: [txt('three')])]))->toBe("### three\n\n");
    expect(MarkdownWriter::write([el('h6', children: [txt('six')])]))->toBe("###### six\n\n");
});

it('renders <a> with href as a markdown link', function (): void {
    expect(MarkdownWriter::write([
        el('a', attributes: ['href' => 'https://example.com/'], children: [txt('click')]),
    ]))->toBe('[click](https://example.com/)');
});

it('renders <a> without href as plain children', function (): void {
    expect(MarkdownWriter::write([
        el('a', children: [txt('plain')]),
    ]))->toBe('plain');
});

it('renders <img> with src and alt as ![alt](src)', function (): void {
    expect(MarkdownWriter::write([
        el('img', attributes: ['alt' => 'logo', 'src' => 'data:img']),
    ]))->toBe('![logo](data:img)');
});

it('renders <img> with no src and no alt as the empty string', function (): void {
    expect(MarkdownWriter::write([el('img')]))->toBe('');
});

it('renders <br> as a two-space hard line break inside a paragraph', function (): void {
    // <br> always appears inside flow content; the two-space marker only
    // matters when preceded by text, so we test the realistic shape.
    $node = el('p', children: [txt('a'), el('br'), txt('b')]);

    expect(MarkdownWriter::write([$node]))->toBe("a  \nb\n\n");
});

it('escapes only chars that would actually be parsed as markdown in this position', function (): void {
    // Mid-text: `[d](e)` is the inline-link pattern, so its brackets are
    // escaped; everything else is left literal because CommonMark would
    // not parse it as syntax here (intraword underscore rule, asterisks
    // not flanking, parens only matter after `]`, `#` only at line start).
    expect(MarkdownWriter::write([txt('a*b_c[d](e)#')]))
        ->toBe('a*b_c\\[d\\](e)#');
});

it('leaves citation-style brackets as literal text', function (): void {
    // `[1]`, `[Nota]`, `[sic]` would only become links if a matching
    // `[ref]: url` definition existed somewhere -- and we never emit
    // those. CommonMark renders them as literal text; no escape needed.
    $node = el('p', children: [txt('vedi [1] e [Nota] e anche [sic]')]);

    expect(MarkdownWriter::write([$node]))
        ->toBe("vedi [1] e [Nota] e anche [sic]\n\n");
});

it('escapes brackets only when they form the inline-link pattern', function (): void {
    // Mixed: `[1]` is a citation (literal), `[click](url)` is a real
    // link pattern that must be escaped to render as text.
    $node = el('p', children: [txt('see [1] and [click](url)')]);

    expect(MarkdownWriter::write([$node]))
        ->toBe("see [1] and \\[click\\](url)\n\n");
});

it('leaves long underscore runs alone (the typical Word fill-in field)', function (): void {
    // PHPWord+CommonMark output style: the underscore run is between
    // whitespace and punctuation, so CommonMark never opens emphasis on
    // it. No escape needed -- the user's main motivation for switching.
    $node = el('p', children: [txt('La società ___________________, con sede in __________')]);

    expect(MarkdownWriter::write([$node]))
        ->toBe("La società ___________________, con sede in __________\n\n");
});

it('does not escape periods, parens or dashes mid-text', function (): void {
    $node = el('p', children: [txt('see (articoli 2222 c.c. e seguenti) - art. 1')]);

    expect(MarkdownWriter::write([$node]))
        ->toBe("see (articoli 2222 c.c. e seguenti) - art. 1\n\n");
});

it('escapes # only at line start (heading marker)', function (): void {
    // Mid-text `#` is literal; at line start it would start a heading
    // and must be escaped to render as `#` text.
    $start = el('p', children: [txt('# not a heading')]);
    $mid = el('p', children: [txt('issue # 42')]);

    expect(MarkdownWriter::write([$start]))->toBe("\\# not a heading\n\n");
    expect(MarkdownWriter::write([$mid]))->toBe("issue # 42\n\n");
});

it('escapes - at line start when it would start a list item', function (): void {
    $list = el('p', children: [txt('- not a list')]);
    $mid = el('p', children: [txt('range 1-10')]);

    expect(MarkdownWriter::write([$list]))->toBe("\\- not a list\n\n");
    expect(MarkdownWriter::write([$mid]))->toBe("range 1-10\n\n");
});

it('escapes . after a digit run at line start (ordered-list marker)', function (): void {
    $list = el('p', children: [txt('1. not a list')]);
    $mid = el('p', children: [txt('Art. 2 - Natura')]);

    expect(MarkdownWriter::write([$list]))->toBe("1\\. not a list\n\n");
    expect(MarkdownWriter::write([$mid]))->toBe("Art. 2 - Natura\n\n");
});

it('escapes ! only when followed by [ (image syntax)', function (): void {
    $img = el('p', children: [txt('not an ![image](src) here')]);
    $excl = el('p', children: [txt('hello!')]);

    // Both `!` (only before `[`) and `[` `]` (always) are escaped here.
    expect(MarkdownWriter::write([$img]))->toBe("not an \\!\\[image\\](src) here\n\n");
    expect(MarkdownWriter::write([$excl]))->toBe("hello!\n\n");
});

it('escapes a literal backslash by doubling it', function (): void {
    expect(MarkdownWriter::write([txt('a\\b')]))->toBe('a\\\\b');
});

it('renders an unordered list with one bullet per item', function (): void {
    $list = el('ul', children: [
        el('li', children: [txt('Apple')]),
        el('li', children: [txt('Banana')]),
    ]);

    expect(MarkdownWriter::write([$list]))->toBe("- Apple\n- Banana\n\n");
});

it('renders an ordered list with incrementing numbers', function (): void {
    $list = el('ol', children: [
        el('li', children: [txt('one')]),
        el('li', children: [txt('two')]),
    ]);

    expect(MarkdownWriter::write([$list]))->toBe("1. one\n2. two\n\n");
});

it('indents nested lists with tabs and reuses the parent newline', function (): void {
    $list = el('ul', children: [
        el('li', children: [
            txt('A'),
            el('ul', children: [el('li', children: [txt('a1')])]),
        ]),
        el('li', children: [txt('B')]),
    ]);

    expect(MarkdownWriter::write([$list]))->toBe("- A\n\t- a1\n- B\n\n");
});

it('emits no markup for unknown tags but still walks children', function (): void {
    expect(MarkdownWriter::write([
        el('section', children: [el('p', children: [txt('hi')])]),
    ]))->toBe("hi\n\n");
});
