<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/document-to-html.js + lib/options-reader.js

namespace EndlessCreativity\ElephantPhp\Document;

use EndlessCreativity\ElephantPhp\Html\Element as HtmlElement;
use EndlessCreativity\ElephantPhp\Html\ForceWrite;
use EndlessCreativity\ElephantPhp\Html\HtmlWriter;
use EndlessCreativity\ElephantPhp\Html\MarkdownWriter;
use EndlessCreativity\ElephantPhp\Html\Node as HtmlNode;
use EndlessCreativity\ElephantPhp\Html\Simplifier;
use EndlessCreativity\ElephantPhp\Html\Tag;
use EndlessCreativity\ElephantPhp\Html\Text as HtmlText;
use EndlessCreativity\ElephantPhp\Image\DataUriImageHandler;
use EndlessCreativity\ElephantPhp\Image\ImageHandler;
use EndlessCreativity\ElephantPhp\Message;
use EndlessCreativity\ElephantPhp\Result;
use EndlessCreativity\ElephantPhp\Style\RunProperty;
use EndlessCreativity\ElephantPhp\Style\StyleMap;
use Throwable;

final class DocumentConverter
{
    private readonly StyleMap $styleMap;

    /**
     * Per-conversion mutable state. Reset at the start of every
     * convertWith() so a single converter instance can be reused safely
     * across documents (PHP is single-threaded per request anyway).
     *
     * @var list<NoteReference>
     */
    private array $noteReferences = [];

    private int $noteCounter = 1;

    /** @var list<array{label: string, comment: Comment}> */
    private array $referencedComments = [];

    private int $commentCounter = 1;

    private Comments $comments;

    public function __construct(
        ?StyleMap $styleMap = null,
        private readonly ImageHandler $imageHandler = new DataUriImageHandler(),
    ) {
        $this->styleMap = $styleMap ?? StyleMap::default();
        $this->comments = new Comments();
    }

    /**
     * @return Result<string>
     */
    public function convertToHtml(Document $document): Result
    {
        return $this->convertWith($document, HtmlWriter::write(...));
    }

    /**
     * @return Result<string>
     */
    public function convertToMarkdown(Document $document): Result
    {
        return $this->convertWith($document, MarkdownWriter::write(...));
    }

