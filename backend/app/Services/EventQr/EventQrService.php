<?php

declare(strict_types=1);

namespace App\Services\EventQr;

use App\Models\Event;
use App\Models\EventQrCode;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The promotional QR identity of each event: one stable public code per event, an approved random design,
 * and validated artwork on the private disk.
 *
 * - ensureFor() is idempotent. The first call creates the identity (one row per event, enforced by a unique
 *   index) and renders the artwork; later calls do nothing once the artwork is ready, and retry a failed
 *   render without changing the code or design.
 * - redesign() replaces only the look; the public code, and so every printed copy, keeps working.
 * - Every design is decoded with a real QR reader before it is stored; a design that does not read back
 *   exactly is replaced by the conventional fallback design, and if even that fails the identity is kept
 *   with status "failed" for a retry. The event itself is never rolled back because of artwork.
 *
 * The code encodes only a URL with an opaque identifier: nothing about customers, payments or tickets.
 * It never admits anyone; ticket admission uses each order's own secure token.
 */
class EventQrService
{
    private const DISK = 'local';
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 10;

    public function __construct(
        protected QrArtworkRenderer $renderer
    ) {
    }

    /** The identity for the event, created and rendered if needed. Never throws because of artwork. */
    public function ensureFor(Event $event): EventQrCode
    {
        $qr = $this->identityFor($event);

        if ($qr->isReady() && Storage::disk(self::DISK)->exists($qr->asset_path)) {
            return $qr;
        }

        $design = is_array($qr->design) ? QrDesignCatalog::safe($qr->design) : QrDesignCatalog::generate('random');

        return $this->render($qr, $event, $design, $qr->design_version ?: 1);
    }

    /**
     * A new look from the chosen preset ("random" for any). The public code is unchanged.
     * The new artwork is validated before it replaces the current one.
     */
    public function redesign(Event $event, string $preset): EventQrCode
    {
        $qr = $this->identityFor($event);
        $palette = $preset === 'custom_theme' ? $this->flyerPalette($event) : null;

        return $this->render($qr, $event, QrDesignCatalog::generate($preset, $palette), $qr->design_version + 1);
    }

    /** The URL the event QR code encodes: a stable short link that resolves to the public event page. */
    public function destinationUrl(EventQrCode $qr): string
    {
        return rtrim((string) config('app.url'), '/') . '/e/' . $qr->public_code;
    }

    public function svg(EventQrCode $qr): string
    {
        if ($qr->asset_path && Storage::disk(self::DISK)->exists($qr->asset_path)) {
            return (string) Storage::disk(self::DISK)->get($qr->asset_path);
        }

        return $this->renderer->svg($this->destinationUrl($qr), $this->designOf($qr));
    }

    public function png(EventQrCode $qr, int $pixelsPerModule = 24): string
    {
        return $this->renderer->png($this->destinationUrl($qr), $this->designOf($qr), $pixelsPerModule);
    }

    public function poster(EventQrCode $qr, Event $event): string
    {
        $date = trim(implode(' · ', array_filter([(string) $event->date_str, (string) $event->time_str])));

        return $this->renderer->poster($this->destinationUrl($qr), $this->designOf($qr), (string) $event->title, $date, 'Scan for tickets, food & event details');
    }

    /** Decodes the artwork with a real QR reader; true only when it reads back exactly the expected URL. */
    public function decodes(string $png, string $expected): bool
    {
        try {
            return (string) (new QRCode(new QROptions(['readerGrayscale' => true])))->readFromBlob($png) === $expected;
        } catch (\Throwable) {
            return false;
        }
    }

