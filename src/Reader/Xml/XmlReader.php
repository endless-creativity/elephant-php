<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/xml/reader.js

namespace EndlessCreativity\ElephantPhp\Reader\Xml;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

final class XmlReader
{
    /**
     * @param  array<string, string>  $namespaceMap  namespace URI => prefix
     */
    public static function readString(string $xml, array $namespaceMap = []): Element
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $document->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $errors !== []) {
            $message = $errors === [] ? 'Malformed XML' : trim($errors[0]->message);
            throw new RuntimeException("Could not parse XML: {$message}");
        }

        $root = $document->documentElement;
        if ($root === null) {
            throw new RuntimeException('XML document has no root element');
        }

        return self::collapseAlternateContentTree(self::convertElement($root, $namespaceMap));
    }

    /**
     * Walks the tree and replaces every mc:AlternateContent element with the
     * children of its mc:Fallback child. mammoth applies this in
     * lib/docx/office-xml-reader.js so the docx body never sees Office's
     * forwards-compat fallbacks. Done here so every consumer of XmlReader
     * gets the same behaviour.
     */
    private static function collapseAlternateContentTree(Element $element): Element
    {
        return new Element(
            name: $element->name,
            attributes: $element->attributes,
            children: self::collapseAlternateContentChildren($element->children),
        );
    }

    /**
     * @param  list<Node>  $children
     * @return list<Node>
     */
    private static function collapseAlternateContentChildren(array $children): array
    {
        $result = [];
        foreach ($children as $child) {
            if (! $child instanceof Element) {
                $result[] = $child;

                continue;
            }
            if ($child->name === 'mc:AlternateContent') {
                $fallback = $child->first('mc:Fallback');
                if ($fallback === null) {
                    continue;
                }
                foreach (self::collapseAlternateContentChildren($fallback->children) as $node) {
                    $result[] = $node;
                }

                continue;
            }
            $result[] = self::collapseAlternateContentTree($child);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $namespaceMap
     */
    private static function convertNode(DOMNode $node, array $namespaceMap): ?Node
    {
        if ($node instanceof DOMElement) {
            return self::convertElement($node, $namespaceMap);
        }
        if ($node instanceof DOMText) {
            return new Text(value: $node->nodeValue ?? '');
        }

        return null;
    }

    /**
     * @param  array<string, string>  $namespaceMap
     */
    private static function convertElement(DOMElement $element, array $namespaceMap): Element
    {
        $children = [];
        foreach ($element->childNodes as $childNode) {
            $converted = self::convertNode($childNode, $namespaceMap);
            if ($converted !== null) {
                $children[] = $converted;
            }
        }

        $attributes = [];
        /** @var DOMAttr $attribute */
        foreach ($element->attributes as $attribute) {
            $attributes[self::convertName($attribute, $namespaceMap)] = $attribute->value;
        }

        return new Element(
            name: self::convertName($element, $namespaceMap),
            attributes: $attributes,
            children: $children,
        );
    }

    /**
     * @param  array<string, string>  $namespaceMap
     */
    private static function convertName(DOMNode $node, array $namespaceMap): string
    {
        $localName = $node->localName ?? $node->nodeName;

        if ($node->namespaceURI !== null && $node->namespaceURI !== '') {
            $prefix = $namespaceMap[$node->namespaceURI] ?? null;

            return $prefix !== null
                ? $prefix.':'.$localName
                : '{'.$node->namespaceURI.'}'.$localName;
        }

        return $localName;
    }
}
