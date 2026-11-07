# Changelog

## [1.0.0] - 2026-11-07

### Added

- `action=hanja` looks up one character in the external `hanja` table with a prepared statement. Database credentials come from LocalSettings.
- Parsed pages wrap each Han ideograph in `span.hanja-word`, leaving link labels and script, style, and textarea blocks unchanged.
- A click or finger tap opens a tooltip with that character's definition.
- `maintenance/loadHanja.php` loads the libhangul hanja list into MySQL.
