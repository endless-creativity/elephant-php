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

it('renders a paragraph as text followed by two newlines', function (): void {
    // The trailing period is escaped to keep markdown parsers from
    // treating a leading number as the start of an ordered list -- this
    // matches mammoth.js's escape set exactly.
    expect(MarkdownWriter::write([el('p', children: [txt('Hello.')])]))
        ->toBe("Hello\\.\n\n");
});

it('hoists leading whitespace out of <strong> and drops it at line start', function (): void {
    // The hoist moves the spaces outside the wrapper so the markdown is
    // valid; the line-start strip then keeps the output as pure text without
    // triggering CommonMark's indented-code-block rule (4+ leading spaces).
    $node = el('p', children: [
        el('strong', children: [txt('    Bau')]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("__Bau__\n\n");
});

it('hoists trailing whitespace out of <strong> and keeps it after the wrapper', function (): void {
    // Trailing whitespace lands mid-line, where it does not trigger any
    // CommonMark rule, so it is preserved as-is.
    $node = el('p', children: [
        el('strong', children: [txt('Bau    ')]),
    ]);

    expect(MarkdownWriter::write([$node]))->toBe("__Bau__    \n\n");
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

    expect(MarkdownWriter::write([$node]))->toBe("__*hi*__    \n\n");
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

it('renders <strong> as __ wrappers', function (): void {
    expect(MarkdownWriter::write([el('p', children: [
        el('strong', children: [txt('bold')]),
    ])]))->toBe("__bold__\n\n");
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

it('escapes markdown-special characters in text', function (): void {
    expect(MarkdownWriter::write([txt('a*b_c[d](e)#')]))
        ->toBe('a\\*b\\_c\\[d\\]\\(e\\)\\#');
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
