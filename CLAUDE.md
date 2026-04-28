# elephant-php

Port PHP di mammoth.js per convertire .docx in HTML semantico e Markdown.

## Audience: libreria pubblica server-side
Questo repository è una libreria pubblica pubblicata su Packagist e usata
in contesti server-side internazionali. Tutto ciò che committiamo finisce
in mano a sviluppatori esterni di lingua qualunque, e una volta taggato
finisce su Packagist senza possibilità di "ripensamenti puliti". Quindi:

- **Tutto ciò che è committato deve essere in inglese**: codice, commenti,
  commit message, CHANGELOG, ROADMAP, README, CONTRIBUTING, tag message,
  PR descriptions. L'italiano resta esclusivamente nella conversazione
  qui in chat. Quando aggiungo o modifico un file, prima di committare
  controllo che non ci siano residui di italiano.
- **Niente informazioni di gestione interna nei file pubblici**:
  - frasi tipo "in our deployments", "in this session", "we just shipped",
    "our team uses..." appartengono a doc interne, non a una libreria
    pubblica;
  - snapshot numerici che invecchiano subito ("66 commits", "400 tests
    passing") in file versionati creano debito di manutenzione e non
    aggiungono valore al consumatore esterno;
  - riferimenti a workflow interni, persone, decisioni di processo non
    vanno in CHANGELOG / ROADMAP / README;
  - i messaggi di commit possono essere più discorsivi, ma anche lì
    evitiamo "I", "we just", "this session" -- meglio descrivere la
    modifica in terza persona impersonale.
- **Best practice di librerie pubbliche** che applichiamo by default:
  - SemVer rigoroso, anche pre-1.0 il bump minor segnala feature, patch
    segnala fix, e qualsiasi cambio di output o di firma pubblica viene
    annunciato nel CHANGELOG;
  - CHANGELOG in formato Keep a Changelog, una entry per release con
    sezioni Added / Changed / Fixed / Removed / Deprecated / Security;
  - sicurezza-by-default: input attaccabile (XML, ZIP, URL, path) viene
    trattato come ostile e documentato esplicitamente nelle release notes;
  - attribution chiara per il codice portato (header `// Ported from
    mammoth.js: lib/path/to/file.js`) e licenza visibile nel README;
  - API pubblica documentata via README + esempi eseguibili; namespace
    interni mantenuti `internal` con commento.

Quando proponi modifiche ai file pubblici, segnala subito se rilevi
violazioni di queste regole anche nel contenuto preesistente, e
proponi la pulizia.

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

## Flusso di release
Ordine obbligatorio quando si pubblica una nuova versione:
1. Aggiornare `CHANGELOG.md` (sezione nuova `## [x.y.z] — YYYY-MM-DD`)
2. Commit del changelog
3. `git tag -a vX.Y.Z -m "..."`
4. `git push origin main && git push origin vX.Y.Z`

Mai taggare prima di aver scritto l'entry: il tag finisce subito su Packagist
e spostarlo dopo è scomodo.
