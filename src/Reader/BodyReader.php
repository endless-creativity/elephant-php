<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/body-reader.js

namespace EndlessCreativity\ElephantPhp\Reader;

use EndlessCreativity\ElephantPhp\Document\Node as DocumentNode;
use EndlessCreativity\ElephantPhp\Document\Paragraph;
use EndlessCreativity\ElephantPhp\Document\Run;
use EndlessCreativity\ElephantPhp\Document\Text;
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

    /**
     * @return Result<?DocumentNode>
     */
    public function readXmlElement(Element $element): Result
    {
        return match ($element->name) {
            'w:p' => $this->readParagraph($element),
            'w:r' => $this->readRun($element),
            'w:t' => Result::success(new Text(value: $element->text())),
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
        return $this->readXmlElements($element->children)
            ->map(fn (array $children): Paragraph => new Paragraph(children: $children));
    }

    /**
     * @return Result<DocumentNode>
     */
    private function readRun(Element $element): Result
    {
        return $this->readXmlElements($element->children)
            ->map(fn (array $children): Run => new Run(children: $children));
    }
}
