<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/document-to-html.js + lib/options-reader.js

namespace EndlessCreativity\ElephantPhp\Document;

use EndlessCreativity\ElephantPhp\Html\Element as HtmlElement;
use EndlessCreativity\ElephantPhp\Html\HtmlWriter;
use EndlessCreativity\ElephantPhp\Html\Node as HtmlNode;
use EndlessCreativity\ElephantPhp\Html\Simplifier;
use EndlessCreativity\ElephantPhp\Html\Tag;
use EndlessCreativity\ElephantPhp\Html\Text as HtmlText;
use EndlessCreativity\ElephantPhp\Message;
use EndlessCreativity\ElephantPhp\Result;

final class DocumentConverter
{
    /**
     * Hardcoded default paragraph style map, ported from
     * mammoth.js/lib/options-reader.js. The DSL parser will allow users to
     * extend this in a later commit; for now any non-default mapping must be
     * added by editing this constant.
     *
     * @var list<array{styleId?: string, styleName?: string, tag: string}>
     */
    private const DEFAULT_PARAGRAPH_STYLE_MAP = [
        ['styleId' => 'Heading1', 'tag' => 'h1'],
        ['styleId' => 'Heading2', 'tag' => 'h2'],
        ['styleId' => 'Heading3', 'tag' => 'h3'],
        ['styleId' => 'Heading4', 'tag' => 'h4'],
        ['styleId' => 'Heading5', 'tag' => 'h5'],
        ['styleId' => 'Heading6', 'tag' => 'h6'],
        ['styleName' => 'Heading 1', 'tag' => 'h1'],
        ['styleName' => 'Heading 2', 'tag' => 'h2'],
        ['styleName' => 'Heading 3', 'tag' => 'h3'],
        ['styleName' => 'Heading 4', 'tag' => 'h4'],
        ['styleName' => 'Heading 5', 'tag' => 'h5'],
        ['styleName' => 'Heading 6', 'tag' => 'h6'],
        ['styleId' => 'Heading', 'tag' => 'h1'],
        ['styleName' => 'Heading', 'tag' => 'h1'],
    ];

    /**
     * @return Result<string>
     */
    public function convertToHtml(Document $document): Result
    {
        $messages = [];
        $htmlNodes = $this->convertNodes($document->children, $messages);

        return new Result(
            value: HtmlWriter::write(Simplifier::simplify($htmlNodes)),
            messages: $messages,
        );
    }

