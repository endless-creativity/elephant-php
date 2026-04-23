<?php

declare(strict_types=1);

// Ported from mammoth.js: lib/index.js + lib/docx/office-xml-reader.js

namespace EndlessCreativity\ElephantPhp;

use EndlessCreativity\ElephantPhp\Document\DocumentConverter;
use EndlessCreativity\ElephantPhp\Reader\BodyReader;
use EndlessCreativity\ElephantPhp\Reader\DocumentXmlReader;
use EndlessCreativity\ElephantPhp\Reader\Relationships;
use EndlessCreativity\ElephantPhp\Reader\RelationshipsReader;
use EndlessCreativity\ElephantPhp\Reader\Styles;
use EndlessCreativity\ElephantPhp\Reader\StylesReader;
use EndlessCreativity\ElephantPhp\Reader\Xml\XmlReader;
use EndlessCreativity\ElephantPhp\Reader\ZipFile;

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

    /**
     * @return Result<string>
     */
    public function convertToHtml(string $path): Result
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

        $documentXml = self::stripUtf8Bom($zip->read('word/document.xml'));
        $documentElement = XmlReader::readString($documentXml, self::OFFICE_XML_NAMESPACE_MAP);

        $documentResult = (new DocumentXmlReader(
            new BodyReader(styles: $styles, relationships: $relationships),
        ))->convertXmlToDocument($documentElement);

        $htmlResult = (new DocumentConverter())->convertToHtml($documentResult->value);

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
