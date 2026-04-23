<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/styles/document-matchers.js
//
// Subset for v0.1: only paragraph and run matchers, only styleId / styleName
// (equal or startsWith). Mammoth's table, b/i/u/strike, all-caps/small-caps,
// highlight, comment-reference, list and break matchers are TODO.

namespace EndlessCreativity\ElephantPhp\Style;

use EndlessCreativity\ElephantPhp\Document\Paragraph;
use EndlessCreativity\ElephantPhp\Document\Run;

final readonly class Matcher
{
    public function __construct(
        public MatcherKind $kind,
        public ?string $styleId = null,
        public ?string $styleName = null,
        public StyleNameMatch $styleNameMatch = StyleNameMatch::Equal,
    ) {
    }

    public function matchesParagraph(Paragraph $paragraph): bool
    {
        if ($this->kind !== MatcherKind::Paragraph) {
            return false;
        }

        return $this->matchesStyle($paragraph->styleId, $paragraph->styleName);
    }

    public function matchesRun(Run $run): bool
    {
        if ($this->kind !== MatcherKind::Run) {
            return false;
        }

        return $this->matchesStyle($run->styleId, $run->styleName);
    }

    private function matchesStyle(?string $styleId, ?string $styleName): bool
    {
        if ($this->styleId !== null && $this->styleId !== $styleId) {
            return false;
        }
        if ($this->styleName !== null) {
            if ($styleName === null) {
                return false;
            }
            if ($this->styleNameMatch === StyleNameMatch::Equal && $this->styleName !== $styleName) {
                return false;
            }
            if ($this->styleNameMatch === StyleNameMatch::StartsWith && ! str_starts_with($styleName, $this->styleName)) {
                return false;
            }
        }

        return true;
    }
}