    /**
     * @param  list<Node>  $nodes
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertNodes(array $nodes, array &$messages): array
    {
        $html = [];
        foreach ($nodes as $node) {
            foreach ($this->convertNode($node, $messages) as $htmlNode) {
                $html[] = $htmlNode;
            }
        }

        return $html;
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertNode(Node $node, array &$messages): array
    {
        if ($node instanceof Paragraph) {
            return $this->convertParagraph($node, $messages);
        }

        if ($node instanceof Run) {
            return $this->convertRun($node, $messages);
        }

        if ($node instanceof Hyperlink) {
            return $this->convertHyperlink($node, $messages);
        }

        if ($node instanceof Table) {
            return [new HtmlElement(
                tag: new Tag(tagName: 'table'),
                children: $this->convertNodes($node->children, $messages),
            )];
        }

        if ($node instanceof TableRow) {
            return [new HtmlElement(
                tag: new Tag(tagName: 'tr'),
                children: $this->convertTableRowChildren($node, $messages),
            )];
        }

        if ($node instanceof TableCell) {
            return [new HtmlElement(
                tag: new Tag(tagName: 'td', attributes: self::tableCellAttributes($node)),
                children: $this->convertNodes($node->children, $messages),
            )];
        }

        if ($node instanceof Text) {
            return [new HtmlText(value: $node->value)];
        }

        return [];
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertTableRowChildren(TableRow $row, array &$messages): array
    {
        $cells = [];
        $tagName = $row->isHeader ? 'th' : 'td';
        foreach ($row->children as $child) {
            if ($child instanceof TableCell) {
                $cells[] = new HtmlElement(
                    tag: new Tag(tagName: $tagName, attributes: self::tableCellAttributes($child)),
                    children: $this->convertNodes($child->children, $messages),
                );
            } else {
                foreach ($this->convertNode($child, $messages) as $node) {
                    $cells[] = $node;
                }
            }
        }

        return $cells;
    }

    /**
     * @return array<string, string>
     */
    private static function tableCellAttributes(TableCell $cell): array
    {
        $attributes = [];
        if ($cell->colSpan !== 1) {
            $attributes['colspan'] = (string) $cell->colSpan;
        }
        if ($cell->rowSpan !== 1) {
            $attributes['rowspan'] = (string) $cell->rowSpan;
        }

        return $attributes;
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertHyperlink(Hyperlink $hyperlink, array &$messages): array
    {
        $children = $this->convertNodes($hyperlink->children, $messages);

        if ($hyperlink->href === null && $hyperlink->anchor === null) {
            return $children;
        }

        $href = $hyperlink->anchor !== null ? '#'.$hyperlink->anchor : ($hyperlink->href ?? '');
        $attributes = ['href' => $href];
        if ($hyperlink->targetFrame !== null) {
            $attributes['target'] = $hyperlink->targetFrame;
        }

        return [new HtmlElement(
            tag: new Tag(tagName: 'a', attributes: $attributes, fresh: false),
            children: $children,
        )];
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertParagraph(Paragraph $paragraph, array &$messages): array
    {
        if ($paragraph->numbering !== null) {
            return [self::wrapAsListItem(
                $paragraph->numbering,
                $this->convertNodes($paragraph->children, $messages),
            )];
        }

        $tagName = self::resolveParagraphTag($paragraph, $messages);

        return [new HtmlElement(
            tag: new Tag(tagName: $tagName),
            children: $this->convertNodes($paragraph->children, $messages),
        )];
    }

    /**
     * Builds the list-item wrapping for a single numbered paragraph as a
     * stack of `<list><li>` pairs deep enough to express the paragraph's
     * level. The deepest `<li>` is fresh so the simplifier preserves it as
     * a separate item; everything above is non-fresh so adjacent siblings
     * collapse into a single nested list. This avoids the need for the full
     * style-map DSL while still producing valid nested-list HTML.
     *
     * @param  list<HtmlNode>  $children
     */
    private static function wrapAsListItem(NumberingLevel $numbering, array $children): HtmlElement
    {
        $listTag = $numbering->isOrdered ? 'ol' : 'ul';

        $node = new HtmlElement(
            tag: new Tag(tagName: 'li', fresh: true),
            children: $children,
        );
        $node = new HtmlElement(
            tag: new Tag(tagName: $listTag, fresh: false),
            children: [$node],
        );
        for ($depth = 0; $depth < $numbering->level; $depth++) {
            $node = new HtmlElement(
                tag: new Tag(tagName: 'li', fresh: false),
                children: [$node],
            );
            $node = new HtmlElement(
                tag: new Tag(tagName: $listTag, fresh: false),
                children: [$node],
            );
        }

        return $node;
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     */
    private static function resolveParagraphTag(Paragraph $paragraph, array &$messages): string
    {
        foreach (self::DEFAULT_PARAGRAPH_STYLE_MAP as $entry) {
            if (isset($entry['styleId']) && $entry['styleId'] === $paragraph->styleId) {
                return $entry['tag'];
            }
            if (isset($entry['styleName']) && $entry['styleName'] === $paragraph->styleName) {
                return $entry['tag'];
            }
        }

        if ($paragraph->styleId !== null) {
            $messages[] = Message::warning(
                "Unrecognised paragraph style: '{$paragraph->styleName}' (Style ID: {$paragraph->styleId})",
            );
        }

        return 'p';
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertRun(Run $run, array &$messages): array
    {
        if ($run->styleId !== null) {
            $messages[] = Message::warning(
                "Unrecognised run style: '{$run->styleName}' (Style ID: {$run->styleId})",
            );
        }

        $nodes = $this->convertNodes($run->children, $messages);

        // Wrap from innermost to outermost, matching the push order in
        // mammoth.js convertRun. <strong> ends up as the outermost wrapper.
        if ($run->isStrikethrough) {
            $nodes = self::wrap('s', $nodes);
        }
        if ($run->verticalAlignment === VerticalAlignment::Subscript) {
            $nodes = self::wrap('sub', $nodes);
        }
        if ($run->verticalAlignment === VerticalAlignment::Superscript) {
            $nodes = self::wrap('sup', $nodes);
        }
        if ($run->isItalic) {
            $nodes = self::wrap('em', $nodes);
        }
        if ($run->isBold) {
            $nodes = self::wrap('strong', $nodes);
        }

        return $nodes;
    }

    /**
     * @param  list<HtmlNode>  $nodes
     * @return list<HtmlNode>
     */
    private static function wrap(string $tagName, array $nodes): array
    {
        return [new HtmlElement(tag: new Tag(tagName: $tagName, fresh: false), children: $nodes)];
    }
}
