<?php

namespace App\Support;

use App\Models\Event;
use App\Services\ReceiptService;

/**
 * Cover artwork for an event that has no uploaded flyer: its name, date and place over layered colour fields, soft
 * light, arcs and the EventBite fork-pin motif, in a palette chosen from the event's id so each event looks its own.
 * It uses no photography or cultural motifs, so an event is never shown under another culture's imagery while its
 * host has not uploaded a flyer.
 */
final class GeneratedFlyer
{
    /** Accent colours from the design tokens; each has strong contrast on the dark canvas. */
    private const ACCENTS = ['#C7FF45', '#C4B5FD', '#7DD3FC', '#FBBF24', '#4ADE80', '#FCA5A5'];

    /** The second light in each composition: the brand purple, or a cool teal for purple accents. */
    private const SECONDARY = ['#8B5CF6', '#22D3EE', '#8B5CF6', '#F472B6', '#8B5CF6', '#8B5CF6'];

    public static function svg(Event $event): string
    {
        $seed = crc32((string) $event->id);
        $pick = $seed % count(self::ACCENTS);
        $accent = self::ACCENTS[$pick];
        $second = self::SECONDARY[$pick];
        // Composition varies by event: where the light sits and how the arcs turn.
        $orbX = 760 + ($seed >> 3) % 260;
        $orbY = 80 + ($seed >> 7) % 160;
        $turn = ($seed >> 11) % 40 - 20;
        $title = self::lines((string) $event->title, 20, 3);
        $date = (string) (ReceiptService::eventDate($event) ?? '');
        $place = trim(implode(', ', array_filter([$event->city, $event->state_code])));
        $traditions = implode(' · ', array_slice($event->traditionLabels(), 0, 3));

        $e = fn (string $text) => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        // The title starts below the top quarter, where listing cards place their category and availability badges.
        $titleSize = count($title) >= 3 ? 66 : 80;
        $y = 330;
        $titleText = '';
        foreach ($title as $i => $line) {
            $titleText .= '<text x="80" y="' . ($y + $i * ($titleSize + 8)) . '" font-size="' . $titleSize . '" font-weight="800" fill="#F8FAFC">' . $e($line) . '</text>';
        }
        $below = $y + count($title) * ($titleSize + 8) + 30;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" role="img" aria-label="{$e((string) $event->title)}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#152C45"/><stop offset="0.55" stop-color="#0B192B"/><stop offset="1" stop-color="#07111F"/></linearGradient>
    <radialGradient id="glow" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="{$accent}" stop-opacity="0.38"/><stop offset="1" stop-color="{$accent}" stop-opacity="0"/></radialGradient>
    <radialGradient id="glow2" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="{$second}" stop-opacity="0.34"/><stop offset="1" stop-color="{$second}" stop-opacity="0"/></radialGradient>
    <linearGradient id="sheen" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#FFFFFF" stop-opacity="0"/><stop offset="0.5" stop-color="#FFFFFF" stop-opacity="0.07"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/></linearGradient>
    <linearGradient id="fade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#07111F" stop-opacity="0.85"/><stop offset="0.6" stop-color="#07111F" stop-opacity="0.2"/><stop offset="1" stop-color="#07111F" stop-opacity="0"/></linearGradient>
  </defs>
  <rect width="1200" height="800" fill="url(#bg)"/>
  <circle cx="{$orbX}" cy="{$orbY}" r="520" fill="url(#glow)"/>
  <circle cx="{$e((string) (1200 - $orbX + 420))}" cy="760" r="460" fill="url(#glow2)"/>
  <g fill="none" stroke-linecap="round" transform="rotate({$turn} 940 400)">
    <circle cx="940" cy="400" r="210" stroke="{$accent}" stroke-opacity="0.28" stroke-width="3"/>
    <circle cx="940" cy="400" r="290" stroke="#F8FAFC" stroke-opacity="0.08" stroke-width="2" stroke-dasharray="4 14"/>
    <path d="M 650 400 A 290 290 0 0 1 1230 400" stroke="{$second}" stroke-opacity="0.45" stroke-width="10"/>
    <circle cx="940" cy="400" r="370" stroke="#F8FAFC" stroke-opacity="0.05" stroke-width="1.5"/>
  </g>
  <g transform="translate(860 240) scale(1.25)" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.5">
    <path d="M64 4C98 4 124 30 124 64 124 104 64 158 64 158 64 158 4 104 4 64 4 30 30 4 64 4Z" stroke="{$accent}" stroke-width="7"/>
    <path d="M46 26v30M64 26v30M82 26v30M46 56c0 12 8 18 18 18s18-6 18-18M64 74v62" stroke="#F8FAFC" stroke-opacity="0.75" stroke-width="7"/>
    <circle cx="112" cy="96" r="9" fill="#07111F" stroke="none"/><circle cx="104" cy="108" r="8" fill="#07111F" stroke="none"/>
  </g>
  <rect x="-200" y="0" width="420" height="800" fill="url(#sheen)" transform="rotate(18 600 400) translate(520 0)"/>
  <rect width="1200" height="800" fill="url(#fade)"/>
  <rect x="80" y="240" width="96" height="10" rx="5" fill="{$accent}"/>
  <rect x="188" y="240" width="28" height="10" rx="5" fill="{$second}"/>
  <g font-family="'Plus Jakarta Sans', Inter, 'Segoe UI', Arial, sans-serif">
    {$titleText}
    <text x="80" y="{$below}" font-size="40" font-weight="700" fill="{$accent}">{$e($date)}</text>
    <text x="80" y="{$e((string) ($below + 56))}" font-size="34" font-weight="600" fill="#A8B6C8">{$e($place)}</text>
    <text x="80" y="720" font-size="28" font-weight="600" fill="#8494A8">{$e($traditions)}</text>
    <text x="1120" y="720" font-size="30" font-weight="800" fill="#F8FAFC" text-anchor="end">EventBite</text>
  </g>
</svg>
SVG;
    }

    /** @return list<string> the text in up to $max lines of about $width characters; the last line ends in … if cut */
    private static function lines(string $text, int $width, int $max): array
    {
        $lines = explode("\n", wordwrap(trim($text), $width, "\n", true));
        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = rtrim(mb_substr($lines[$max - 1], 0, $width - 1)) . '…';
        }

        return $lines;
    }
}
