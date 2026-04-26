<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Reader\NumberingReader;
use EndlessCreativity\ElephantPhp\Reader\Xml\Element;

/**
 * @param  list<array{ilvl: string, fmt: ?string, start?: string}>  $levels
 */
function abstractNumElement(string $abstractNumId, array $levels): Element
{
    $children = [];
    foreach ($levels as $level) {
        $levelChildren = [];
        if (isset($level['start'])) {
            $levelChildren[] = new Element(name: 'w:start', attributes: ['w:val' => $level['start']]);
        }
        if ($level['fmt'] !== null) {
            $levelChildren[] = new Element(name: 'w:numFmt', attributes: ['w:val' => $level['fmt']]);
        }
        $children[] = new Element(
            name: 'w:lvl',
            attributes: ['w:ilvl' => $level['ilvl']],
            children: $levelChildren,
        );
    }

    return new Element(
        name: 'w:abstractNum',
        attributes: ['w:abstractNumId' => $abstractNumId],
        children: $children,
    );
}

function numElement(string $numId, string $abstractNumId): Element
{
    return new Element(
        name: 'w:num',
        attributes: ['w:numId' => $numId],
        children: [
            new Element(name: 'w:abstractNumId', attributes: ['w:val' => $abstractNumId]),
        ],
    );
}

/**
 * @param  list<Element>  $children
 */
function numberingXml(array $children): Element
{
    return new Element(name: 'w:numbering', children: $children);
}

it('returns null for an unknown numId', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([]));

    expect($numbering->findLevel('1', '0'))->toBeNull();
});

it('resolves numId to abstractNumId and returns the matching level', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([
        abstractNumElement(abstractNumId: '0', levels: [
            ['ilvl' => '0', 'fmt' => 'bullet'],
            ['ilvl' => '1', 'fmt' => 'decimal'],
        ]),
        numElement(numId: '1', abstractNumId: '0'),
    ]));

    $level0 = $numbering->findLevel('1', '0');
    expect($level0?->level)->toBe(0);
    expect($level0?->isOrdered)->toBeFalse();

    $level1 = $numbering->findLevel('1', '1');
    expect($level1?->level)->toBe(1);
    expect($level1?->isOrdered)->toBeTrue();
});

it('treats any non-bullet numFmt as ordered', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([
        abstractNumElement(abstractNumId: '0', levels: [
            ['ilvl' => '0', 'fmt' => 'lowerRoman'],
        ]),
        numElement(numId: '1', abstractNumId: '0'),
    ]));

    expect($numbering->findLevel('1', '0')?->isOrdered)->toBeTrue();
});

it('returns null when the level is not defined for the abstractNum', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([
        abstractNumElement(abstractNumId: '0', levels: [
            ['ilvl' => '0', 'fmt' => 'bullet'],
        ]),
        numElement(numId: '1', abstractNumId: '0'),
    ]));

    expect($numbering->findLevel('1', '5'))->toBeNull();
});

it('falls back to level 0 when a level lacks an ilvl attribute', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([
        new Element(
            name: 'w:abstractNum',
            attributes: ['w:abstractNumId' => '0'],
            children: [
                new Element(name: 'w:lvl', children: [
                    new Element(name: 'w:numFmt', attributes: ['w:val' => 'bullet']),
                ]),
            ],
        ),
        numElement(numId: '1', abstractNumId: '0'),
    ]));

    $level = $numbering->findLevel('1', '0');
    expect($level?->level)->toBe(0);
    expect($level?->isOrdered)->toBeFalse();
});

it('reads <w:start> as the level start when present', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([
        abstractNumElement(abstractNumId: '0', levels: [
            ['ilvl' => '0', 'fmt' => 'decimal', 'start' => '2'],
        ]),
        numElement(numId: '1', abstractNumId: '0'),
    ]));

    expect($numbering->findLevel('1', '0')?->start)->toBe(2);
});

it('leaves start null when no <w:start> is declared', function (): void {
    $numbering = NumberingReader::readFromXml(numberingXml([
        abstractNumElement(abstractNumId: '0', levels: [
            ['ilvl' => '0', 'fmt' => 'decimal'],
        ]),
        numElement(numId: '1', abstractNumId: '0'),
    ]));

    expect($numbering->findLevel('1', '0')?->start)->toBeNull();
});
