<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/numbering-xml.js (Numbering)

namespace EndlessCreativity\ElephantPhp\Reader;

use EndlessCreativity\ElephantPhp\Document\NumberingLevel;

final readonly class Numbering
{
    /**
     * @param  array<int, string>  $abstractNumIdByNumId  numId => abstractNumId
     * @param  array<int, array<int, NumberingLevel>>  $levelsByAbstractNumId
     *                                                                        abstractNumId => (level => NumberingLevel)
     */
    public function __construct(
        private array $abstractNumIdByNumId = [],
        private array $levelsByAbstractNumId = [],
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    public function findLevel(string $numId, string $level): ?NumberingLevel
    {
        $abstractNumId = $this->abstractNumIdByNumId[(int) $numId] ?? null;
        if ($abstractNumId === null) {
            return null;
        }

        return $this->levelsByAbstractNumId[(int) $abstractNumId][(int) $level] ?? null;
    }
}
