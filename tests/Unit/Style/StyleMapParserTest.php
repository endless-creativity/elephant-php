<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Style\MatcherKind;
use EndlessCreativity\ElephantPhp\Style\StyleMapParser;
use EndlessCreativity\ElephantPhp\Style\StyleNameMatch;

it('parses a paragraph styleId mapping with a fresh tag', function (): void {
    $mapping = StyleMapParser::parse('p.Heading1 => h1:fresh');

    expect($mapping->from->kind)->toBe(MatcherKind::Paragraph);
    expect($mapping->from->styleId)->toBe('Heading1');
    expect($mapping->from->styleName)->toBeNull();

    expect($mapping->to->elements)->toHaveCount(1);
    expect($mapping->to->elements[0]->tagName)->toBe('h1');
    expect($mapping->to->elements[0]->fresh)->toBeTrue();
});

it('parses a styleName matcher with equals', function (): void {
    $mapping = StyleMapParser::parse("p[style-name='Heading 1'] => h1:fresh");

    expect($mapping->from->styleName)->toBe('Heading 1');
    expect($mapping->from->styleNameMatch)->toBe(StyleNameMatch::Equal);
});

it('parses a styleName matcher with starts-with', function (): void {
    $mapping = StyleMapParser::parse("p[style-name^='Heading'] => h1:fresh");

    expect($mapping->from->styleName)->toBe('Heading');
    expect($mapping->from->styleNameMatch)->toBe(StyleNameMatch::StartsWith);
});

it('parses a run matcher', function (): void {
    $mapping = StyleMapParser::parse("r[style-name='Emphasis'] => em");

    expect($mapping->from->kind)->toBe(MatcherKind::Run);
    expect($mapping->from->styleName)->toBe('Emphasis');
    expect($mapping->to->elements[0]->tagName)->toBe('em');
    expect($mapping->to->elements[0]->fresh)->toBeFalse();
});

it('parses a path with a class modifier', function (): void {
    $mapping = StyleMapParser::parse("p[style-name='Aside'] => p.aside");

    expect($mapping->to->elements[0]->attributes)->toBe(['class' => 'aside']);
});

it('appends multiple class names with a single space', function (): void {
    $mapping = StyleMapParser::parse("p[style-name='X'] => p.foo.bar");

    expect($mapping->to->elements[0]->attributes)->toBe(['class' => 'foo bar']);
});

it('parses a path with an attribute modifier', function (): void {
    $mapping = StyleMapParser::parse("p[style-name='X'] => p[data-x='hi']");

    expect($mapping->to->elements[0]->attributes)->toBe(['data-x' => 'hi']);
});

it('parses a multi-element path with > as element separator', function (): void {
    $mapping = StyleMapParser::parse("p[style-name='Aside'] => div.aside > p");

    expect($mapping->to->elements)->toHaveCount(2);
    expect($mapping->to->elements[0]->tagName)->toBe('div');
    expect($mapping->to->elements[0]->attributes)->toBe(['class' => 'aside']);
    expect($mapping->to->elements[1]->tagName)->toBe('p');
});

it('parses a bang as an ignore path', function (): void {
    $mapping = StyleMapParser::parse("p[style-name='Hidden'] => !");

    expect($mapping->to->ignore)->toBeTrue();
});

it('parses a matcher with no path as an empty path', function (): void {
    $mapping = StyleMapParser::parse('p');

    expect($mapping->to->ignore)->toBeFalse();
    expect($mapping->to->elements)->toBe([]);
});

it('throws on an unknown matcher kind', function (): void {
    StyleMapParser::parse('section.Foo => p');
})->throws(InvalidArgumentException::class);

it('throws on an unterminated string', function (): void {
    StyleMapParser::parse("p[style-name='Heading => h1");
})->throws(InvalidArgumentException::class);

it('parseAll builds a StyleMap from multiple rules', function (): void {
    $map = StyleMapParser::parseAll([
        'p.Heading1 => h1:fresh',
        'p.Heading2 => h2:fresh',
    ]);

    expect($map->mappings)->toHaveCount(2);
});
