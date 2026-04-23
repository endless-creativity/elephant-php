<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/index.js + lib/docx/office-xml-reader.js

namespace EndlessCreativity\ElephantPhp;

use EndlessCreativity\ElephantPhp\Document\DocumentConverter;
use EndlessCreativity\ElephantPhp\Image\DataUriImageHandler;
use EndlessCreativity\ElephantPhp\Image\ImageHandler;
use EndlessCreativity\ElephantPhp\Reader\BodyReader;
use EndlessCreativity\ElephantPhp\Reader\ContentTypes;
use EndlessCreativity\ElephantPhp\Reader\ContentTypesReader;
use EndlessCreativity\ElephantPhp\Reader\DocumentXmlReader;
use EndlessCreativity\ElephantPhp\Reader\Numbering;
use EndlessCreativity\ElephantPhp\Reader\NumberingReader;
use EndlessCreativity\ElephantPhp\Reader\Relationships;
use EndlessCreativity\ElephantPhp\Reader\RelationshipsReader;
use EndlessCreativity\ElephantPhp\Reader\Styles;
use EndlessCreativity\ElephantPhp\Reader\StylesReader;
use EndlessCreativity\ElephantPhp\Reader\Xml\XmlReader;
use EndlessCreativity\ElephantPhp\Reader\ZipFile;
use EndlessCreativity\ElephantPhp\Style\StyleMap;
use EndlessCreativity\ElephantPhp\Style\StyleMapParser;

final class Converter
{
    /** @var array<string, string> */
    private const OFFICE_XML_NAMESPACE_MAP = [
        // Transitional format
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main' => 'w',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships' => 'r',
        'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing' => 'wp',
        'http://schemas.openxmlformats.org/drawingml/2006/main' => 'a',
        'http://schemas.openxmlformats.org/drawingml/2006/picture' => 'pic',

        // Strict format
        'http://purl.oclc.org/ooxml/wordprocessingml/main' => 'w',
        'http://purl.oclc.org/ooxml/officeDocument/relationships' => 'r',
        'http://purl.oclc.org/ooxml/drawingml/wordprocessingDrawing' => 'wp',
        'http://purl.oclc.org/ooxml/drawingml/main' => 'a',
        'http://purl.oclc.org/ooxml/drawingml/picture' => 'pic',

        // Common
        'http://schemas.openxmlformats.org/package/2006/content-types' => 'content-types',
        'http://schemas.openxmlformats.org/package/2006/relationships' => 'relationships',
        'http://schemas.openxmlformats.org/markup-compatibility/2006' => 'mc',
        'urn:schemas-microsoft-com:vml' => 'v',
        'urn:schemas-microsoft-com:office:word' => 'office-word',

        'http://schemas.microsoft.com/office/word/2010/wordml' => 'wordml',
    ];

    private readonly StyleMap $styleMap;

    /**
     * @param  list<string>|null  $styleMap  Optional list of mapping rules in
     *                                       mammoth's DSL (e.g. "p[style-name=
     *                                       'Aside'] => p.aside"). Rules are
     *                                       prepended to the default heading
     *                                       map and tried first.
     */
    public function __construct(
        ?array $styleMap = null,
        private readonly ImageHandler $imageHandler = new DataUriImageHandler(),
    ) {
        $base = StyleMap::default();
        $this->styleMap = $styleMap === null
            ? $base
            : $base->prepend(StyleMapParser::parseAll($styleMap)->mappings);
    }

    /**
     * @return Result<string>
     */
    public function convertToHtml(string $path): Result
    {
        return $this->convert(
            $path,
            static fn (DocumentConverter $converter, $document): Result => $converter->convertToHtml($document),
        );
    }

    /**
     * @return Result<string>
     */
    public function convertToMarkdown(string $path): Result
    {
        return $this->convert(
            $path,
            static fn (DocumentConverter $converter, $document): Result => $converter->convertToMarkdown($document),
        );
    }

    /**
     * @param  callable(DocumentConverter, \EndlessCreativity\ElephantPhp\Document\Document): Result<string>  $write
     * @return Result<string>
     */
    private function convert(string $path, callable $write): Result
    {
        $zip = ZipFile::openPath($path);

        $styles = $zip->exists('word/styles.xml')
            ? StylesReader::readFromXml(XmlReader::readString(
                self::stripUtf8Bom($zip->read('word/styles.xml')),
                self::OFFICE_XML_NAMESPACE_MAP,
            ))
            : Styles::default();

        $relationships = $zip->exists('word/_rels/document.xml.rels')
            ? RelationshipsReader::readFromXml(XmlReader::readString(
                self::stripUtf8Bom($zip->read('word/_rels/document.xml.rels')),
                self::OFFICE_XML_NAMESPACE_MAP,
            ))
            : Relationships::default();

        $numbering = $zip->exists('word/numbering.xml')
            ? NumberingReader::readFromXml(XmlReader::readString(
                self::stripUtf8Bom($zip->read('word/numbering.xml')),
                self::OFFICE_XML_NAMESPACE_MAP,
            ))
            : Numbering::default();

        $contentTypes = $zip->exists('[Content_Types].xml')
            ? ContentTypesReader::readFromXml(XmlReader::readString(
                self::stripUtf8Bom($zip->read('[Content_Types].xml')),
                self::OFFICE_XML_NAMESPACE_MAP,
            ))
            : ContentTypes::default();

        $documentXml = self::stripUtf8Bom($zip->read('word/document.xml'));
        $documentElement = XmlReader::readString($documentXml, self::OFFICE_XML_NAMESPACE_MAP);

        $documentResult = (new DocumentXmlReader(
            new BodyReader(
                styles: $styles,
                relationships: $relationships,
                numbering: $numbering,
                contentTypes: $contentTypes,
                imageReader: static fn (string $path): string => $zip->read($path),
            ),
        ))->convertXmlToDocument($documentElement);

        $converter = new DocumentConverter(
            styleMap: $this->styleMap,
            imageHandler: $this->imageHandler,
        );
        $htmlResult = $write($converter, $documentResult->value);

        return new Result(
            value: $htmlResult->value,
            messages: array_merge($documentResult->messages, $htmlResult->messages),
        );
    }

    private static function stripUtf8Bom(string $value): string
    {
        return str_starts_with($value, "\u{FEFF}") ? mb_substr($value, 1) : $value;
    }
}