    /**
     * @param  callable(list<HtmlNode>): string  $writer
     * @return Result<string>
     */
    private function convertWith(Document $document, callable $writer): Result
    {
        $this->noteReferences = [];
        $this->noteCounter = 1;
        $this->referencedComments = [];
        $this->commentCounter = 1;
        $this->comments = $document->comments;

        $messages = [];
        $htmlNodes = $this->convertNodes($document->children, $messages);

        $noteItems = $this->renderCollectedNotes($document->notes, $messages);
        if ($noteItems !== []) {
            $htmlNodes[] = new HtmlElement(
                tag: new Tag(tagName: 'ol'),
                children: $noteItems,
            );
        }

        $commentItems = $this->renderCollectedComments($messages);
        if ($commentItems !== []) {
            $htmlNodes[] = new HtmlElement(
                tag: new Tag(tagName: 'dl'),
                children: $commentItems,
            );
        }

        return new Result(
            value: $writer(Simplifier::simplify($htmlNodes)),
            messages: $messages,
        );
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlElement>
     */
    private function renderCollectedNotes(Notes $notes, array &$messages): array
    {
        $items = [];
        foreach ($this->noteReferences as $reference) {
            $note = $notes->resolve($reference);
            if ($note !== null) {
                $items[] = $this->renderNoteListItem($note, $messages);
            }
        }

        return $items;
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     */
    private function renderNoteListItem(Note $note, array &$messages): HtmlElement
    {
        $body = $this->convertNodes($note->body, $messages);
        // Mammoth appends an inline backlink in a non-fresh <p> so it merges
        // into the trailing paragraph of the body when one exists.
        $backLink = new HtmlElement(
            tag: new Tag(tagName: 'p', fresh: false),
            children: [
                new HtmlText(value: ' '),
                new HtmlElement(
                    tag: new Tag(tagName: 'a', attributes: ['href' => '#'.self::noteRefId($note->noteType, $note->noteId)]),
                    children: [new HtmlText(value: '↑')],
                ),
            ],
        );

        return new HtmlElement(
            tag: new Tag(tagName: 'li', attributes: ['id' => self::noteId($note->noteType, $note->noteId)]),
            children: [...$body, $backLink],
        );
    }

    /**
     * @return list<HtmlNode>
     */
    private function convertCommentReference(CommentReference $reference): array
    {
        $mapping = $this->styleMap->findForCommentReference();
        if ($mapping === null) {
            // mammoth's default is htmlPaths.ignore: no inline marker, no
            // <dl> entry. Users opt in via "comment-reference => sup" or
            // similar.
            return [];
        }
        $comment = $this->comments->findById($reference->commentId);
        if ($comment === null) {
            return [];
        }

        $count = $this->commentCounter++;
        $label = '['.($comment->authorInitials ?? '').$count.']';
        $this->referencedComments[] = ['label' => $label, 'comment' => $comment];

        $anchor = new HtmlElement(
            tag: new Tag(tagName: 'a', attributes: [
                'href' => '#'.self::commentTargetId($comment->commentId),
                'id' => self::commentReferenceId($comment->commentId),
            ]),
            children: [new HtmlText(value: $label)],
        );

        return $mapping->to->applyTo([$anchor]);
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlElement>
     */
    private function renderCollectedComments(array &$messages): array
    {
        $items = [];
        foreach ($this->referencedComments as $entry) {
            $comment = $entry['comment'];
            $body = $this->convertNodes($comment->body, $messages);
            $body[] = new HtmlElement(
                tag: new Tag(tagName: 'p', fresh: false),
                children: [
                    new HtmlText(value: ' '),
                    new HtmlElement(
                        tag: new Tag(tagName: 'a', attributes: [
                            'href' => '#'.self::commentReferenceId($comment->commentId),
                        ]),
                        children: [new HtmlText(value: '↑')],
                    ),
                ],
            );

            $items[] = new HtmlElement(
                tag: new Tag(tagName: 'dt', attributes: [
                    'id' => self::commentTargetId($comment->commentId),
                ]),
                children: [new HtmlText(value: 'Comment '.$entry['label'])],
            );
            $items[] = new HtmlElement(
                tag: new Tag(tagName: 'dd'),
                children: $body,
            );
        }

        return $items;
    }

    private static function commentTargetId(string $id): string
    {
        return 'comment-'.$id;
    }

    private static function commentReferenceId(string $id): string
    {
        return 'comment-ref-'.$id;
    }

    private static function noteId(NoteType $type, string $id): string
    {
        return $type->value.'-'.$id;
    }

    private static function noteRefId(NoteType $type, string $id): string
    {
        return $type->value.'-ref-'.$id;
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
            return [$this->convertTable($node, $messages)];
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

        if ($node instanceof Image) {
            return $this->convertImage($node, $messages);
        }

        if ($node instanceof NoteReference) {
            return [$this->convertNoteReference($node)];
        }

        if ($node instanceof CommentReference) {
            return $this->convertCommentReference($node);
        }

        if ($node instanceof Tab) {
            return [new HtmlText(value: "\t")];
        }

        if ($node instanceof BreakElement) {
            return $this->convertBreak($node);
        }

        if ($node instanceof BookmarkStart) {
            return [new HtmlElement(
                tag: new Tag(tagName: 'a', attributes: ['id' => $node->name], fresh: true),
                children: [new ForceWrite()],
            )];
        }

        if ($node instanceof Text) {
            return [new HtmlText(value: $node->value)];
        }

        return [];
    }

    /**
     * @return list<HtmlNode>
     */
    private function convertBreak(BreakElement $break): array
    {
        // Mammoth: a user-supplied `br[type='X'] => path` mapping wins;
        // otherwise line breaks default to <br> and page/column breaks emit
        // nothing.
        $mapping = $this->styleMap->findForBreak($break->breakType);
        if ($mapping !== null) {
            return $mapping->to->applyTo([]);
        }

        return $break->breakType === BreakType::Line
            ? [new HtmlElement(tag: new Tag(tagName: 'br'))]
            : [];
    }

    private function convertNoteReference(NoteReference $reference): HtmlElement
    {
        $this->noteReferences[] = $reference;
        $number = $this->noteCounter++;

        return new HtmlElement(
            tag: new Tag(tagName: 'sup'),
            children: [new HtmlElement(
                tag: new Tag(tagName: 'a', attributes: [
                    'href' => '#'.self::noteId($reference->noteType, $reference->noteId),
                    'id' => self::noteRefId($reference->noteType, $reference->noteId),
                ]),
                children: [new HtmlText(value: '['.$number.']')],
            )],
        );
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertImage(Image $image, array &$messages): array
    {
        try {
            $attributes = $this->imageHandler->attributes($image);
        } catch (Throwable $error) {
            $messages[] = Message::error($error->getMessage());

            return [];
        }

        if ($image->altText !== null && ! isset($attributes['alt'])) {
            $attributes = ['alt' => $image->altText] + $attributes;
        }

        return [new HtmlElement(tag: new Tag(tagName: 'img', attributes: $attributes))];
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     */
    private function convertTable(Table $table, array &$messages): HtmlElement
    {
        $bodyIndex = self::findBodyRowIndex($table);
        if ($bodyIndex === 0) {
            // No header rows -- emit <table><tr>...</tr></table> as before.
            return new HtmlElement(
                tag: new Tag(tagName: 'table'),
                children: $this->convertNodes($table->children, $messages),
            );
        }

        $headSlice = array_slice($table->children, 0, $bodyIndex);
        $bodySlice = array_slice($table->children, $bodyIndex);

        $children = [new HtmlElement(
            tag: new Tag(tagName: 'thead'),
            children: $this->convertNodes($headSlice, $messages),
        )];
        if ($bodySlice !== []) {
            $children[] = new HtmlElement(
                tag: new Tag(tagName: 'tbody'),
                children: $this->convertNodes($bodySlice, $messages),
            );
        }

        return new HtmlElement(tag: new Tag(tagName: 'table'), children: $children);
    }

    private static function findBodyRowIndex(Table $table): int
    {
        foreach ($table->children as $i => $child) {
            if (! $child instanceof TableRow || ! $child->isHeader) {
                return $i;
            }
        }

        return count($table->children);
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

        $children = $this->convertNodes($paragraph->children, $messages);

        $mapping = $this->styleMap->findForParagraph($paragraph);
        if ($mapping !== null) {
            return $mapping->to->applyTo($children);
        }

        if ($paragraph->styleId !== null) {
            $messages[] = Message::warning(
                "Unrecognised paragraph style: '{$paragraph->styleName}' (Style ID: {$paragraph->styleId})",
            );
        }

        return [new HtmlElement(tag: new Tag(tagName: 'p'), children: $children)];
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
        // Deepest <list> wraps just this fresh <li>. It may merge with a
        // sibling deepest list of the same kind, but should NOT merge across
        // ul/ol boundaries -- so no matchAlternativeTagNames here.
        $node = new HtmlElement(
            tag: new Tag(tagName: $listTag, fresh: false),
            children: [$node],
        );
        // Outer wrappers (one (li, list) pair per ancestor level) merge into
        // whatever the parent level previously emitted, even when the parent
        // is the other list kind. matchAlternativeTagNames lets a level-1
        // <ol> wrapper collapse into a level-0 <ul> (and vice versa),
        // matching mammoth's `ul|ol` multi-tag matcher.
        for ($depth = 0; $depth < $numbering->level; $depth++) {
            $node = new HtmlElement(
                tag: new Tag(tagName: 'li', fresh: false),
                children: [$node],
            );
            $node = new HtmlElement(
                tag: new Tag(
                    tagName: $listTag,
                    fresh: false,
                    matchAlternativeTagNames: ['ul', 'ol'],
                ),
                children: [$node],
            );
        }

        return $node;
    }

    /**
     * @param  list<Message>  $messages
     * @param-out  list<Message>  $messages
     * @return list<HtmlNode>
     */
    private function convertRun(Run $run, array &$messages): array
    {
        $nodes = $this->convertNodes($run->children, $messages);

        // Inline styling wrappers, innermost first to outermost, matching
        // mammoth.js convertRun's push order. Each property may be wrapped
        // by either a user-supplied DSL matcher (e.g. "b => mark") or, if
        // none is set, mammoth's default tag for that property.
        if ($run->highlight !== null) {
            $highlightMapping = $this->styleMap->findForHighlight($run->highlight);
            if ($highlightMapping !== null) {
                $nodes = $highlightMapping->to->applyTo($nodes);
            }
        }
        $nodes = $this->wrapRunProperty($run->isSmallCaps, RunProperty::SmallCaps, null, $nodes);
        $nodes = $this->wrapRunProperty($run->isAllCaps, RunProperty::AllCaps, null, $nodes);
        $nodes = $this->wrapRunProperty($run->isStrikethrough, RunProperty::Strikethrough, 's', $nodes);
        $nodes = $this->wrapRunProperty($run->isUnderline, RunProperty::Underline, null, $nodes);
        if ($run->verticalAlignment === VerticalAlignment::Subscript) {
            $nodes = self::wrap('sub', $nodes);
        }
        if ($run->verticalAlignment === VerticalAlignment::Superscript) {
            $nodes = self::wrap('sup', $nodes);
        }
        $nodes = $this->wrapRunProperty($run->isItalic, RunProperty::Italic, 'em', $nodes);
        $nodes = $this->wrapRunProperty($run->isBold, RunProperty::Bold, 'strong', $nodes);

        // The character-style mapping wraps everything else.
        $mapping = $this->styleMap->findForRun($run);
        if ($mapping !== null) {
            return $mapping->to->applyTo($nodes);
        }

        if ($run->styleId !== null) {
            $messages[] = Message::warning(
                "Unrecognised run style: '{$run->styleName}' (Style ID: {$run->styleId})",
            );
        }

        return $nodes;
    }

    /**
     * @param  list<HtmlNode>  $nodes
     * @return list<HtmlNode>
     */
    private function wrapRunProperty(bool $apply, RunProperty $property, ?string $defaultTag, array $nodes): array
    {
        if (! $apply) {
            return $nodes;
        }

        $mapping = $this->styleMap->findForRunProperty($property);
        if ($mapping !== null) {
            return $mapping->to->applyTo($nodes);
        }

        return $defaultTag === null ? $nodes : self::wrap($defaultTag, $nodes);
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
