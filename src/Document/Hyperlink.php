<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js (Hyperlink)

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class Hyperlink implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(
        public array $children = [],
        public ?string $href = null,
        public ?string $anchor = null,
        public ?string $targetFrame = null,
    ) {
    }
}
