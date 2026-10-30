<?php

declare(strict_types=1);

namespace App\Services\EventQr;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Draws event QR artwork from a design (see QrDesignCatalog).
 *
 * The QR matrix comes from chillerlan/php-qrcode and is never altered: shapes only change how each module is
 * drawn inside its own cell. The artwork is built once as a list of shapes in module units, then written out
 * as SVG or painted as PNG with GD, so both formats always share the same geometry.
 *
 * Layout, from the inside out: the matrix, a 4-module quiet zone in the plate colour (the QR minimum), then
 * the decorative frame. Borders, labels and decorations live only in the frame, never in the quiet zone.
 * A centre logo is only drawn in the area the encoder cleared for it at error correction H.
 *
 * SVG output contains only numbers, validated #RRGGBB colours and escaped text: no markup from outside.
 */
class QrArtworkRenderer
{
    private const QUIET_ZONE = 4;
    private const FRAME = 3;
    private const LABEL_SPACE = 2.6;

    /**
     * The QR matrix for the data at the design's error-correction level.
     *
     * @param array<string, mixed> $design
     * @return array{size: int, dark: list<list<bool>>, logo: ?array{x: int, y: int, w: int, h: int}}
     */
    public function matrix(string $data, array $design): array
    {
        $ecc = ['M' => EccLevel::M, 'Q' => EccLevel::Q, 'H' => EccLevel::H][$design['ecc']] ?? EccLevel::M;
        $withLogo = ($design['logo'] ?? 'none') !== 'none';

        $probe = (new QRCode(new QROptions(['eccLevel' => $ecc, 'addQuietzone' => false])))->addByteSegment($data)->getQRMatrix();
        $size = $probe->getSize();

        $options = ['eccLevel' => $ecc, 'addQuietzone' => false];
        if ($withLogo) {
            // About a fifth of the width, odd so it centres on a module; error correction H restores the data.
            $logo = max(5, (int) floor($size * 0.2));
            $logo += ($logo % 2 === 0) ? 1 : 0;
            $options += ['addLogoSpace' => true, 'logoSpaceWidth' => $logo, 'logoSpaceHeight' => $logo];
        }

        $matrix = (new QRCode(new QROptions($options)))->addByteSegment($data)->getQRMatrix();
        $size = $matrix->getSize();

        $dark = [];
        $logoBox = null;
        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                $row[] = $matrix->check($x, $y);
                if ($matrix->checkType($x, $y, QRMatrix::M_LOGO)) {
                    $logoBox = $logoBox === null
                        ? ['x0' => $x, 'y0' => $y, 'x1' => $x, 'y1' => $y]
                        : ['x0' => min($logoBox['x0'], $x), 'y0' => min($logoBox['y0'], $y), 'x1' => max($logoBox['x1'], $x), 'y1' => max($logoBox['y1'], $y)];
                }
            }
            $dark[] = $row;
        }

        return [
            'size' => $size,
            'dark' => $dark,
            'logo' => $logoBox === null ? null : ['x' => $logoBox['x0'], 'y' => $logoBox['y0'], 'w' => $logoBox['x1'] - $logoBox['x0'] + 1, 'h' => $logoBox['y1'] - $logoBox['y0'] + 1],
        ];
    }

    /** @param array<string, mixed> $design */
    public function svg(string $data, array $design, int $pixelsPerModule = 10): string
    {
        [$width, $height, $shapes] = $this->scene($data, $design);

        return $this->toSvg($shapes, $width, $height, $pixelsPerModule);
    }

    /** @param array<string, mixed> $design */
    public function png(string $data, array $design, int $pixelsPerModule = 16): string
    {
        [$width, $height, $shapes] = $this->scene($data, $design);

        return $this->toPng($shapes, $width, $height, $pixelsPerModule);
    }

    /**
     * A print layout: the QR artwork with the event name, date and a short call to action around it.
     * Event text sits outside the QR artwork, so it never touches the scanning region.
     *
     * @param array<string, mixed> $design
     */
    public function poster(string $data, array $design, string $title, string $dateLine, string $callToAction): string
    {
        [$qrWidth, $qrHeight, $shapes] = $this->scene($data, $design);
        $frame = $design['frame'];
        $text = $this->readableOn($frame, [$design['border_color'], '#FFFFFF', '#0B0F14']);

        $w = 1200;
        $qrSize = 840;
        $qrX = ($w - $qrSize) / 2;
        $qrY = 360;
        $h = (int) ($qrY + $qrSize * ($qrHeight / $qrWidth) + 260);

        $qr = $this->toSvg($shapes, $qrWidth, $qrHeight, 10, false);
        $title = $this->escape(mb_strimwidth($title, 0, 60, '…'));

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">', $w, $h, $w, $h)
            . sprintf('<rect width="%d" height="%d" fill="%s"/>', $w, $h, $frame)
            . sprintf('<text x="%d" y="170" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="68" font-weight="800" fill="%s">%s</text>', $w / 2, $text, $title)
            . sprintf('<text x="%d" y="250" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="38" fill="%s">%s</text>', $w / 2, $text, $this->escape($dateLine))
            . sprintf('<svg x="%s" y="%d" width="%d" height="%s" viewBox="0 0 %s %s">%s</svg>', $this->n($qrX), $qrY, $qrSize, $this->n($qrSize * $qrHeight / $qrWidth), $this->n($qrWidth), $this->n($qrHeight), $qr)
            . sprintf('<text x="%d" y="%d" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="44" font-weight="700" fill="%s">%s</text>', $w / 2, $h - 110, $text, $this->escape($callToAction))
            . sprintf('<text x="%d" y="%d" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="26" fill="%s" opacity="0.8">EventBite</text>', $w / 2, $h - 55, $text)
            . '</svg>';
    }

    /**
     * @param array<string, mixed> $design
     * @return array{float, float, list<array<string, mixed>>}
     */
    private function scene(string $data, array $design): array
    {
        $m = $this->matrix($data, $design);
        $size = $m['size'];
        $q = self::QUIET_ZONE;
        $f = self::FRAME;
        $hasLabel = !empty($design['label']);

        $width = $size + 2 * $q + 2 * $f;
        $height = $width + ($hasLabel ? self::LABEL_SPACE : 0);
        $ox = $f + $q; // matrix origin
        $oy = $f + $q;
        $fg = $design['fg'];
        $plate = $design['plate'];
        $accent = $design['finder_accent'] ?? null ?: $fg;

        $shapes = [];
        $shapes[] = ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => $width, 'h' => $height, 'r' => 1.2, 'fill' => $design['frame']];
        array_push($shapes, ...$this->border($design, $width, $height));
        $shapes[] = ['type' => 'rect', 'x' => $f, 'y' => $f, 'w' => $size + 2 * $q, 'h' => $size + 2 * $q, 'r' => 0.8, 'fill' => $plate];

        // Data modules (finder patterns are drawn whole below)
        $finderOrigins = [[0, 0], [$size - 7, 0], [0, $size - 7]];
        $inFinder = function (int $x, int $y) use ($finderOrigins): bool {
            foreach ($finderOrigins as [$fx, $fy]) {
                if ($x >= $fx && $x < $fx + 7 && $y >= $fy && $y < $fy + 7) {
                    return true;
                }
            }

            return false;
        };

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$m['dark'][$y][$x] || $inFinder($x, $y)) {
                    continue;
                }
                $shapes[] = match ($design['module']) {
                    'dot' => ['type' => 'circle', 'cx' => $ox + $x + 0.5, 'cy' => $oy + $y + 0.5, 'r' => 0.46, 'fill' => $fg],
                    'rounded' => ['type' => 'rect', 'x' => $ox + $x, 'y' => $oy + $y, 'w' => 1, 'h' => 1, 'r' => 0.32, 'fill' => $fg],
                    default => ['type' => 'rect', 'x' => $ox + $x, 'y' => $oy + $y, 'w' => 1, 'h' => 1, 'r' => 0, 'fill' => $fg],
                };
            }
        }

        foreach ($finderOrigins as [$fx, $fy]) {
            array_push($shapes, ...$this->finder($design['finder'], $ox + $fx, $oy + $fy, $accent, $plate, $fg));
        }

        if ($m['logo'] !== null && ($design['logo'] ?? 'none') === 'bite') {
            array_push($shapes, ...$this->biteMark($ox + $m['logo']['x'], $oy + $m['logo']['y'], $m['logo']['w'], $plate, $fg));
        }

        if ($hasLabel) {
            $shapes[] = [
                'type' => 'text', 'x' => $width / 2, 'y' => $f + $size + 2 * $q + 1.9,
                'text' => (string) $design['label'], 'size' => 1.35,
                'fill' => $this->readableOn($design['frame'], [$design['border_color'], '#FFFFFF', '#0B0F14']),
                'serif' => ($design['preset'] ?? '') === 'editorial',
            ];
        }

        return [$width, $height, $shapes];
    }

    /** @return list<array<string, mixed>> */
    private function finder(string $shape, float $x, float $y, string $outer, string $plate, string $inner): array
    {
        if ($shape === 'circle') {
            return [
                ['type' => 'circle', 'cx' => $x + 3.5, 'cy' => $y + 3.5, 'r' => 3.5, 'fill' => $outer],
                ['type' => 'circle', 'cx' => $x + 3.5, 'cy' => $y + 3.5, 'r' => 2.5, 'fill' => $plate],
                ['type' => 'circle', 'cx' => $x + 3.5, 'cy' => $y + 3.5, 'r' => 1.5, 'fill' => $inner],
            ];
        }

        $rounded = $shape === 'rounded';

        return [
            ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => 7, 'h' => 7, 'r' => $rounded ? 2.0 : 0, 'fill' => $outer],
            ['type' => 'rect', 'x' => $x + 1, 'y' => $y + 1, 'w' => 5, 'h' => 5, 'r' => $rounded ? 1.4 : 0, 'fill' => $plate],
            ['type' => 'rect', 'x' => $x + 2, 'y' => $y + 2, 'w' => 3, 'h' => 3, 'r' => $rounded ? 0.9 : 0, 'fill' => $inner],
        ];
    }

    /**
     * EventBite's mark, simplified: a ticket with a bite out of one corner, drawn inside the cleared logo area.
     *
     * @return list<array<string, mixed>>
     */
    private function biteMark(float $x, float $y, int $w, string $plate, string $ink): array
    {
        $pad = 0.9;
        $s = $w - 2 * $pad;

        return [
            ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $w, 'r' => 0.9, 'fill' => $plate],
            ['type' => 'rect', 'x' => $x + $pad, 'y' => $y + $pad + $s * 0.18, 'w' => $s, 'h' => $s * 0.64, 'r' => $s * 0.12, 'fill' => $ink],
            ['type' => 'circle', 'cx' => $x + $pad + $s, 'cy' => $y + $pad + $s * 0.18, 'r' => $s * 0.24, 'fill' => $plate],
            ['type' => 'circle', 'cx' => $x + $pad, 'cy' => $y + $pad + $s * 0.5, 'r' => $s * 0.1, 'fill' => $plate],
            ['type' => 'circle', 'cx' => $x + $pad + $s, 'cy' => $y + $pad + $s * 0.62, 'r' => $s * 0.1, 'fill' => $plate],
        ];
    }

    /**
     * @param array<string, mixed> $design
     * @return list<array<string, mixed>>
     */
    private function border(array $design, float $width, float $height): array
    {
        $c = $design['border_color'];
        $i = 0.9; // inset from the outer edge; always inside the frame, never in the quiet zone

        return match ($design['border']) {
            'line' => [['type' => 'stroke', 'x' => $i, 'y' => $i, 'w' => $width - 2 * $i, 'h' => $height - 2 * $i, 'sw' => 0.35, 'color' => $c, 'dash' => false]],
            'double' => [
                ['type' => 'stroke', 'x' => 0.6, 'y' => 0.6, 'w' => $width - 1.2, 'h' => $height - 1.2, 'sw' => 0.3, 'color' => $c, 'dash' => false],
                ['type' => 'stroke', 'x' => 1.5, 'y' => 1.5, 'w' => $width - 3, 'h' => $height - 3, 'sw' => 0.15, 'color' => $c, 'dash' => false],
            ],
            'dashed' => [['type' => 'stroke', 'x' => $i, 'y' => $i, 'w' => $width - 2 * $i, 'h' => $height - 2 * $i, 'sw' => 0.35, 'color' => $c, 'dash' => true]],
            'corners' => $this->cornerBrackets($width, $height, $c),
            'zigzag' => $this->zigzag($width, $height, $c),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function cornerBrackets(float $w, float $h, string $c): array
    {
        $len = 3.2;
        $t = 0.45;
        $i = 0.8;
        $shapes = [];
        foreach ([[$i, $i, 1, 1], [$w - $i, $i, -1, 1], [$i, $h - $i, 1, -1], [$w - $i, $h - $i, -1, -1]] as [$x, $y, $dx, $dy]) {
            $shapes[] = ['type' => 'poly', 'fill' => $c, 'points' => [
                [$x, $y], [$x + $dx * $len, $y], [$x + $dx * $len, $y + $dy * $t],
                [$x + $dx * $t, $y + $dy * $t], [$x + $dx * $t, $y + $dy * $len], [$x, $y + $dy * $len],
            ]];
        }

        return $shapes;
    }

    /** A sawtooth along the top and bottom edges of the frame. @return list<array<string, mixed>> */
    private function zigzag(float $w, float $h, string $c): array
    {
        $tooth = 1.0;
        $depth = 0.9;
        $count = (int) floor($w / $tooth);
        $top = [[0, 0]];
        $bottom = [[0, $h]];
        for ($k = 0; $k < $count; $k++) {
            $top[] = [$k * $tooth + $tooth / 2, $depth];
            $top[] = [($k + 1) * $tooth, 0];
            $bottom[] = [$k * $tooth + $tooth / 2, $h - $depth];
            $bottom[] = [($k + 1) * $tooth, $h];
        }
        $top[] = [$w, 0];
        $bottom[] = [$w, $h];

        return [['type' => 'poly', 'fill' => $c, 'points' => $top], ['type' => 'poly', 'fill' => $c, 'points' => $bottom]];
    }

    /** @param list<array<string, mixed>> $shapes */
    private function toSvg(array $shapes, float $width, float $height, int $scale, bool $standalone = true): string
    {
        $out = [];
        foreach ($shapes as $s) {
            $out[] = match ($s['type']) {
                'rect' => sprintf('<rect x="%s" y="%s" width="%s" height="%s"%s fill="%s"/>',
                    $this->n($s['x']), $this->n($s['y']), $this->n($s['w']), $this->n($s['h']),
                    $s['r'] > 0 ? sprintf(' rx="%s"', $this->n($s['r'])) : '', $this->color($s['fill'])),
                'circle' => sprintf('<circle cx="%s" cy="%s" r="%s" fill="%s"/>', $this->n($s['cx']), $this->n($s['cy']), $this->n($s['r']), $this->color($s['fill'])),
                'stroke' => sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="0.8" fill="none" stroke="%s" stroke-width="%s"%s/>',
                    $this->n($s['x']), $this->n($s['y']), $this->n($s['w']), $this->n($s['h']), $this->color($s['color']), $this->n($s['sw']),
                    $s['dash'] ? ' stroke-dasharray="1 0.6"' : ''),
                'poly' => sprintf('<polygon points="%s" fill="%s"/>',
                    implode(' ', array_map(fn ($p) => $this->n($p[0]) . ',' . $this->n($p[1]), $s['points'])), $this->color($s['fill'])),
                'text' => sprintf('<text x="%s" y="%s" text-anchor="middle" font-family="%s" font-size="%s" font-weight="600" fill="%s">%s</text>',
                    $this->n($s['x']), $this->n($s['y']), $s['serif'] ? 'Georgia, \'Times New Roman\', serif' : 'Inter, Helvetica, Arial, sans-serif',
                    $this->n($s['size']), $this->color($s['fill']), $this->escape($s['text'])),
            };
        }

        $body = implode('', $out);
        if (!$standalone) {
            return $body;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%d" height="%d" shape-rendering="geometricPrecision">',
                $this->n($width), $this->n($height), (int) round($width * $scale), (int) round($height * $scale))
            . $body . '</svg>';
    }

    /** @param list<array<string, mixed>> $shapes */
    private function toPng(array $shapes, float $width, float $height, int $scale): string
    {
        $img = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
        $colors = [];
        $col = function (string $hex) use ($img, &$colors) {
            return $colors[$hex] ??= imagecolorallocate($img, ...QrDesignCatalog::rgb($hex));
        };
        $p = fn (float $v) => (int) round($v * $scale);

        foreach ($shapes as $s) {
            switch ($s['type']) {
                case 'rect':
                    $this->gdRoundedRect($img, $p($s['x']), $p($s['y']), $p($s['x'] + $s['w']) - 1, $p($s['y'] + $s['h']) - 1, $p($s['r']), $col($s['fill']));
                    break;
                case 'circle':
                    $d = max(1, (int) round(2 * $s['r'] * $scale));
                    imagefilledellipse($img, $p($s['cx']), $p($s['cy']), $d, $d, $col($s['fill']));
                    break;
                case 'stroke':
                    imagesetthickness($img, max(1, $p($s['sw'])));
                    if ($s['dash']) {
                        $c = $col($s['color']);
                        $style = array_merge(array_fill(0, max(1, $p(1)), $c), array_fill(0, max(1, $p(0.6)), IMG_COLOR_TRANSPARENT));
                        imagesetstyle($img, $style);
                        imagerectangle($img, $p($s['x']), $p($s['y']), $p($s['x'] + $s['w']), $p($s['y'] + $s['h']), IMG_COLOR_STYLED);
                    } else {
                        imagerectangle($img, $p($s['x']), $p($s['y']), $p($s['x'] + $s['w']), $p($s['y'] + $s['h']), $col($s['color']));
                    }
                    imagesetthickness($img, 1);
                    break;
                case 'poly':
                    $flat = [];
                    foreach ($s['points'] as [$x, $y]) {
                        $flat[] = $p($x);
                        $flat[] = $p($y);
                    }
                    imagefilledpolygon($img, $flat, $col($s['fill']));
                    break;
                case 'text':
                    // Labels are part of the SVG artwork only; GD has no reliable font here, so the PNG omits them.
                    break;
            }
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    private function gdRoundedRect(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = min($r, intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2));
        if ($r <= 0) {
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);

            return;
        }
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, 2 * $r, 2 * $r, $color);
        }
    }

    /** @param list<string> $candidates */
    private function readableOn(string $background, array $candidates): string
    {
        foreach ($candidates as $c) {
            if (QrDesignCatalog::isHex($c) && QrDesignCatalog::contrast($c, $background) >= 4.5) {
                return $c;
            }
        }

        return QrDesignCatalog::luminance($background) > 0.4 ? '#0B0F14' : '#FFFFFF';
    }

    private function color(string $hex): string
    {
        return QrDesignCatalog::isHex($hex) ? $hex : '#000000';
    }

    private function n(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
