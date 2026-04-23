<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/body-reader.js

namespace EndlessCreativity\ElephantPhp\Reader;

use EndlessCreativity\ElephantPhp\Document\Hyperlink;
use EndlessCreativity\ElephantPhp\Document\Node as DocumentNode;
use EndlessCreativity\ElephantPhp\Document\NumberingLevel;
use EndlessCreativity\ElephantPhp\Document\Paragraph;
use EndlessCreativity\ElephantPhp\Document\Run;
use EndlessCreativity\ElephantPhp\Document\Text;
use EndlessCreativity\ElephantPhp\Document\VerticalAlignment;
use EndlessCreativity\ElephantPhp\Message;
use EndlessCreativity\ElephantPhp\Reader\Xml\Element;
use EndlessCreativity\ElephantPhp\Reader\Xml\Node as XmlNode;
use EndlessCreativity\ElephantPhp\Result;

final class BodyReader
{
    /** @var array<string, true> */
    private const IGNORED_ELEMENTS = [
        'office-word:wrap' => true,
        'v:shadow' => true,
        'v:shapetype' => true,
        'w:annotationRef' => true,
        'w:bookmarkEnd' => true,
        'w:sectPr' => true,
        'w:proofErr' => true,
        'w:lastRenderedPageBreak' => true,
        'w:commentRangeStart' => true,
        'w:commentRangeEnd' => true,
        'w:del' => true,
        'w:footnoteRef' => true,
        'w:endnoteRef' => true,
        'w:pPr' => true,
        'w:rPr' => true,
        'w:tblPr' => true,
        'w:tblGrid' => true,
        'w:trPr' => true,
        'w:tcPr' => true,
    ];

    public function __construct(
        private readonly Styles $styles = new Styles(),
        private readonly Relationships $relationships = new Relationships(),
        private readonly Numbering $numbering = new Numbering(),
    ) {
    }

    /**
     * @return Result<?DocumentNode>
     */
    public function readXmlElement(Element $element): Result
    {
        return match ($element->name) {
            'w:p' => $this->readParagraph($element),
            'w:r' => $this->readRun($element),
            'w:t' => Result::success(new Text(value: $element->text())),
            'w:hyperlink' => $this->readHyperlink($element),
            default => isset(self::IGNORED_ELEMENTS[$element->name])
                ? Result::success(null)
                : new Result(
                    value: null,
                    messages: [Message::warning("An unrecognised element was ignored: {$element->name}")],
                ),
        };
    }

