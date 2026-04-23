<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/documents.js

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class Run implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(
        public array $children = [],
        public ?string $styleId = null,
        public ?string $styleName = null,
        public bool $isBold = false,
        public bool $isItalic = false,
        public bool $isUnderline = false,
        public bool $isStrikethrough = false,
        public bool $isAllCaps = false,
        public bool $isSmallCaps = false,
        public VerticalAlignment $verticalAlignment = VerticalAlignment::Baseline,
    ) {
    }
}
