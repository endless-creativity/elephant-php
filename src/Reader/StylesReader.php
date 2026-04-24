<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/styles-reader.js
//
// Note: mammoth does not resolve w:basedOn chains either. The original plan
// for this port mentioned recursive basedOn handling, but per the
// "do as mammoth does" rule we skip it. TODO: revisit once user-defined style
// maps land and we have a concrete edge case where basedOn matters.

namespace EndlessCreativity\ElephantPhp\Reader;

use EndlessCreativity\ElephantPhp\Reader\Xml\Element;

final class StylesReader
{
    public static function readFromXml(Element $root): Styles
    {
        $paragraphStyles = [];
        $characterStyles = [];
        $tableStyles = [];

        foreach ($root->getElementsByTagName('w:style') as $styleElement) {
            $type = $styleElement->attribute('w:type');
            $styleId = $styleElement->attribute('w:styleId');
            if ($type === null || $styleId === null) {
                continue;
            }
            if ($type !== 'paragraph' && $type !== 'character' && $type !== 'table') {
                continue;
            }

            // Per ECMA-376 4th edition Part 1 § 17.7.4.17, when the same
            // styleId is declared multiple times only the first definition
            // keeps it. Mammoth ignores subsequent definitions; we do too.
            if ($type === 'paragraph' && isset($paragraphStyles[$styleId])) {
                continue;
            }
            if ($type === 'character' && isset($characterStyles[$styleId])) {
                continue;
            }
            if ($type === 'table' && isset($tableStyles[$styleId])) {
                continue;
            }

            $style = new Style(
                styleId: $styleId,
                name: $styleElement->first('w:name')?->attribute('w:val'),
            );

            if ($type === 'paragraph') {
                $paragraphStyles[$styleId] = $style;
            } elseif ($type === 'character') {
                $characterStyles[$styleId] = $style;
            } else {
                $tableStyles[$styleId] = $style;
            }
        }

        return new Styles(
            paragraphStyles: $paragraphStyles,
            characterStyles: $characterStyles,
            tableStyles: $tableStyles,
        );
    }
}
