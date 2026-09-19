<?php

namespace App\Support;

/**
 * Repairs text that was UTF-8 bytes decoded as Windows console code page 437 and then stored as UTF-8
 * ("Công ty Cổ phần" -> "C├┤ng ty Cß╗ò phß║ºn"). 182 stock_symbols.name rows were stored like that by an
 * old sync run on a Windows console; the fix is the exact inverse: re-encode as CP437, read the bytes as UTF-8.
 */
class Mojibake
{
    /** Box-drawing / block characters that CP437 uses for the UTF-8 lead & continuation bytes 0xC3-0xE1. */
    private const TELLTALE = '/[├┤┬┴┼─│┐└┘┌╗╝╚╔╠╣╦╩╬║═╪╫╧╨╤╥╙╘╒╓▄▀█░▒▓ßÇ╗╜╛╞╟]/u';

    public static function looksBroken(?string $text): bool
    {
        return $text !== null && $text !== '' && preg_match(self::TELLTALE, $text) === 1;
    }

    /**
     * @return string|null the repaired text, or null when the input does not look broken or cannot be
     *                     round-tripped to valid UTF-8 (so callers never replace good text with worse text)
     */
    public static function repairCp437(?string $text): ?string
    {
        if (! self::looksBroken($text)) {
            return null;
        }

        $bytes = @iconv('UTF-8', 'CP437//IGNORE', $text);
        if ($bytes === false || $bytes === '' || ! mb_check_encoding($bytes, 'UTF-8') || $bytes === $text) {
            return null;
        }

        return $bytes;
    }
}
