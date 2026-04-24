<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/docx/body-reader.js

namespace EndlessCreativity\ElephantPhp\Reader;

use Closure;
use EndlessCreativity\ElephantPhp\Document\BookmarkStart;
use EndlessCreativity\ElephantPhp\Document\BreakElement;
use EndlessCreativity\ElephantPhp\Document\BreakType;
use EndlessCreativity\ElephantPhp\Document\CommentReference;
use EndlessCreativity\ElephantPhp\Document\Hyperlink;
use EndlessCreativity\ElephantPhp\Document\Image;
use EndlessCreativity\ElephantPhp\Document\Node as DocumentNode;
use EndlessCreativity\ElephantPhp\Document\NoteReference;
use EndlessCreativity\ElephantPhp\Document\NoteType;
use EndlessCreativity\ElephantPhp\Document\NumberingLevel;
use EndlessCreativity\ElephantPhp\Document\Paragraph;
use EndlessCreativity\ElephantPhp\Document\Run;
use EndlessCreativity\ElephantPhp\Document\Tab;
use EndlessCreativity\ElephantPhp\Document\Table;
use EndlessCreativity\ElephantPhp\Document\TableCell;
use EndlessCreativity\ElephantPhp\Document\TableRow;
use EndlessCreativity\ElephantPhp\Document\Text;
use EndlessCreativity\ElephantPhp\Document\VerticalAlignment;
use EndlessCreativity\ElephantPhp\Message;
use EndlessCreativity\ElephantPhp\Reader\Xml\Element;
use EndlessCreativity\ElephantPhp\Reader\Xml\Node as XmlNode;
use EndlessCreativity\ElephantPhp\Result;

