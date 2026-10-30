<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal QR Code encoder (byte mode, error correction level L, mask 0), ported from
 * src/lib/qrCodeMatrix.ts so the Blade ticket shows the same code as the React app.
 *
 * Supports versions 1–5 only (up to 105 bytes): those use a single Reed–Solomon block, which is
 * what this encoder implements. Ticket tokens ("tok_" + 32 characters) need version 3.
 * Longer input is rejected rather than producing an unreadable code.
 * Unlike the TypeScript original, this also writes the second copy of the format information.
 */
final class QrCode
{
    /** version => [size, data codewords, ecc codewords, alignment centres] */
    private const SPECS = [
        1 => [21, 19, 7, []],
        2 => [25, 34, 10, [6, 18]],
        3 => [29, 55, 15, [6, 22]],
        4 => [33, 80, 20, [6, 26]],
        5 => [37, 108, 26, [6, 30]],
    ];

    /** Format information for level L, mask 0 (MSB first). */
    private const FORMAT_BITS = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];

    private static array $exp = [];
    private static array $log = [];

    /** @return list<list<bool>> true = dark module */
    public static function matrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $spec = null;
        foreach (self::SPECS as $candidate) {
            if ($candidate[1] >= count($bytes) + 3) {
                $spec = $candidate;
                break;
            }
        }
        if ($spec === null) {
            throw new \InvalidArgumentException('QR content too long: at most 105 bytes are supported.');
        }
        [$size, $dataBytes, $eccBytes, $alignment] = $spec;

        // 1. Bit stream: byte mode (0100), 8-bit length, data, zero-fill to a byte, then pad bytes.
        $bits = [0, 1, 0, 0];
        $count = count($bytes);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($count >> $i) & 1;
        }
        foreach ($bytes as $b) {
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($b >> $j) & 1;
            }
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        while (count($bits) < $dataBytes * 8) {
            $pad = (intdiv(count($bits), 8) % 2 === 0) ? 0xEC : 0x11;
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($pad >> $j) & 1;
            }
        }

        $data = [];
        for ($i = 0; $i < $dataBytes; $i++) {
            $value = 0;
            for ($j = 0; $j < 8; $j++) {
                $value = ($value << 1) | $bits[$i * 8 + $j];
            }
            $data[] = $value;
        }
        $codewords = array_merge($data, self::reedSolomon($data, $eccBytes));

        // 2. Function patterns.
        $m = array_fill(0, $size, array_fill(0, $size, null));
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$row, $col]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $mr = $row + $r;
                    $mc = $col + $c;
                    if ($mr < 0 || $mr >= $size || $mc < 0 || $mc >= $size) {
                        continue;
                    }
                    $m[$mr][$mc] = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                        || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                        || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $m[6][$i] ??= $i % 2 === 0;
            $m[$i][6] ??= $i % 2 === 0;
        }
        $m[$size - 8][8] = true; // dark module

        if (count($alignment) >= 2) {
            foreach ($alignment as $r) {
                foreach ($alignment as $c) {
                    if (($r < 9 && $c < 9) || ($r < 9 && $c > $size - 9) || ($r > $size - 9 && $c < 9)) {
                        continue;
                    }
                    for ($dr = -2; $dr <= 2; $dr++) {
                        for ($dc = -2; $dc <= 2; $dc++) {
                            $m[$r + $dr][$c + $dc] = abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0);
                        }
                    }
                }
            }
        }

        for ($i = 0; $i < 9; $i++) {
            $m[8][$i] ??= false;
            $m[$i][8] ??= false;
            $m[8][$size - 1 - $i] ??= false;
            $m[$size - 1 - $i][8] ??= false;
        }

        // Format information: first copy around the top-left finder...
        $first = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]];
        foreach ($first as $i => [$r, $c]) {
            $m[$r][$c] = self::FORMAT_BITS[$i] === 1;
        }
        // ...and the second copy split between the bottom-left and top-right finders.
        // FORMAT_BITS[$i] is bit (14 - i); bits 0–7 run along row 8 from the right edge,
        // bits 8–14 run down column 8 from row size-7.
        for ($bit = 0; $bit <= 7; $bit++) {
            $m[8][$size - 1 - $bit] = self::FORMAT_BITS[14 - $bit] === 1;
        }
        for ($bit = 8; $bit <= 14; $bit++) {
            $m[$size - 15 + $bit][8] = self::FORMAT_BITS[14 - $bit] === 1;
        }
        $m[$size - 8][8] = true; // the dark module sits inside the second copy's column

        // 3. Data modules in the zig-zag order, mask 0 ((r + c) % 2 === 0).
        $stream = [];
        foreach ($codewords as $byte) {
            for ($j = 7; $j >= 0; $j--) {
                $stream[] = ($byte >> $j) & 1;
            }
        }
        $index = 0;
        $upward = true;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right--;
            }
            for ($k = 0; $k < $size; $k++) {
                $r = $upward ? $size - 1 - $k : $k;
                foreach ([$right, $right - 1] as $c) {
                    if ($m[$r][$c] === null) {
                        $bit = $index < count($stream) ? $stream[$index++] : 0;
                        $m[$r][$c] = ($bit === 1) !== (($r + $c) % 2 === 0);
                    }
                }
            }
            $upward = !$upward;
        }

        return array_map(fn (array $row) => array_map(fn ($cell) => (bool) $cell, $row), $m);
    }

    /**
     * Inline SVG of the code with the standard 4-module quiet zone, dark on white.
     */
    public static function svg(string $text, int $pixels = 220, string $label = 'Ticket QR code'): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $view = $size + 8;
        $path = '';
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if ($dark) {
                    $path .= 'M' . ($c + 4) . ' ' . ($r + 4) . 'h1v1h-1z';
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" role="img" aria-label="%3$s" shape-rendering="crispEdges"><rect width="%1$d" height="%1$d" fill="#FFFFFF"/><path d="%4$s" fill="#07111F"/></svg>',
            $view,
            $pixels,
            htmlspecialchars($label, ENT_QUOTES),
            $path,
        );
    }

    /** @return list<int> */
    private static function reedSolomon(array $data, int $degree): array
    {
        self::initField();

        // Generator polynomial (x - a^0)(x - a^1)...(x - a^(degree-1)), highest power first.
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            $root = self::$exp[$i];
            foreach ($gen as $j => $coef) {
                $next[$j] ^= self::mul($coef, $root);
                $next[$j + 1] ^= $coef;
            }
            $gen = $next;
        }

        $remainder = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            for ($j = 0; $j < $degree - 1; $j++) {
                $remainder[$j] = $remainder[$j + 1] ^ self::mul($gen[$degree - 1 - $j], $factor);
            }
            $remainder[$degree - 1] = self::mul($gen[0], $factor);
        }

        return $remainder;
    }

    private static function initField(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $value;
            self::$exp[$i + 255] = $value;
            self::$log[$value] = $i;
            $value = ($value << 1) ^ (($value & 128) ? 0x11D : 0);
        }
        self::$log[0] = 0;
    }

    private static function mul(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }
}
