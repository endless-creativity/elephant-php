<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/numbering-xml.js (level shape)

namespace EndlessCreativity\ElephantPhp\Document;

final readonly class NumberingLevel
{
    public function __construct(
        public int $level,
        public bool $isOrdered,
    ) {
    }
}
