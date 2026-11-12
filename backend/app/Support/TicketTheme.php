<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;

/**
 * The visual theme of an event's tickets, so a dancehall night, a fish fry, a wedding and a conference do not all look
 * the same. Chosen only from what the host declared: the kind of event (photo_subject) and the event's category,
 * never guessed from the title. The host's own ticket designer choices (style and accent per ticket type) still apply
 * on top. Themes change colour, type and a quiet background motif only: the QR code always sits on plain white.
 *
 * Every theme uses the EventBite palette (navy surfaces, lime, purple) with one supporting colour.
 */
final class TicketTheme
{
    /** key => label, accents, and the CSS class that carries the rest (06-components.css, "Ticket themes"). */
    public const THEMES = [
        'island' => ['label' => 'Caribbean', 'accent' => '#C7FF45', 'accent2' => '#FBBF24'],
        'stage' => ['label' => 'Music & entertainment', 'accent' => '#8B5CF6', 'accent2' => '#C7FF45'],
        'feast' => ['label' => 'Food', 'accent' => '#FB923C', 'accent2' => '#C7FF45'],
        'celebration' => ['label' => 'Celebration', 'accent' => '#E7C873', 'accent2' => '#F8FAFC'],
        'professional' => ['label' => 'Professional', 'accent' => '#7DD3FC', 'accent2' => '#A8B6C8'],
        'community' => ['label' => 'Community', 'accent' => '#34D399', 'accent2' => '#C7FF45'],
        'classic' => ['label' => 'Event', 'accent' => '#C7FF45', 'accent2' => '#8B5CF6'],
    ];

    /** Kinds of event (config/photo_library.php) and the theme each takes. */
    private const BY_SUBJECT = [
        'reggae_concert' => 'island', 'dancehall_event' => 'island', 'jamaican_fish_fry' => 'island',
        'caribbean_food_festival' => 'island',
        'live_band' => 'stage', 'dj_set' => 'stage', 'music_festival' => 'stage', 'outdoor_concert' => 'stage',
        'nightlife' => 'stage', 'rooftop_party' => 'stage', 'arts_entertainment' => 'stage', 'vip_experience' => 'stage',
        'international_food_festival' => 'feast', 'outdoor_cookout' => 'feast', 'street_food_market' => 'feast',
        'food_vendor_gathering' => 'feast',
        'wedding' => 'celebration', 'birthday_celebration' => 'celebration', 'formal_dinner' => 'celebration',
        'private_party' => 'celebration',
        'corporate_event' => 'professional',
        'cultural_celebration' => 'community', 'family_festival' => 'community', 'community_gathering' => 'community',
        'outdoor_picnic' => 'community', 'cultural_heritage' => 'community', 'community_fundraiser' => 'community',
        'family_entertainment_day' => 'community', 'sports_outdoor' => 'community',
    ];

    /** Words in the host's chosen category, checked in this order when no kind of event was given. */
    private const BY_CATEGORY = [
        'island' => ['jamaican', 'caribbean', 'reggae', 'dancehall', 'soca', 'carnival', 'island'],
        'celebration' => ['wedding', 'birthday', 'anniversary', 'gala', 'private', 'celebration'],
        'professional' => ['corporate', 'conference', 'business', 'professional', 'networking', 'seminar', 'workshop'],
        'stage' => ['music', 'concert', 'nightlife', 'dj', 'band', 'comedy', 'theatre', 'theater', 'dance', 'entertainment'],
        'feast' => ['food', 'cuisine', 'fish fry', 'cookout', 'barbecue', 'bbq', 'market', 'tasting'],
        'community' => ['community', 'family', 'cultural', 'culture', 'heritage', 'festival', 'sports', 'fundraiser'],
    ];

    private function __construct(public readonly string $key, public readonly string $label,
        public readonly string $accent, public readonly string $accent2) {}

    public static function for(?Event $event): self
    {
        $key = 'classic';
        if ($event) {
            $subject = (string) ($event->photo_subject ?? '');
            $category = mb_strtolower((string) ($event->category ?? ''));
            if (isset(self::BY_SUBJECT[$subject])) {
                $key = self::BY_SUBJECT[$subject];
            } elseif ($category !== '') {
                foreach (self::BY_CATEGORY as $theme => $words) {
                    foreach ($words as $word) {
                        if (str_contains($category, $word)) {
                            $key = $theme;
                            break 2;
                        }
                    }
                }
            }
        }
        $theme = self::THEMES[$key];

        return new self($key, $theme['label'], $theme['accent'], $theme['accent2']);
    }

    /** The class and custom properties for a ticket's root element. */
    public function cssClass(): string
    {
        return 'eb-ticket-theme eb-ticket-theme-' . $this->key;
    }

    public function style(): string
    {
        return "--tt-accent: {$this->accent}; --tt-accent-2: {$this->accent2};";
    }
}