final class BodyReader
{
    /** @var array<string, true> */
    private const SUPPORTED_IMAGE_TYPES = [
        'image/png' => true,
        'image/gif' => true,
        'image/jpeg' => true,
        'image/svg+xml' => true,
        'image/tiff' => true,
    ];

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
     * @param  ?Closure(string): string  $imageReader  Reader for embedded image
     *                                                 bytes by zip-entry path.
     *                                                 Without it, w:drawing
     *                                                 elements emit a warning
     *                                                 and are dropped.
     */
    public function __construct(
        private readonly Styles $styles = new Styles(),
        private readonly Relationships $relationships = new Relationships(),
        private readonly Numbering $numbering = new Numbering(),
        private readonly ContentTypes $contentTypes = new ContentTypes(),
        private readonly ?Closure $imageReader = null,
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
            'w:tab' => Result::success(new Tab()),
            'w:noBreakHyphen' => Result::success(new Text(value: "\u{2011}")),
            'w:softHyphen' => Result::success(new Text(value: "\u{00AD}")),
            'w:br' => Result::success(self::readBreak($element)),
            'w:bookmarkStart' => Result::success(self::readBookmarkStart($element)),
            'w:hyperlink' => $this->readHyperlink($element),
            'w:tbl' => $this->readTable($element),
            'w:tr' => $this->readTableRow($element),
            'w:tc' => $this->readTableCell($element),
            'w:drawing' => $this->readDrawingChildren($element),
            'wp:inline', 'wp:anchor' => $this->readDrawingElement($element),
            'w:footnoteReference' => self::readNoteReference($element, NoteType::Footnote),
            'w:endnoteReference' => self::readNoteReference($element, NoteType::Endnote),
            'w:commentReference' => self::readCommentReference($element),
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
            // Content controls (Word's structured document tags) are
            // transparent: emit just the contents of <w:sdtContent> as if
            // they were siblings of the surrounding scope. Matches
            // mammoth's "w:sdt" handler when no checkbox is present (the
            // checkbox case is TODO).
            if ($node->name === 'w:sdt') {
                $perElement[] = $this->readXmlElements($node->firstOrEmpty('w:sdtContent')->children);

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
                highlight: self::readHighlight($properties->first('w:highlight')),
                font: $properties->first('w:rFonts')?->attribute('w:ascii'),
                fontSize: self::readFontSize($properties->first('w:sz')),
            ),
            messages: array_merge($childrenResult->messages, $styleResult->messages),
        );
    }

    private static function readHighlight(?Element $element): ?string
    {
        if ($element === null) {
            return null;
        }
        $value = $element->attribute('w:val');

        // Mammoth treats "none" as not highlighted, drops empty values.
        if ($value === null || $value === '' || $value === 'none') {
            return null;
        }

        return $value;
    }

    private static function readFontSize(?Element $element): ?float
    {
        if ($element === null) {
            return null;
        }
        $value = $element->attribute('w:val');
        // w:sz is in half-points; halve to get the size in points. Only
        // accept all-digit values, matching mammoth.
        if ($value === null || preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        return ((int) $value) / 2;
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

    /**
     * @return Result<DocumentNode>
     */
    private function readTable(Element $element): Result
    {
        return $this->readXmlElements($element->children)
            ->map(fn (array $children): Table => new Table(
                children: self::resolveRowSpans($children),
            ));
    }

    /**
     * @return Result<?DocumentNode>
     */
    private function readTableRow(Element $element): Result
    {
        $properties = $element->firstOrEmpty('w:trPr');

        // Per ECMA-376 § 17.13.5.12 (Deleted Table Row), a row marked as
        // deleted is effectively absent.
        if ($properties->first('w:del') !== null) {
            return Result::success(null);
        }

        $isHeader = $properties->first('w:tblHeader') !== null;

        return $this->readXmlElements($element->children)
            ->map(fn (array $children): TableRow => new TableRow(
                children: $children,
                isHeader: $isHeader,
            ));
    }

    /**
     * @return Result<DocumentNode>
     */
    private function readTableCell(Element $element): Result
    {
        $properties = $element->firstOrEmpty('w:tcPr');
        $gridSpan = $properties->first('w:gridSpan')?->attribute('w:val');

        return $this->readXmlElements($element->children)
            ->map(fn (array $children): TableCell => new TableCell(
                children: $children,
                colSpan: $gridSpan !== null ? (int) $gridSpan : 1,
                vMerge: self::readVMerge($properties),
            ));
    }

    /**
     * @return Result<?DocumentNode>
     */
    private function readDrawingChildren(Element $element): Result
    {
        $inline = $element->first('wp:inline') ?? $element->first('wp:anchor');

        return $inline === null
            ? Result::success(null)
            : $this->readDrawingElement($inline);
    }

    /**
     * @return Result<?DocumentNode>
     */
    private function readDrawingElement(Element $inline): Result
    {
        $blip = $inline
            ->firstOrEmpty('a:graphic')
            ->firstOrEmpty('a:graphicData')
            ->firstOrEmpty('pic:pic')
            ->firstOrEmpty('pic:blipFill')
            ->first('a:blip');
        if ($blip === null) {
            return Result::success(null);
        }

        $imageDescriptor = $this->resolveBlipImage($blip);
        if ($imageDescriptor === null) {
            return new Result(
                value: null,
                messages: [Message::warning('Could not find image file for a:blip element')],
            );
        }
        [$path, $reader] = $imageDescriptor;

        $contentType = $this->contentTypes->findContentType($path);

        $docPr = $inline->firstOrEmpty('wp:docPr');
        $altText = self::firstNonBlank($docPr->attribute('descr'), $docPr->attribute('title'));

        $image = new Image(
            readBytes: $reader,
            contentType: $contentType,
            altText: $altText,
        );

        $messages = [];
        if ($contentType !== null && ! isset(self::SUPPORTED_IMAGE_TYPES[$contentType])) {
            $messages[] = Message::warning(
                "Image of type {$contentType} is unlikely to display in web browsers",
            );
        }

        // wp:docPr/a:hlinkClick wraps the image in a hyperlink. mammoth
        // does this around an already-built image element.
        $hlinkRelId = $docPr->firstOrEmpty('a:hlinkClick')->attribute('r:id');
        if ($hlinkRelId !== null) {
            $href = $this->relationships->findTargetByRelationshipId($hlinkRelId);
            if ($href !== null) {
                return new Result(
                    value: new Hyperlink(children: [$image], href: $href),
                    messages: $messages,
                );
            }
        }

        return new Result(value: $image, messages: $messages);
    }

    /**
     * Resolves the image bytes reader for an a:blip element, supporting
     * both r:embed (image inside the docx zip) and r:link (linked from
     * disk via an absolute URI).
     *
     * @return ?array{string, Closure(): string}  [zip-or-disk path, reader closure]
     */
    private function resolveBlipImage(Element $blip): ?array
    {
        $embedId = $blip->attribute('r:embed');
        $linkId = $blip->attribute('r:link');

        if ($embedId !== null) {
            $target = $this->relationships->findTargetByRelationshipId($embedId);
            if ($target === null || $this->imageReader === null) {
                return null;
            }
            $path = self::joinImagePath('word', $target);
            $reader = $this->imageReader;

            return [$path, static fn (): string => $reader($path)];
        }

        if ($linkId !== null) {
            $target = $this->relationships->findTargetByRelationshipId($linkId);
            if ($target === null) {
                return null;
            }

            // r:link points at a path on disk relative to (or absolute
            // from) the docx host. We try to read it directly; if the
            // file isn't available the closure throws when invoked, and
            // DocumentConverter records that as a Message::error and
            // drops the image (mammoth's recoveringConvertImage).
            return [$target, static function () use ($target): string {
                $bytes = @file_get_contents($target);
                if ($bytes === false) {
                    throw new \RuntimeException("Could not read linked image at {$target}");
                }

                return $bytes;
            }];
        }

        return null;
    }

    private static function joinImagePath(string $base, string $target): string
    {
        return str_starts_with($target, '/') ? mb_substr($target, 1) : $base.'/'.$target;
    }

    private static function firstNonBlank(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function readVMerge(Element $properties): ?bool
    {
        $element = $properties->first('w:vMerge');
        if ($element === null) {
            return null;
        }
        $value = $element->attribute('w:val');

        // Mammoth: a w:vMerge with no w:val (or w:val="continue") means
        // the cell continues a vertical merge from above.
        return $value === null || $value === 'continue';
    }

    /**
     * Walks the rows of a table and resolves vertical merges into rowSpan
     * counts on the anchor cells, dropping the continuation cells. This
     * mirrors the in-place mutation in mammoth's calculateRowSpans, but
     * returns fresh readonly nodes instead.
     *
     * @param  list<DocumentNode>  $children
     * @return list<DocumentNode>
     */
    private static function resolveRowSpans(array $children): array
    {
        // Step 1: bail out unchanged if any non-row child or any non-cell row
        // child appears -- mammoth removes _vMerge in that case rather than
        // attempting a merge. We do the equivalent by stripping vMerge from
        // any TableCell encountered.
        foreach ($children as $row) {
            if (! $row instanceof TableRow) {
                return self::stripVMerge($children);
            }
            foreach ($row->children as $cell) {
                if (! $cell instanceof TableCell) {
                    return self::stripVMerge($children);
                }
            }
        }

        /** @var list<list<TableCell>> $rowsCells */
        $rowsCells = [];
        foreach ($children as $row) {
            /** @var TableRow $row */
            $rowsCells[] = array_values(array_filter(
                $row->children,
                static fn ($cell): bool => $cell instanceof TableCell,
            ));
        }

        /** @var array<int, array{rowIndex: int, cellIndex: int, rowSpan: int}> $columns */
        $columns = [];
        foreach ($rowsCells as $rowIndex => $cells) {
            $cellIndex = 0;
            foreach ($cells as $i => $cell) {
                if ($cell->vMerge === true && isset($columns[$cellIndex])) {
                    $columns[$cellIndex]['rowSpan']++;
                    // Mark this cell as merged-away by setting vMerge to a
                    // sentinel we drop in step 3.
                    $rowsCells[$rowIndex][$i] = new TableCell(
                        children: $cell->children,
                        colSpan: $cell->colSpan,
                        rowSpan: $cell->rowSpan,
                        vMerge: true,
                    );
                } else {
                    $columns[$cellIndex] = [
                        'rowIndex' => $rowIndex,
                        'cellIndex' => $i,
                        'rowSpan' => 1,
                    ];
                    // Reset vMerge so the cell becomes an anchor.
                    $rowsCells[$rowIndex][$i] = new TableCell(
                        children: $cell->children,
                        colSpan: $cell->colSpan,
                        rowSpan: 1,
                        vMerge: false,
                    );
                }
                $cellIndex += $cell->colSpan;
            }
        }

        // Step 2: write the computed rowSpan back onto each anchor.
        foreach ($columns as $column) {
            if ($column['rowSpan'] > 1) {
                $anchor = $rowsCells[$column['rowIndex']][$column['cellIndex']];
                $rowsCells[$column['rowIndex']][$column['cellIndex']] = new TableCell(
                    children: $anchor->children,
                    colSpan: $anchor->colSpan,
                    rowSpan: $column['rowSpan'],
                    vMerge: false,
                );
            }
        }

        // Step 3: drop merged-away cells and rebuild rows with cleaned cells.
        $result = [];
        foreach ($children as $rowIndex => $row) {
            /** @var TableRow $row */
            $cleanedCells = [];
            foreach ($rowsCells[$rowIndex] as $cell) {
                if ($cell->vMerge === true) {
                    continue;
                }
                $cleanedCells[] = new TableCell(
                    children: $cell->children,
                    colSpan: $cell->colSpan,
                    rowSpan: $cell->rowSpan,
                    vMerge: null,
                );
            }
            $result[] = new TableRow(children: $cleanedCells, isHeader: $row->isHeader);
        }

        return $result;
    }

    /**
     * @param  list<DocumentNode>  $children
     * @return list<DocumentNode>
     */
    private static function stripVMerge(array $children): array
    {
        $result = [];
        foreach ($children as $node) {
            if ($node instanceof TableRow) {
                $cells = [];
                foreach ($node->children as $cell) {
                    $cells[] = $cell instanceof TableCell
                        ? new TableCell(
                            children: $cell->children,
                            colSpan: $cell->colSpan,
                            rowSpan: $cell->rowSpan,
                            vMerge: null,
                        )
                        : $cell;
                }
                $result[] = new TableRow(children: $cells, isHeader: $node->isHeader);
            } else {
                $result[] = $node;
            }
        }

        return $result;
    }

    private static function readBreak(Element $element): BreakElement
    {
        // Mammoth maps w:type=textWrapping (and a missing type) to a line
        // break, w:type=page to a page break, w:type=column to a column
        // break. Anything else falls back to a line break, matching mammoth.
        $type = $element->attribute('w:type');

        return match ($type) {
            'page' => new BreakElement(breakType: BreakType::Page),
            'column' => new BreakElement(breakType: BreakType::Column),
            default => new BreakElement(breakType: BreakType::Line),
        };
    }

    private static function readBookmarkStart(Element $element): ?BookmarkStart
    {
        $name = $element->attribute('w:name');
        if ($name === null) {
            return null;
        }
        // Word inserts a synthetic _GoBack bookmark on every save -- mammoth
        // strips it because rendering it as <a id="_GoBack"></a> in the
        // output is just noise.
        if ($name === '_GoBack') {
            return null;
        }

        return new BookmarkStart(name: $name);
    }

    /**
     * @return Result<?DocumentNode>
     */
    private static function readNoteReference(Element $element, NoteType $noteType): Result
    {
        $noteId = $element->attribute('w:id');
        if ($noteId === null) {
            return Result::success(null);
        }

        return Result::success(new NoteReference(noteType: $noteType, noteId: $noteId));
    }

    /**
     * @return Result<?DocumentNode>
     */
    private static function readCommentReference(Element $element): Result
    {
        $commentId = $element->attribute('w:id');
        if ($commentId === null) {
            return Result::success(null);
        }

        return Result::success(new CommentReference(commentId: $commentId));
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
