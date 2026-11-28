# Changelog

## [1.1.1] - 2026-11-27

### Fixed

- The tooltip posts the dictionary lookup. A GET left `action=hanja` in the query string, and the edge challenge answered that URL with a block page for a mobile user agent, which the tooltip showed as "Not found".
- The tooltip uses `width: max-content`, so a short entry beside the right edge no longer collapses to a few characters.

## [1.1.0] - 2026-11-13

### Changed

- `action=hanja` accepts a request only when it comes from the wiki that installed the extension. A cross-site call is rejected.
- Lookup responses are cached privately, so a shared cache cannot skip that check.

## [1.0.0] - 2026-11-07

### Added

- `action=hanja` looks up one character in the external `hanja` table with a prepared statement. Database credentials come from LocalSettings.
- Parsed pages wrap each Han ideograph in `span.hanja-word`, leaving link labels and script, style, and textarea blocks unchanged.
- A click or finger tap opens a tooltip with that character's definition.
- `maintenance/loadHanja.php` loads the libhangul hanja list into MySQL.
