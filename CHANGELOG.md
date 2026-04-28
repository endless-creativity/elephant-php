# Changelog

All notable changes to this project will be documented in this file. The
format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.

## [Unreleased]

### Added

- **`Converter` options:** `idPrefix`, `ignoreEmptyParagraphs`,
  `prettyPrint`, `transformDocument` — all mirroring mammoth's
  same-named options. Defaults preserve existing behaviour.
- **Checkbox form fields:** `<w:sdt><wordml:checkbox>` and
  `FORMCHECKBOX` complex fields now produce `Checkbox` document
  nodes that render as `<input type="checkbox">` (HTML) or
  `[x]` / `[ ]` (Markdown).
- **`numStyleLink` chasing in numbering:** an `<w:abstractNum>`
  pointing at a numbering-type `<w:style>` resolves transparently
  to that style's `<w:numId>`. Requires `Styles` to be passed to
  `NumberingReader`, which `Converter` now does automatically.
- **`findLevelByParagraphStyleId` fallback** in body reader: when a
  paragraph has only a styleId and no `<w:numPr>`, but the
  numbering definition tied a level to that style via
  `<w:lvl><w:pStyle>`, the paragraph picks up the level.
- **DSL style-map:** backslash escape sequences (`\n`, `\r`, `\t`,
  `\\`, `\'`) inside `'...'` strings; `:separator('text')` modifier
  on path elements.

### Tracked in `ROADMAP.md` for later

DSL list matchers (`p:unordered-list(N)`); `transforms.*` helpers;
custom `underline` mappers; CLI `--style-map FILE`.

## [0.2.0] — 2026-04-27

Cleaner Markdown output and faithful Word numbering. The public API is
unchanged; output format has shifted in ways that may affect downstream
consumers diffing rendered Markdown.

### Changed

- **Bold marker switched from `__` to `**`.** The double-underscore
  wrapper clashed with literal underscore runs (Word fill-in fields
  like `_______`), breaking surrounding emphasis. Asterisks have no
  intraword conflict in docx-derived content.
- **Replaced mammoth's blanket text escape with a context-aware one.**
  `*`, `_`, `(`, `)`, `{`, `}` are never escaped; `#`, `+`, `-`, `.`
  only when at line start in a position that would parse as syntax;
  `[` and `]` only when forming the inline-link pattern `[text](url)`
  (citation-style brackets like `[1]`, `[Nota]`, `[sic]` stay
  literal); `!` only before such a link pattern. `\` and backtick
  remain always escaped.

### Added

- **Honour `<w:start>` from numbering definitions.** Word's exporter
  often splits a visually continuous "1., 2., 3." sequence into
  separate single-item ordered lists, each with its own `<w:start>`
  in the abstract numbering. Previously every chunk restarted at 1;
  the converter now forwards `start` on `<ol>` and the Markdown
  writer renders the first item from that number. `NumberingLevel`
  gained an optional `start: ?int` field.

### Fixed

- **Drop HTML `<a id="...">` anchors from Markdown output.** Bookmarks
  (Word's `_Toc...`, `_Hlk...`, `_Ref...`) used to leak into the
  Markdown as raw HTML; they are now silently elided. Real links
  (`<a href>`) are unaffected. HTML output keeps the anchors as
  before.

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
