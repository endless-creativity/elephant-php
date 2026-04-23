<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/document-to-html.js (findStyle / styleMap)

namespace EndlessCreativity\ElephantPhp\Style;

use EndlessCreativity\ElephantPhp\Document\Paragraph;
use EndlessCreativity\ElephantPhp\Document\Run;

final readonly class StyleMap
{
    /**
     * @param  list<StyleMapping>  $mappings  Tried in order; the first matching
     *                                        mapping wins.
     */
    public function __construct(public array $mappings = [])
    {
    }

    public static function default(): self
    {
        return StyleMapParser::parseAll(self::DEFAULT_RULES);
    }

    /**
     * Returns a new style map whose mappings are searched before this one's.
     *
     * @param  list<StyleMapping>  $mappings
     */
    public function prepend(array $mappings): self
    {
        return new self([...$mappings, ...$this->mappings]);
    }

    public function findForParagraph(Paragraph $paragraph): ?StyleMapping
    {
        foreach ($this->mappings as $mapping) {
            if ($mapping->from->matchesParagraph($paragraph)) {
                return $mapping;
            }
        }

        return null;
    }

    public function findForRun(Run $run): ?StyleMapping
    {
        foreach ($this->mappings as $mapping) {
            if ($mapping->from->matchesRun($run)) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Default style map ported from
     * mammoth.js/lib/options-reader.js (heading mappings only -- list,
     * table, comment-reference and break mappings depend on matchers that
     * are not yet implemented).
     *
     * @var list<string>
     */
    private const DEFAULT_RULES = [
        'p.Heading1 => h1:fresh',
        'p.Heading2 => h2:fresh',
        'p.Heading3 => h3:fresh',
        'p.Heading4 => h4:fresh',
        'p.Heading5 => h5:fresh',
        'p.Heading6 => h6:fresh',
        "p[style-name='Heading 1'] => h1:fresh",
        "p[style-name='Heading 2'] => h2:fresh",
        "p[style-name='Heading 3'] => h3:fresh",
        "p[style-name='Heading 4'] => h4:fresh",
        "p[style-name='Heading 5'] => h5:fresh",
        "p[style-name='Heading 6'] => h6:fresh",
        'p.Heading => h1:fresh',
        "p[style-name='Heading'] => h1:fresh",
    ];
}