    /**
     * Reads a list of sibling XML nodes. Non-element nodes (e.g. text outside
     * of `<w:t>`) are silently skipped, matching mammoth's element-only
     * dispatch.
     *
     * @param  list<XmlNode>  $nodes
     * @return Result<list<DocumentNode>>
     */
    public function readXmlElements(array $nodes): Result
    {
        $perElement = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Element) {
                continue;
            }
            $perElement[] = $this->readXmlElement($node)
                ->map(fn (?DocumentNode $documentNode): array => $documentNode === null ? [] : [$documentNode]);
        }

        return Result::combine($perElement);
    }

    /**
     * @return Result<DocumentNode>
     */
    private function readParagraph(Element $element): Result
    {
        $properties = $element->firstOrEmpty('w:pPr');
        $childrenResult = $this->readXmlElements($element->children);
        $styleResult = $this->readStyle(
            properties: $properties,
            styleTagName: 'w:pStyle',
            styleType: 'Paragraph',
            finder: fn (string $id): ?Style => $this->styles->findParagraphStyleById($id),
        );

        return new Result(
            value: new Paragraph(
                children: $childrenResult->value,
                styleId: $styleResult->value['styleId'],
                styleName: $styleResult->value['styleName'],
                numbering: $this->readNumbering($properties->firstOrEmpty('w:numPr')),
            ),
            messages: array_merge($childrenResult->messages, $styleResult->messages),
        );
    }

    private function readNumbering(Element $numPr): ?NumberingLevel
    {
        $numId = $numPr->first('w:numId')?->attribute('w:val');
        $ilvl = $numPr->first('w:ilvl')?->attribute('w:val');
        if ($numId === null) {
            return null;
        }

        // Per mammoth: malformed docs may omit w:ilvl while still
        // referencing a numId, in which case we assume level 0.
        return $this->numbering->findLevel($numId, $ilvl ?? '0');
    }

    /**
     * @return Result<DocumentNode>
     */
    private function readRun(Element $element): Result
    {
        $properties = $element->firstOrEmpty('w:rPr');
        $childrenResult = $this->readXmlElements($element->children);
        $styleResult = $this->readStyle(
            properties: $properties,
            styleTagName: 'w:rStyle',
            styleType: 'Run',
            finder: fn (string $id): ?Style => $this->styles->findCharacterStyleById($id),
        );

        return new Result(
            value: new Run(
                children: $childrenResult->value,
                styleId: $styleResult->value['styleId'],
                styleName: $styleResult->value['styleName'],
                isBold: self::readBoolean($properties->first('w:b')),
                isItalic: self::readBoolean($properties->first('w:i')),
                isUnderline: self::readUnderline($properties->first('w:u')),
                isStrikethrough: self::readBoolean($properties->first('w:strike')),
                isAllCaps: self::readBoolean($properties->first('w:caps')),
                isSmallCaps: self::readBoolean($properties->first('w:smallCaps')),
                verticalAlignment: self::readVerticalAlignment($properties->first('w:vertAlign')),
            ),
            messages: array_merge($childrenResult->messages, $styleResult->messages),
        );
    }

    /**
     * @return Result<DocumentNode>
     */
    private function readHyperlink(Element $element): Result
    {
        $relationshipId = $element->attribute('r:id');
        $anchor = $element->attribute('w:anchor');
        $targetFrame = $element->attribute('w:tgtFrame');

        return $this->readXmlElements($element->children)
            ->map(function (array $children) use ($relationshipId, $anchor, $targetFrame): Hyperlink {
                if ($relationshipId !== null) {
                    $href = $this->relationships->findTargetByRelationshipId($relationshipId) ?? '';
                    if ($anchor !== null) {
                        $href = self::replaceFragment($href, $anchor);
                    }

                    return new Hyperlink(children: $children, href: $href, targetFrame: $targetFrame);
                }

                return new Hyperlink(children: $children, anchor: $anchor, targetFrame: $targetFrame);
            });
    }

    private static function replaceFragment(string $uri, string $fragment): string
    {
        $hashIndex = mb_strpos($uri, '#');
        if ($hashIndex !== false) {
            $uri = mb_substr($uri, 0, $hashIndex);
        }

        return $uri.'#'.$fragment;
    }

    /**
     * @param  callable(string): ?Style  $finder
     * @return Result<array{styleId: ?string, styleName: ?string}>
     */
    private function readStyle(Element $properties, string $styleTagName, string $styleType, callable $finder): Result
    {
        $styleElement = $properties->first($styleTagName);
        $styleId = $styleElement?->attribute('w:val');
        if ($styleId === null) {
            return Result::success(['styleId' => null, 'styleName' => null]);
        }

        $style = $finder($styleId);
        if ($style === null) {
            return new Result(
                value: ['styleId' => $styleId, 'styleName' => null],
                messages: [Message::warning(
                    "{$styleType} style with ID {$styleId} was referenced but not defined in the document",
                )],
            );
        }

        return Result::success(['styleId' => $styleId, 'styleName' => $style->name]);
    }

    private static function readBoolean(?Element $element): bool
    {
        if ($element === null) {
            return false;
        }
        $value = $element->attribute('w:val');

        return $value !== 'false' && $value !== '0';
    }

    private static function readUnderline(?Element $element): bool
    {
        if ($element === null) {
            return false;
        }
        $value = $element->attribute('w:val');

        return $value !== null && $value !== 'false' && $value !== '0' && $value !== 'none';
    }

    private static function readVerticalAlignment(?Element $element): VerticalAlignment
    {
        if ($element === null) {
            return VerticalAlignment::Baseline;
        }

        return VerticalAlignment::tryFrom($element->attribute('w:val') ?? '') ?? VerticalAlignment::Baseline;
    }
}
