<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Document\Run;
use EndlessCreativity\ElephantPhp\Reader\BodyReader;
use EndlessCreativity\ElephantPhp\Reader\Xml\Element;

/**
 * @param  list<Element>  $properties
 */
function runWithRPr(array $properties): Element
{
    return new Element(name: 'w:r', children: [
        new Element(name: 'w:rPr', children: $properties),
    ]);
}

function readRunForHighlight(Element $run): Run
{
    $result = (new BodyReader())->readXmlElement($run);
    if (! $result->value instanceof Run) {
        throw new RuntimeException('Expected Run');
    }

    return $result->value;
}

it('reads w:highlight as the highlight color', function (): void {
    $run = readRunForHighlight(runWithRPr([
        new Element(name: 'w:highlight', attributes: ['w:val' => 'yellow']),
    ]));

    expect($run->highlight)->toBe('yellow');
});

it('treats w:highlight w:val="none" as no highlight', function (): void {
    $run = readRunForHighlight(runWithRPr([
        new Element(name: 'w:highlight', attributes: ['w:val' => 'none']),
    ]));

    expect($run->highlight)->toBeNull();
});

it('reads w:rFonts/@w:ascii as the run font', function (): void {
    $run = readRunForHighlight(runWithRPr([
        new Element(name: 'w:rFonts', attributes: ['w:ascii' => 'Helvetica']),
    ]));

    expect($run->font)->toBe('Helvetica');
});

it('reads w:sz/@w:val as half-points and stores it in points', function (): void {
    $run = readRunForHighlight(runWithRPr([
        new Element(name: 'w:sz', attributes: ['w:val' => '24']),
    ]));

    expect($run->fontSize)->toBe(12.0);
});

it('ignores a non-numeric w:sz value', function (): void {
    $run = readRunForHighlight(runWithRPr([
        new Element(name: 'w:sz', attributes: ['w:val' => 'big']),
    ]));

    expect($run->fontSize)->toBeNull();
});
