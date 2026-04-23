<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js (TableRow)

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class TableRow implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(
        public array $children = [],
        public bool $isHeader = false,
    ) {
    }
}
