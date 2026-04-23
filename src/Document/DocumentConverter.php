<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/document-to-html.js

namespace EndlessCreativity\ElephantPhp\Document;

use EndlessCreativity\ElephantPhp\Html\Element as HtmlElement;
use EndlessCreativity\ElephantPhp\Html\HtmlWriter;
use EndlessCreativity\ElephantPhp\Html\Node as HtmlNode;
use EndlessCreativity\ElephantPhp\Html\Simplifier;
use EndlessCreativity\ElephantPhp\Html\Tag;
use EndlessCreativity\ElephantPhp\Html\Text as HtmlText;
use EndlessCreativity\ElephantPhp\Result;

final class DocumentConverter
{
    /**
     * @return Result<string>
     */
    public function convertToHtml(Document $document): Result
    {
        $htmlNodes = $this->convertNodes($document->children);

        return Result::success(HtmlWriter::write(Simplifier::simplify($htmlNodes)));
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<HtmlNode>
     */
    private function convertNodes(array $nodes): array
    {
        $html = [];
        foreach ($nodes as $node) {
            foreach ($this->convertNode($node) as $htmlNode) {
                $html[] = $htmlNode;
            }
        }

        return $html;
    }

    /**
     * @return list<HtmlNode>
     */
    private function convertNode(Node $node): array
    {
        if ($node instanceof Paragraph) {
            return [new HtmlElement(
                tag: new Tag(tagName: 'p'),
                children: $this->convertNodes($node->children),
            )];
        }

        if ($node instanceof Run) {
            return $this->convertNodes($node->children);
        }

        if ($node instanceof Text) {
            return [new HtmlText(value: $node->value)];
        }

        return [];
    }
}
