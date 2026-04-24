<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class Document implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(
        public array $children = [],
        public Notes $notes = new Notes(),
        public Comments $comments = new Comments(),
    ) {
    }
}
