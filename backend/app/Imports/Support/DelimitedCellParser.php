<?php

namespace App\Imports\Support;

/**
 * Splits a spreadsheet cell into comma-separated entries while honouring
 * double-quote wrapping, shared by importers whose cells pack several values
 * into one column (e.g. a tag's values, or a product's tags).
 *
 * An entry is "quoted" only when its first non-space character is a quote; then
 * commas and colons inside it are literal and a doubled "" is one quote. Outside
 * a quoted entry a bare quote (e.g. 6" pipe) is just a character.
 *
 * Anything after a quoted entry's closing quote (before the next comma) is
 * captured as `trailing` rather than mixed into the value, so callers can drop
 * it with a warning — a word after "" with no comma is denied.
 */
class DelimitedCellParser
{
    /**
     * @return list<array{text: string, quoted: bool, trailing: string}>
     */
    public static function split(string $cell): array
    {
        $entries = [];
        $text = '';
        $trailing = '';
        $quoted = false;
        $closed = false;
        $seenNonSpace = false;
        $inQuotes = false;
        $length = strlen($cell);

        $i = 0;
        while ($i < $length) {
            $char = $cell[$i];

            if ($inQuotes) {
                if ($char === '"') {
                    if ($i + 1 < $length && $cell[$i + 1] === '"') {
                        $text .= '"';
                        $i += 2;
                        continue;
                    }
                    $inQuotes = false;
                    $closed = true;
                    $i++;
                    continue;
                }
                $text .= $char;
                $i++;
                continue;
            }

            if ($char === ',') {
                $entries[] = ['text' => $text, 'quoted' => $quoted, 'trailing' => $trailing];
                $text = '';
                $trailing = '';
                $quoted = false;
                $closed = false;
                $seenNonSpace = false;
                $i++;
                continue;
            }

            if ($char === '"' && !$seenNonSpace) {
                $inQuotes = true;
                $quoted = true;
                $seenNonSpace = true;
                $i++;
                continue;
            }

            // Past a quoted entry's closing quote: hold the rest aside as trailing.
            if ($closed) {
                $trailing .= $char;
                $i++;
                continue;
            }

            if ($char !== ' ' && $char !== "\t") {
                $seenNonSpace = true;
            }
            $text .= $char;
            $i++;
        }

        $entries[] = ['text' => $text, 'quoted' => $quoted, 'trailing' => $trailing];

        return $entries;
    }
}
