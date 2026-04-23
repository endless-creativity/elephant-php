<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class Paragraph implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(
        public array $children = [],
        public ?string $styleId = null,
        public ?string $styleName = null,
    ) {
    }
}
