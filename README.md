# elephant-php

> An elephant never forgets your document structure — convert `.docx` to
> semantic HTML and Markdown in pure PHP. Inspired by
> [mammoth.js](https://github.com/mwilliamson/mammoth.js).

> **Attribution.** This library is an independent PHP port inspired by
> mammoth.js by Michael Williamson. It adopts mammoth's core philosophy
> (extract semantics, not formatting) and style-mapping DSL. It is not
> affiliated with or endorsed by the mammoth.js project.

## Status

Early development. Not yet usable. Tracking the porting in
[`CLAUDE.md`](CLAUDE.md).

## Installation

```bash
composer require endless-creativity/elephant-php
```

Requires PHP 8.2+ with the standard `dom`, `libxml`, `mbstring`,
`xmlreader`, and `zip` extensions. No external binaries (no LibreOffice,
no Pandoc, no shell-out).

## Usage

```php
use EndlessCreativity\ElephantPhp\Converter;

$result = (new Converter())->convertToHtml('/path/to/file.docx');

echo $result->value();           // semantic HTML
foreach ($result->messages() as $message) {
    fwrite(STDERR, $message . PHP_EOL);
}
```

## Development

```bash
composer install
composer test     # Pest
composer stan     # PHPStan level 8
composer format   # Laravel Pint
```

## License

[BSD-2-Clause](LICENSE). Copyright © 2026 Endless Creativity (PHP port)
and © 2013 Michael Williamson (mammoth.js, from which this work derives
its algorithmic structure and test fixtures).
