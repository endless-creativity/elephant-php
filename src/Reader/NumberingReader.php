<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/numbering-xml.js
//
// Subset for the v0.1 port: we read w:abstractNum/w:lvl into per-level
// (level, isOrdered) entries, and w:num to map numId -> abstractNumId.
// w:numStyleLink (numbering inherited via a paragraph style) and
// findLevelByParagraphStyleId are intentionally deferred -- they are needed
// only by less common documents and require Styles to be wired in here.

namespace EndlessCreativity\ElephantPhp\Reader;

use EndlessCreativity\ElephantPhp\Document\NumberingLevel;
use EndlessCreativity\ElephantPhp\Reader\Xml\Element;

final class NumberingReader
{
    public static function readFromXml(Element $root): Numbering
    {
        $levelsByAbstractNumId = [];
        foreach ($root->getElementsByTagName('w:abstractNum') as $abstractNum) {
            $abstractNumId = $abstractNum->attribute('w:abstractNumId');
            if ($abstractNumId === null) {
                continue;
            }
            $levelsByAbstractNumId[(int) $abstractNumId] = self::readLevels($abstractNum);
        }

        $abstractNumIdByNumId = [];
        foreach ($root->getElementsByTagName('w:num') as $num) {
            $numId = $num->attribute('w:numId');
            $abstractNumId = $num->first('w:abstractNumId')?->attribute('w:val');
            if ($numId === null || $abstractNumId === null) {
                continue;
            }
            $abstractNumIdByNumId[(int) $numId] = $abstractNumId;
        }

        return new Numbering(
            abstractNumIdByNumId: $abstractNumIdByNumId,
            levelsByAbstractNumId: $levelsByAbstractNumId,
        );
    }

    /**
     * @return array<int, NumberingLevel>
     */
    private static function readLevels(Element $abstractNum): array
    {
        $levels = [];
        $levelWithoutIndex = null;

        foreach ($abstractNum->getElementsByTagName('w:lvl') as $levelElement) {
            $levelIndex = $levelElement->attribute('w:ilvl');
            $numFmt = $levelElement->first('w:numFmt')?->attribute('w:val');
            $isOrdered = $numFmt !== 'bullet';

            $level = new NumberingLevel(
                level: (int) ($levelIndex ?? 0),
                isOrdered: $isOrdered,
            );

            if ($levelIndex === null) {
                $levelWithoutIndex ??= $level;
            } else {
                $levels[(int) $levelIndex] = $level;
            }
        }

        // Per mammoth: malformed docs may declare a level with no w:ilvl;
        // fall back to level 0 if not already filled.
        if ($levelWithoutIndex !== null && ! isset($levels[0])) {
            $levels[0] = $levelWithoutIndex;
        }

        return $levels;
    }
}
