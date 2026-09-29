# Changelog

All notable changes to `vibefilter/filament` will be documented in this file.

## Unreleased

- Requires PHP 8.2+, Laravel 12.36+ or 13, and Filament 5.1+.
- Natural-language table filter for Filament 5: keeps the rows a plain-English statement is true for.
- Decisions from TypeSafe Jev, sent in parallel batches with retries.
- Content-based decision cache: equal texts are scored once, edited rows get a new score.
- Filters only the rows left by the other filters and the search.
- Asks before scoring more unscored rows than the limit, with a "Run anyway" button.
- Live progress bar in the table while rows are scored.
