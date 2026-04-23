<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js (Table)

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class Table implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(public array $children = [])
    {
    }
}
