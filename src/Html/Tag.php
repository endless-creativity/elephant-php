<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/styles/html-paths.js (Element)

namespace EndlessCreativity\ElephantPhp\Html;

final readonly class Tag
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public string $tagName,
        public array $attributes = [],
        public bool $fresh = true,
        public ?string $separator = null,
    ) {
    }

    public function matches(self $other): bool
    {
        return $this->tagName === $other->tagName && $this->attributes === $other->attributes;
    }
}
