<?php

namespace App\Support;

use App\Models\Event;
use App\Services\ReceiptService;

/**
 * A typographic flyer for an event that has no uploaded artwork: its name, date and place on the EventBite dark
 * canvas, with one accent colour chosen from the event's id. It uses no photography or cultural motifs, so an
 * event is never shown under another culture's imagery while its host has not uploaded a flyer.
 */
final class GeneratedFlyer
{
    /** Accent colours from the design tokens; each has strong contrast on the dark canvas. */
    private const ACCENTS = ['#C7FF45', '#C4B5FD', '#7DD3FC', '#FBBF24', '#4ADE80', '#FCA5A5'];

    public static function svg(Event $event): string
    {
        $accent = self::ACCENTS[crc32((string) $event->id) % count(self::ACCENTS)];
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
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#152C45"/><stop offset="1" stop-color="#07111F"/></linearGradient>
    <radialGradient id="glow" cx="0.85" cy="0.1" r="0.7"><stop offset="0" stop-color="{$accent}" stop-opacity="0.22"/><stop offset="1" stop-color="{$accent}" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="1200" height="800" fill="url(#bg)"/>
  <rect width="1200" height="800" fill="url(#glow)"/>
  <rect x="80" y="240" width="96" height="10" rx="5" fill="{$accent}"/>
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
