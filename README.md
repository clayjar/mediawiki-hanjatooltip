# HanjaTooltip

MediaWiki extension that wraps Han ideographs on parsed pages and shows a dictionary entry when a reader clicks or taps a character.

The lookup reads the `hanja` table in an external MySQL database. The page module calls `api.php?action=hanja&char=韓`.

## Install

Clone the extension into `extensions/HanjaTooltip`. The directory name has to be `HanjaTooltip`, because ResourceLoader publishes files under that path.

```bash
git clone https://github.com/clayjar/mediawiki-hanjatooltip.git extensions/HanjaTooltip
```

Run that from the MediaWiki root. To update an existing checkout, run `git pull` inside `extensions/HanjaTooltip`.

Add this to `LocalSettings.php`. The database user used here should have `SELECT` on `hanja` only.

```php
wfLoadExtension( 'HanjaTooltip' );
$wgHanjaTooltipDBserver = '127.0.0.1';
$wgHanjaTooltipDBport = 3306;
$wgHanjaTooltipDBname = 'hanja';
$wgHanjaTooltipDBuser = 'hanja_webuser';
$wgHanjaTooltipDBpassword = '…';
```

Tagged HTML is stored in the parser cache. After enabling the extension, purge that cache so existing pages pick up the spans:

```bash
php maintenance/run.php purgeParserCache --age 0
```

`MediaWiki:Common.js` and `MediaWiki:Common.css` are not part of this extension.

## Load the dictionary

**Skip this section if the `hanja` table is already loaded.** The lookup only reads that table. Running the loader again truncates it and inserts the dictionary from scratch.

The dictionary file is the hanja list by Choe Hwanjin (2005, 2006), published in [libhangul](https://github.com/libhangul/libhangul) as [`data/hanja/hanja.txt`](https://github.com/libhangul/libhangul/blob/main/data/hanja/hanja.txt). Its header states the copyright and license. This repository does not include that file.

Save it as `data/hanja.txt` inside the extension directory. The database must already exist. The account that loads it needs `CREATE`, `INSERT`, and `TRUNCATE` on that database. That can be a different account from the `SELECT`-only user configured above.

Set the connection in the environment, or in a `.env` file in the extension root. `.env` fills only variables that are not already set.

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hanja
DB_USERNAME=
DB_PASSWORD=
```

```bash
php maintenance/loadHanja.php data/hanja.txt
```

Each data line is `entry:hanja:definition`. Empty lines and comments are skipped. Only a hanja field of one character is stored. An empty definition falls back to the Korean `entry`. Another reading of the same character is appended with ` / `, in file order.

The script creates `hanja` when it is missing:

| Column | Type | Notes |
| --- | --- | --- |
| `hanja` | `CHAR(1)` | Primary key, `utf8mb4_bin` |
| `definition` | `TEXT` | Reading and gloss, with extra readings joined by ` / ` |

A later run truncates `hanja` and inserts the dictionary again. The current libhangul list yields 27,552 rows. The script prints how many rows it inserted.

## Behavior

`ParserAfterTidy` wraps each Unicode Han ideograph (`\p{Han}`) in `<span class="hanja-word" data-char="韓">`. Hangul, kana, Latin, digits, and punctuation stay plain text. Text inside links, `script`, `style`, and `textarea` is left unchanged.

A mouse click or finger tap on a span requests that one character. Hover and keyboard focus do not. A second click or tap on the same character, Escape, or a click outside the popup closes it.

A hit looks like:

```json
{
  "hanja": {
    "char": "韓",
    "definition": "…",
    "found": true
  }
}
```

A missing character returns `found: false` and an empty `definition`.
