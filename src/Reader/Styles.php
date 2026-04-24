<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/styles-reader.js

namespace EndlessCreativity\ElephantPhp\Reader;

final readonly class Styles
{
    /**
     * @param  array<string, Style>  $paragraphStyles
     * @param  array<string, Style>  $characterStyles
     * @param  array<string, Style>  $tableStyles
     */
    public function __construct(
        private array $paragraphStyles = [],
        private array $characterStyles = [],
        private array $tableStyles = [],
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    public function findParagraphStyleById(string $styleId): ?Style
    {
        return $this->paragraphStyles[$styleId] ?? null;
    }

    public function findCharacterStyleById(string $styleId): ?Style
    {
        return $this->characterStyles[$styleId] ?? null;
    }

    public function findTableStyleById(string $styleId): ?Style
    {
        return $this->tableStyles[$styleId] ?? null;
    }
}
