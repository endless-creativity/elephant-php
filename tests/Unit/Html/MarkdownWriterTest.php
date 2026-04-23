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

it('renders <br> as two-space hard line break', function (): void {
    expect(MarkdownWriter::write([el('br')]))->toBe("  \n");
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
