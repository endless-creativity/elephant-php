<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Converter;

it('converts single-paragraph.docx into a single <p> element', function (): void {
    $result = (new Converter())->convertToHtml(fixture('single-paragraph.docx'));

    expect($result->value)->toBe('<p>Walking on imported air</p>');
});

it('converts strikethrough.docx with a strikethrough run wrapped in <s>', function (): void {
    $result = (new Converter())->convertToHtml(fixture('strikethrough.docx'));

    expect($result->value)->toBe("<p><s>Today's Special: Salmon</s> Sold out</p>");
});

it('converts simple-list.docx into a single <ul> with two <li> items', function (): void {
    $result = (new Converter())->convertToHtml(fixture('simple-list.docx'));

    expect($result->value)->toBe('<ul><li>Apple</li><li>Banana</li></ul>');
});

it('converts tables.docx into a 2x2 <table>', function (): void {
    $result = (new Converter())->convertToHtml(fixture('tables.docx'));

    expect($result->value)->toBe(
        '<p>Above</p>'
        .'<table>'
        .'<tr><td><p>Top left</p></td><td><p>Top right</p></td></tr>'
        .'<tr><td><p>Bottom left</p></td><td><p>Bottom right</p></td></tr>'
        .'</table>'
        .'<p>Below</p>',
    );
});
