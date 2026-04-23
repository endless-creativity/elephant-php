<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Reader\Xml\Text;
use EndlessCreativity\ElephantPhp\Reader\Xml\XmlReader;

it('parses elements without namespaces', function (): void {
    $element = XmlReader::readString('<root><child name="alpha"/></root>');

    expect($element->name)->toBe('root');
    expect($element->first('child')?->attributes['name'])->toBe('alpha');
});

it('maps known namespaces to their prefix', function (): void {
    $xml = '<r:Relationships xmlns:r="http://example.com/rel"><r:Relationship Id="rId1"/></r:Relationships>';

    $element = XmlReader::readString($xml, ['http://example.com/rel' => 'relationships']);

    expect($element->name)->toBe('relationships:Relationships');
    expect($element->first('relationships:Relationship')?->attributes['Id'])->toBe('rId1');
});

it('falls back to {namespace}localName when the namespace is not in the map', function (): void {
    $xml = '<r:Foo xmlns:r="http://example.com/unknown"/>';

    $element = XmlReader::readString($xml);

    expect($element->name)->toBe('{http://example.com/unknown}Foo');
});

it('captures text content as Text nodes', function (): void {
    $element = XmlReader::readString('<t>Hello world</t>');

    expect($element->children)->toHaveCount(1);
    expect($element->children[0])->toBeInstanceOf(Text::class);
    expect($element->text())->toBe('Hello world');
});

it('throws on malformed xml', function (): void {
    XmlReader::readString('<not><closed>');
})->throws(RuntimeException::class);
