# Roadmap & Known Gaps

Snapshot at HEAD: **35 commit, 297 test verdi, PHPStan livello 8 clean, Pint pulito.**
Per documenti reali (paragrafi, headings, run inline, hyperlink, liste annidate, tabelle con colspan/rowspan, immagini embedded e linkate, footnote/endnote, comments, simboli speciali, complex field hyperlink) la libreria è **funzionalmente equivalente a mammoth.js**. Quello che segue è il delta residuo, ordinato per dominio.

## Reader: feature mammoth-supportate ancora mancanti

| Mancante | Note |
|---|---|
| **Checkbox `w:sdt[wordml:checkbox]`** | passthrough esiste (commit 16) ma non promuove a un `Checkbox` Document node. Manca il modello e il dispatch della form-field detection. |
| **`FORMCHECKBOX` in complex field** | il dispatcher di `w:fldChar` (commit 31) gestisce solo HYPERLINK. Le caselle di controllo basate su field-code restano non interpretate. |
| **OMML / equazioni** | mammoth skippa (warning), noi anche — parità. |
| **SmartArt / drawing canvas / VML shapes** | mammoth skippa, noi anche — parità. |
| **Track changes (`w:ins`, `w:moveTo`, `w:moveFrom`, `w:cellIns/Del/Merge`)** | mammoth ha gestione parziale (deleted paragraph contents si concatenano col successivo); noi droppiamo silenziosamente. |
| **`numStyleLink` / `findLevelByParagraphStyleId`** in numbering | numbering ereditato via paragraph style. Raro; mammoth lo gestisce, noi no. |

## DSL style-map: forme mancanti

| Forma | Stato |
|---|---|
| `p:unordered-list(N)` / `p:ordered-list(N)` | non implementato. La nostra rendering liste è imperativa (commit 10/21), non passa per DSL. Funzionalmente equivalente ma non offre l'override via mapping. |
| `:separator('text')` su path | non implementato. mammoth lo usa per inserire separatori tra elementi mergiati dal Simplifier. |
| Escape sequences `\n \r \t` nelle stringhe | non implementato. Noi parsiamo le stringhe `'…'` letterali. |
| Errori parser come `Result<warning>` | mammoth ritorna `Result(null, [warning(...)])`, noi tiriamo `InvalidArgumentException`. Più rigido, meno tollerante. |

## API pubblica mammoth non ancora esposta

- `transformDocument(callable)` — callback per trasformare il modello prima della conversione HTML.
- `transforms` module — utility per walk/replace nel tree (`transforms.elementsOfType`, ecc.).
- `underline` module — custom underline mappers.
- Opzione `idPrefix` per namespaceare gli `id` HTML di bookmark, footnote-ref, comment-ref. Utile quando si embedda l'output in una pagina con altri id.
- Opzione `prettyPrint` per HTML indentato.
- Opzione `ignoreEmptyParagraphs` (noi sempre `true`; mammoth permette `false` per preservare paragrafi vuoti tramite `forceWrite`).
- CLI `--style-map FILE` per caricare regole da file di testo esterno.

## Decisioni di design intenzionali (NON da "fixare")

- **`w:basedOn` su style** non viene risolto. Mammoth non lo risolve; per il principio "fai come mammoth" lo skippiamo. Vedi commento in `src/Reader/StylesReader.php`.
- **`Break` rinominato in `BreakElement`** perché `break` è reserved word in PHP. Stessa logica per `MatcherKind::BreakKind`.
- **Lists rendering imperativo** (non DSL-driven). Decisione presa per portare la feature prima del parser DSL; replicare l'approccio mammoth (style-map matchers `unordered-list(N)`) richiederebbe un grosso refactor del flow del converter senza reali vantaggi visibili.
- **Markdown writer**: port nativo di mammoth (commit 14), niente wrapper su `league/html-to-markdown`. Include l'escape del `.` come mammoth (può sorprendere chi guarda l'output).
- **`.docx` writer**: assente. Andata one-way, come mammoth. Per generare `.docx` da PHP esistono librerie dedicate (PhpWord, ecc.).

## Test coverage

297 test contro i ~600+ di mammoth. Aree dove serve più coverage di edge case:
- body-reader properties (font, fontSize, highlight con valori stranieri/malformati, sym con prefissi diversi da F0).
- styles-reader: `basedOn` (anche se non risolto, il parser non deve crashare), styleId mancanti, name attributi vuoti.
- numbering: `numStyleLink`, malformed `w:lvl` senza `w:ilvl`.
- DSL parser: errori posizionali precisi, commenti / spazi insoliti, attributi multipli sullo stesso path element.
- Tables: vMerge che attraversa righe senza cella corrispondente nella riga sopra (malformed).
- Integration test per ogni fixture mammoth/test/test-data che non sia ancora coperta (`comments.docx`, `endnotes.docx`, `external-picture.docx`, `text-box.docx`, `embedded-style-map.docx`, `strict-format.docx`, ecc.).

## Warning attesi su documenti reali

I seguenti warning sono **normali** (anche mammoth li produce), non indicano malfunzionamenti:
- `Unrecognised paragraph style: 'List Paragraph' (Style ID: ListParagraph)` o equivalenti localizzati (`Paragrafoelenco`, ecc.). Word applica `ListParagraph` ai list item; lo style map di default copre solo Heading 1..6. Per silenziare: aggiungere `p[style-name='List Paragraph'] =>` al proprio style map.
- `Unrecognised run style: 'FootnoteReference'` su footnote: stesso meccanismo, occorrenza interna a `footnotes.xml`.
- `Image of type image/X is unlikely to display in web browsers` per immagini in formati legacy (BMP, TIFF). Avviso informativo, l'`<img>` viene comunque emesso.

## Versioning

Pre-1.0. Nessun BC stability promise. Una volta rilasciato 1.0:
- L'API pubblica (`Converter`, `Result`, `Message`, `MessageType`, `StyleMap`, `ImageHandler`) sarà stabile semver.
- I namespace interni (`Reader\*`, `Document\*`, `Html\*`, `Style\*` non-API) restano implementation detail e possono cambiare.