    private function identityFor(Event $event): EventQrCode
    {
        $existing = EventQrCode::where('event_id', $event->id)->first();
        if ($existing) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(fn () => EventQrCode::create([
                    'event_id' => $event->id,
                    'public_code' => $this->newPublicCode(),
                    'status' => EventQrCode::STATUS_PENDING,
                ]));
            } catch (UniqueConstraintViolationException) {
                // Either another request created this event's identity first (use it), or the random code
                // collided with another event's (try a new one).
                $existing = EventQrCode::where('event_id', $event->id)->first();
                if ($existing) {
                    return $existing;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a unique event QR code.');
    }

    /** @param array<string, mixed> $design */
    private function render(EventQrCode $qr, Event $event, array $design, int $version): EventQrCode
    {
        $url = $this->destinationUrl($qr);
        $qr->attempts = $qr->attempts + 1;

        try {
            $chosen = $this->firstReadable($url, [$design, QrDesignCatalog::fallback('the design did not decode reliably', (string) ($design['preset'] ?? ''))]);
            if ($chosen === null) {
                throw new \RuntimeException('Neither the design nor the fallback design decoded.');
            }

            $svg = $this->renderer->svg($url, $chosen);
            $path = "event-qr/{$event->id}/v{$version}.svg";
            Storage::disk(self::DISK)->put($path, $svg);

            $previous = $qr->asset_path;
            DB::transaction(function () use ($qr, $chosen, $version, $path) {
                $qr->fill([
                    'preset' => $chosen['preset'],
                    'design' => $chosen,
                    'design_version' => $version,
                    'status' => EventQrCode::STATUS_READY,
                    'asset_path' => $path,
                    'failure_reason' => null,
                    'generated_at' => now(),
                ])->save();
            });

            if ($previous && $previous !== $path) {
                Storage::disk(self::DISK)->delete($previous);
            }
        } catch (\Throwable $e) {
            Log::warning('Event QR artwork generation failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            // Keep any working artwork from before; only mark failed when there is none.
            if (!$qr->isReady()) {
                $qr->status = EventQrCode::STATUS_FAILED;
            }
            $qr->failure_reason = mb_substr($e->getMessage(), 0, 255);
            $qr->save();
        }

        return $qr->fresh() ?? $qr;
    }

    /**
     * The first design whose artwork decodes back to the URL at print size, at small size, and after
     * JPEG compression.
     *
     * @param list<array<string, mixed>> $designs
     * @return array<string, mixed>|null
     */
    private function firstReadable(string $url, array $designs): ?array
    {
        foreach ($designs as $design) {
            if (QrDesignCatalog::problemWith($design) !== null) {
                continue;
            }
            $large = $this->renderer->png($url, $design, 8);
            $small = $this->renderer->png($url, $design, 4);
            if ($this->decodes($large, $url) && $this->decodes($small, $url) && $this->decodes($this->jpeg($large, 60), $url)) {
                return $design;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function designOf(EventQrCode $qr): array
    {
        return is_array($qr->design) ? QrDesignCatalog::safe($qr->design) : QrDesignCatalog::fallback();
    }

    private function jpeg(string $png, int $quality): string
    {
        $img = imagecreatefromstring($png);
        ob_start();
        imagejpeg($img, null, $quality);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        return $jpeg;
    }

    private function newPublicCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Up to 12 colours sampled from the event's flyer, for the Custom Event Theme. The flyer is read only as
     * pixels (decoded by GD from a size-checked JPEG, PNG or WebP); nothing from it is copied into the artwork.
     *
     * @return list<string>
     */
    private function flyerPalette(Event $event): array
    {
        $path = (string) ($event->flyer_path ?? '');
        if ($path === '') {
            return [];
        }

        try {
            $disk = Storage::disk('s3_flyers');
            if (!$disk->exists($path) || $disk->size($path) > 10 * 1024 * 1024) {
                return [];
            }
            $bytes = (string) $disk->get($path);
            $info = @getimagesizefromstring($bytes);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40_000_000) {
                return [];
            }
            $img = @imagecreatefromstring($bytes);
            if (!$img) {
                return [];
            }
            $thumb = imagescale($img, 6, 6, IMG_BILINEAR_FIXED);
            $colors = [];
            for ($y = 0; $y < 6; $y++) {
                for ($x = 0; $x < 6; $x++) {
                    $rgb = imagecolorat($thumb, $x, $y);
                    $colors[] = sprintf('#%02X%02X%02X', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
                }
            }

            return array_slice(array_values(array_unique($colors)), 0, 12);
        } catch (\Throwable) {
            return [];
        }
    }
}
