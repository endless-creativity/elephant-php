# Changelog

All notable changes to this project will be documented in this file. The
format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.

## [Unreleased]

Tracked in `ROADMAP.md`. Highlights still pending: checkbox form fields,
DSL list matchers (`p:unordered-list(N)`), DSL `:separator(...)` and
escape sequences, `transformDocument` callback, `idPrefix` /
`prettyPrint` / `ignoreEmptyParagraphs` options.

## [0.1.0] — initial release

First public release. Functionally equivalent to mammoth.js for typical
real-world documents (paragraphs, headings, runs, hyperlinks, lists,
tables, images, footnotes, comments, symbols, complex-field hyperlinks).

### Added

- Public API: `Converter::convertToHtml`, `convertToMarkdown`,
  `extractRawText`, `embedStyleMap`, `readEmbeddedStyleMap`.
- HTML and Markdown output from a shared intermediate document model.
- Style-mapping DSL (mammoth-compatible subset): paragraph, run, run
  property (`b/i/u/strike/all-caps/small-caps`), `comment-reference`,
  `highlight[color='X']`, `br[type='X']`, `table` matchers; element
  paths with `.class` and `[attr='val']` modifiers and the `:fresh`
  flag; `!` for ignore.
- Default style map that mirrors mammoth's heading rules
  (`Heading 1..6` → `h1..h6`).
- Reader features: paragraphs, runs (bold, italic, underline,
  strikethrough, all-caps, small-caps, sub/sup, highlight, font,
  fontSize), hyperlinks (regular and complex-field `HYPERLINK`), nested
  lists (mixed ordered/unordered), tables (`gridSpan` → colspan,
  `vMerge` → rowspan, `tblHeader` → `<thead>`), images (embedded via
  `r:embed` and linked via `r:link`, optional wrap in `<a>` from
  `wp:docPr/a:hlinkClick`), footnotes and endnotes with backlinks,
  comments (opt-in render via `comment-reference =>` DSL), content
  controls (`w:sdt`), `w:tab`, line/page/column breaks, bookmarks
  (`w:bookmarkStart`, except the synthetic `_GoBack`), no-break and
  soft hyphens, `w:sym` symbol fonts (Wingdings / Webdings / Symbol
  via the dingbat-to-unicode table), `mc:AlternateContent` collapsing.
- Native Markdown writer (port of mammoth's
  `lib/writers/markdown-writer.js`); CommonMark-aware whitespace
  cleanup so `__   bold   __` and tab-indented paragraphs render
  correctly instead of as code blocks.
- CLI `bin/elephant-php` (HTML / Markdown to stdout or file).
- CI on PHP 8.2 / 8.3 / 8.4 / 8.5 (Pint, PHPStan level 8, Pest).

### Documented limitations

- DSL list matchers (`p:unordered-list(N)`): list rendering is
  imperative, not DSL-driven. Functionally equivalent.
- `w:basedOn` style chains: not resolved (mammoth doesn't either).
- OMML equations, SmartArt, track changes, checkbox form fields:
  dropped or warned, matching mammoth's behaviour.

[Unreleased]: https://github.com/endless-creativity/elephant-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/endless-creativity/elephant-php/releases/tag/v0.1.0
