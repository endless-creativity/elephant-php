<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Reader\StylesReader;
use EndlessCreativity\ElephantPhp\Reader\Xml\Element;

function styleElement(string $type, string $id, ?string $name = null): Element
{
    $children = [];
    if ($name !== null) {
        $children[] = new Element(name: 'w:name', attributes: ['w:val' => $name]);
    }

    return new Element(
        name: 'w:style',
        attributes: ['w:type' => $type, 'w:styleId' => $id],
        children: $children,
    );
}

function stylesXml(Element ...$styleElements): Element
{
    return new Element(name: 'w:styles', children: array_values($styleElements));
}

it('returns null when looking up an unknown paragraph style', function (): void {
    $styles = StylesReader::readFromXml(stylesXml());

    expect($styles->findParagraphStyleById('Heading1'))->toBeNull();
});

it('finds a paragraph style by id', function (): void {
    $styles = StylesReader::readFromXml(stylesXml(
        styleElement(type: 'paragraph', id: 'Heading1', name: 'Heading 1'),
    ));

    $style = $styles->findParagraphStyleById('Heading1');
    expect($style)->not->toBeNull();
    expect($style?->styleId)->toBe('Heading1');
    expect($style?->name)->toBe('Heading 1');
});

it('finds a character style by id', function (): void {
    $styles = StylesReader::readFromXml(stylesXml(
        styleElement(type: 'character', id: 'Heading1Char', name: 'Heading 1 Char'),
    ));

    expect($styles->findCharacterStyleById('Heading1Char')?->styleId)->toBe('Heading1Char');
});

it('keeps paragraph and character styles in distinct namespaces', function (): void {
    $styles = StylesReader::readFromXml(stylesXml(
        styleElement(type: 'paragraph', id: 'Heading1', name: 'Heading 1'),
        styleElement(type: 'character', id: 'Heading1Char', name: 'Heading 1 Char'),
    ));

    expect($styles->findCharacterStyleById('Heading1'))->toBeNull();
    expect($styles->findParagraphStyleById('Heading1Char'))->toBeNull();
});

it('returns null for the name when the w:name element is missing', function (): void {
    $styles = StylesReader::readFromXml(stylesXml(
        styleElement(type: 'paragraph', id: 'Heading1'),
    ));

    expect($styles->findParagraphStyleById('Heading1')?->name)->toBeNull();
});

it('keeps only the first definition when multiple style elements share an id', function (): void {
    $styles = StylesReader::readFromXml(stylesXml(
        styleElement(type: 'paragraph', id: 'Heading1', name: 'Heading 1'),
        styleElement(type: 'paragraph', id: 'Heading1', name: 'Other'),
    ));

    expect($styles->findParagraphStyleById('Heading1')?->name)->toBe('Heading 1');
});
