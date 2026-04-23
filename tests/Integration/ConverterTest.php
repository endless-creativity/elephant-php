<?php

declare(strict_types=1);

use EndlessCreativity\ElephantPhp\Converter;

it('converts single-paragraph.docx into a single <p> element', function (): void {
    $result = (new Converter())->convertToHtml(fixture('single-paragraph.docx'));

    expect($result->value)->toBe('<p>Walking on imported air</p>');
});
