<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/writers/html-writer.js + lib/html/index.js

namespace EndlessCreativity\ElephantPhp\Html;

final class HtmlWriter
{
    /**
     * @param  list<Node>  $nodes
     */
    public static function write(array $nodes): string
    {
        $fragments = [];
        foreach ($nodes as $node) {
            self::writeNode($node, $fragments);
        }

        return implode('', $fragments);
    }

    /**
     * @param  list<string>  $fragments
     * @param-out  list<string>  $fragments
     */
    private static function writeNode(Node $node, array &$fragments): void
    {
        if ($node instanceof Text) {
            $fragments[] = self::escapeText($node->value);

            return;
        }

        if ($node instanceof Element) {
            if ($node->isVoid()) {
                $fragments[] = '<'.$node->tag->tagName.self::renderAttributes($node->tag->attributes).' />';

                return;
            }

            $fragments[] = '<'.$node->tag->tagName.self::renderAttributes($node->tag->attributes).'>';
            foreach ($node->children as $child) {
                self::writeNode($child, $fragments);
            }
            $fragments[] = '</'.$node->tag->tagName.'>';
        }
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private static function renderAttributes(array $attributes): string
    {
        $rendered = '';
        foreach ($attributes as $name => $value) {
            $rendered .= ' '.$name.'="'.self::escapeAttribute($value).'"';
        }

        return $rendered;
    }

    private static function escapeText(string $value): string
    {
        return strtr($value, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    private static function escapeAttribute(string $value): string
    {
        return strtr($value, ['&' => '&amp;', '"' => '&quot;', '<' => '&lt;', '>' => '&gt;']);
    }
}
