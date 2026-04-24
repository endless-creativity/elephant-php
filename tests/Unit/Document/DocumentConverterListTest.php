<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Document\Document;
use EndlessCreativity\ElephantPhp\Document\DocumentConverter;
use EndlessCreativity\ElephantPhp\Document\NumberingLevel;
use EndlessCreativity\ElephantPhp\Document\Paragraph;
use EndlessCreativity\ElephantPhp\Document\Run;
use EndlessCreativity\ElephantPhp\Document\Text;

function listItem(int $level, bool $isOrdered, string $text): Paragraph
{
    return new Paragraph(
        children: [new Run(children: [new Text(value: $text)])],
        numbering: new NumberingLevel(level: $level, isOrdered: $isOrdered),
    );
}

function htmlOf(Paragraph ...$paragraphs): string
{
    return (new DocumentConverter())
        ->convertToHtml(new Document(children: array_values($paragraphs)))
        ->value;
}

it('renders a single bullet item as <ul><li>...</li></ul>', function (): void {
    expect(htmlOf(listItem(level: 0, isOrdered: false, text: 'Apple')))
        ->toBe('<ul><li>Apple</li></ul>');
});

it('renders a single ordered item as <ol><li>...</li></ol>', function (): void {
    expect(htmlOf(listItem(level: 0, isOrdered: true, text: 'first')))
        ->toBe('<ol><li>first</li></ol>');
});

it('merges consecutive same-kind level-0 items into one <ul>', function (): void {
    expect(htmlOf(
        listItem(level: 0, isOrdered: false, text: 'Apple'),
        listItem(level: 0, isOrdered: false, text: 'Banana'),
    ))->toBe('<ul><li>Apple</li><li>Banana</li></ul>');
});

it('keeps unordered and ordered lists as separate sibling lists', function (): void {
    expect(htmlOf(
        listItem(level: 0, isOrdered: false, text: 'A'),
        listItem(level: 0, isOrdered: true, text: 'B'),
    ))->toBe('<ul><li>A</li></ul><ol><li>B</li></ol>');
});

it('nests a level-1 item inside the previous level-0 item', function (): void {
    expect(htmlOf(
        listItem(level: 0, isOrdered: false, text: 'A'),
        listItem(level: 1, isOrdered: false, text: 'a1'),
    ))->toBe('<ul><li>A<ul><li>a1</li></ul></li></ul>');
});

it('keeps multiple consecutive nested items inside the same nested list', function (): void {
    expect(htmlOf(
        listItem(level: 0, isOrdered: false, text: 'A'),
        listItem(level: 1, isOrdered: false, text: 'a1'),
        listItem(level: 1, isOrdered: false, text: 'a2'),
        listItem(level: 0, isOrdered: false, text: 'B'),
    ))->toBe('<ul><li>A<ul><li>a1</li><li>a2</li></ul></li><li>B</li></ul>');
});

it('nests an ordered child inside an unordered parent (mixed kinds)', function (): void {
    expect(htmlOf(
        listItem(level: 0, isOrdered: false, text: 'A'),
        listItem(level: 1, isOrdered: true, text: 'a1'),
        listItem(level: 1, isOrdered: true, text: 'a2'),
        listItem(level: 0, isOrdered: false, text: 'B'),
    ))->toBe('<ul><li>A<ol><li>a1</li><li>a2</li></ol></li><li>B</li></ul>');
});

it('nests an unordered child inside an ordered parent (mixed kinds)', function (): void {
    expect(htmlOf(
        listItem(level: 0, isOrdered: true, text: 'A'),
        listItem(level: 1, isOrdered: false, text: 'a1'),
    ))->toBe('<ol><li>A<ul><li>a1</li></ul></li></ol>');
});

it('breaks the list when a non-list paragraph appears between items', function (): void {
    $paragraph = new Paragraph(children: [new Run(children: [new Text(value: 'between')])]);

    expect(htmlOf(
        listItem(level: 0, isOrdered: false, text: 'A'),
        $paragraph,
        listItem(level: 0, isOrdered: false, text: 'B'),
    ))->toBe('<ul><li>A</li></ul><p>between</p><ul><li>B</li></ul>');
});
