# elephant-php

Port PHP di mammoth.js per convertire .docx in HTML semantico e Markdown.

## Stack
- PHP 8.2+
- Pest per i test
- PHPStan livello 8
- Laravel Pint per lo stile (PSR-12)

## Regole sempre valide
- `declare(strict_types=1);` in ogni file PHP
- Named arguments per chiamate con più di 2 parametri
- Readonly classes e properties dove possibile
- Enum PHP 8.1+ invece di costanti dove appropriato
- Mai scrivere codice senza un test che lo motivi prima
- Ogni file PHP portato da mammoth deve avere in cima:
  `// Ported from mammoth.js: lib/path/to/file.js`

## Comandi utili
- Test: `./vendor/bin/pest`
- Static analysis: `./vendor/bin/phpstan analyse`
- Format: `./vendor/bin/pint`

## Reference
Il codice originale di mammoth.js è in `./reference/mammoth.js/`.
Consultalo per ogni decisione di edge case, non reinventare.
