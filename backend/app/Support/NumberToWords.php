<?php

namespace App\Support;

class NumberToWords
{
    private const ONES = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen',
    ];

    private const TENS = [
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety',
    ];

    private const SCALES = ['', 'thousand', 'million', 'billion', 'trillion', 'quadrillion'];

    /**
     * Spell a whole amount in English, e.g. 2425000 → "two million four hundred
     * twenty-five thousand". Used for the "amount in words" line on documents;
     * the caller appends the currency word (e.g. "dong").
     */
    public static function en(float|int|string|null $number): string
    {
        $number = (int) round((float) $number);
        if ($number === 0) {
            return 'zero';
        }

        $negative = $number < 0;
        $number = abs($number);

        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }
            $words = self::threeDigits($groups[$i]);
            $scale = self::SCALES[$i] ?? '';
            $parts[] = $scale !== '' ? $words.' '.$scale : $words;
        }

        $result = implode(' ', $parts);

        return $negative ? 'negative '.$result : $result;
    }

    private const ONES_VI = [
        'không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín',
    ];

    private const SCALES_VI = ['', 'nghìn', 'triệu'];

    /**
     * Spell a whole amount in Vietnamese, e.g. 2425000 → "hai triệu bốn trăm hai
     * mươi lăm nghìn". Mirrors ::en(); the caller appends the currency word
     * (e.g. "đồng"). Reads middle groups formally ("không trăm", "lẻ").
     */
    public static function vi(float|int|string|null $number): string
    {
        $number = (int) round((float) $number);
        if ($number === 0) {
            return 'không';
        }

        $negative = $number < 0;
        $number = abs($number);

        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $highest = count($groups) - 1;
        $parts = [];
        for ($i = $highest; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }
            $words = self::threeDigitsVi($groups[$i], $i === $highest);
            $scale = self::scaleVi($i);
            $parts[] = $scale !== '' ? $words.' '.$scale : $words;
        }

        $result = trim(implode(' ', $parts));

        return $negative ? 'âm '.$result : $result;
    }

    private static function scaleVi(int $groupIndex): string
    {
        $base = self::SCALES_VI[$groupIndex % 3];
        $billions = intdiv($groupIndex, 3);
        $ty = trim(str_repeat(' tỷ', $billions));

        return trim($base.' '.$ty);
    }

    private static function threeDigitsVi(int $n, bool $isLeading): string
    {
        $hundreds = intdiv($n, 100);
        $tens = intdiv($n % 100, 10);
        $ones = $n % 10;

        $out = [];
        if ($hundreds > 0) {
            $out[] = self::ONES_VI[$hundreds].' trăm';
        } elseif (! $isLeading) {
            $out[] = 'không trăm';
        }

        if ($tens === 0) {
            if ($ones > 0) {
                if ($hundreds > 0 || ! $isLeading) {
                    $out[] = 'lẻ';
                }
                $out[] = self::ONES_VI[$ones];
            }
        } elseif ($tens === 1) {
            $out[] = 'mười';
            if ($ones > 0) {
                $out[] = $ones === 5 ? 'lăm' : self::ONES_VI[$ones];
            }
        } else {
            $out[] = self::ONES_VI[$tens].' mươi';
            if ($ones > 0) {
                $out[] = match ($ones) {
                    1 => 'mốt',
                    5 => 'lăm',
                    default => self::ONES_VI[$ones],
                };
            }
        }

        return implode(' ', $out);
    }

    private static function threeDigits(int $n): string
    {
        $out = [];

        $hundreds = intdiv($n, 100);
        if ($hundreds > 0) {
            $out[] = self::ONES[$hundreds].' hundred';
        }

        $rest = $n % 100;
        if ($rest > 0) {
            if ($rest < 20) {
                $out[] = self::ONES[$rest];
            } else {
                $tens = self::TENS[intdiv($rest, 10)];
                $ones = $rest % 10;
                $out[] = $ones > 0 ? $tens.'-'.self::ONES[$ones] : $tens;
            }
        }

        return implode(' ', $out);
    }
}
