<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js (TableCell)

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class TableCell implements Node
{
    /**
     * @param  list<Node>  $children
     * @param  ?bool  $vMerge  Transient marker carried only during the reader's
     *                         row-span computation. Always null after the
     *                         reader has resolved spans; the HTML converter
     *                         ignores it.
     */
    public function __construct(
        public array $children = [],
        public int $colSpan = 1,
        public int $rowSpan = 1,
        public ?bool $vMerge = null,
    ) {
    }
}
