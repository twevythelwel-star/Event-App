<?php

namespace App\Models;

use App\Models\Concerns\SyncsMinorUnits;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, SyncsMinorUnits;

    protected $fillable = [
        'organizer_id',
        'event_id',
        'user_id',
        'ticket_code',
        'order_number',
        'customer_name',
        'customer_phone',
        'email',
        'method',
        'subtotal',
        'packaging_fee',
        'total_amount',
        'amount_paid',
        'subtotal_minor',
        'discount_minor',
        'fees_minor',
        'tax_minor',
        'total_minor',
        'amount_paid_minor',
        'refunded_minor',
        'currency',
        'status',
        'fulfillment_status',
        'payment_reference',
        'sender_name',
        'verified_at',
        'verified_by',
        'verification_notes',
        'rejection_reason',
        'refund_details',
        'authorized_unpaid',
        'secure_ticket_token',
        'secure_ticket_token_hash',
        'token_version',
        'token_reissued_at',
        'token_revoked_at',
        'revocation_reason',
        'idempotency_key',
        'ticket_restrictions',
        'all_handed_over',
        'closed_at',
        'reservation_expires_at',
        'inventory_released_at',
        'affiliate_id',
        'discount_code_id',
        'is_complimentary',
        'issued_by',
        'complimentary_reason',
        'check_in_request_id',
        'notes',
        'food_fulfillment_method',
        'pickup_window',
        'delivery_location',
    ];

    protected $hidden = [
        'secure_ticket_token',
        'secure_ticket_token_hash',
    ];

    /**
     * In-memory ephemeral vault mapping SHA-256 token hashes to raw tokens for QR generation/delivery.
     * Raw tokens are NEVER stored in the SQL database; the database stores only SHA-256 hashes.
     *
     * @var array<string, string>
     */
    private static array $runtimeTokenVault = [];

    /**
     * Transient raw token on the current model instance for immediate QR delivery.
     */
    protected ?string $rawTicketToken = null;

    protected $casts = [
        'subtotal' => 'decimal:2',
        'packaging_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'fees_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'amount_paid_minor' => 'integer',
        'refunded_minor' => 'integer',
        'refund_details' => 'array',
        'ticket_restrictions' => 'array',
        'authorized_unpaid' => 'boolean',
        'all_handed_over' => 'boolean',
        'verified_at' => 'datetime',
        'closed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
        'inventory_released_at' => 'datetime',
        'is_complimentary' => 'boolean',
        'token_version' => 'integer',
        'token_reissued_at' => 'datetime',
        'token_revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            // Ensure the SQL database ONLY ever stores a cryptographic SHA-256 hash, never a raw tok_... token
            $rawAttr = isset($order->attributes['secure_ticket_token']) ? trim((string) $order->attributes['secure_ticket_token']) : '';
            if ($rawAttr !== '' && !str_starts_with($rawAttr, 'sha256:')) {
                if (preg_match('/^[a-f0-9]{64}$/i', $rawAttr) === 1 && !str_starts_with($rawAttr, 'tok_')) {
                    $hash = strtolower($rawAttr);
                } else {
                    $hash = self::hashTicketToken($rawAttr);
                    self::rememberRawToken($hash, $rawAttr);
                    $order->rawTicketToken = $rawAttr;
                }
                $order->attributes['secure_ticket_token'] = 'sha256:' . $hash;
                if ($order->token_revoked_at === null) {
                    $order->attributes['secure_ticket_token_hash'] = $hash;
                }
            } elseif (str_starts_with($rawAttr, 'sha256:') && empty($order->attributes['secure_ticket_token_hash']) && $order->token_revoked_at === null) {
                $order->attributes['secure_ticket_token_hash'] = strtolower(substr($rawAttr, 7));
            }
        });
    }

    /**
     * Mutator: when a raw token is assigned, remember it for QR delivery and store ONLY its SHA-256 hash in DB attributes.
     */
    public function setSecureTicketTokenAttribute(?string $value): void
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            $this->attributes['secure_ticket_token'] = '';
            return;
        }

        if (str_starts_with($clean, 'sha256:')) {
            $hash = strtolower(substr($clean, 7));
            $this->attributes['secure_ticket_token'] = 'sha256:' . $hash;
            if (($this->attributes['token_revoked_at'] ?? null) === null) {
                $this->attributes['secure_ticket_token_hash'] = $hash;
            }
            return;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $clean) === 1 && !str_starts_with($clean, 'tok_')) {
            $hash = strtolower($clean);
            $this->attributes['secure_ticket_token'] = 'sha256:' . $hash;
            if (($this->attributes['token_revoked_at'] ?? null) === null) {
                $this->attributes['secure_ticket_token_hash'] = $hash;
            }
            return;
        }

        $hash = self::hashTicketToken($clean);
        self::rememberRawToken($hash, $clean);
        $this->rawTicketToken = $clean;
        $this->attributes['secure_ticket_token'] = 'sha256:' . $hash;
        if (($this->attributes['token_revoked_at'] ?? null) === null) {
            $this->attributes['secure_ticket_token_hash'] = $hash;
        }
    }

    /**
     * Accessor: returns the raw token for QR generation/delivery while the database row holds only the SHA-256 hash.
     */
    public function getSecureTicketTokenAttribute(?string $value): ?string
    {
        $hash = $this->attributes['secure_ticket_token_hash'] ?? null;
        if (!$hash && is_string($value) && str_starts_with($value, 'sha256:')) {
            $hash = strtolower(substr($value, 7));
        }

        if ($this->rawTicketToken !== null && (!$hash || self::hashTicketToken($this->rawTicketToken) === $hash)) {
            return $this->rawTicketToken;
        }

        if (is_string($hash) && $hash !== '') {
            $recalled = self::recallRawToken($hash);
            if ($recalled !== null) {
                return $this->rawTicketToken = $recalled;
            }
        }

        if (is_string($value) && $value !== '' && !str_starts_with($value, 'sha256:')) {
            $computedHash = self::hashTicketToken($value);
            self::rememberRawToken($computedHash, $value);
            return $this->rawTicketToken = $value;
        }

        return $value;
    }

    /**
     * Deliver the raw ticket token exclusively for rendering the attendee ticket QR code.
     * If a settled, unredeemed ticket row only has a database hash not yet in the delivery vault,
     * transparently rotates a fresh 256-bit CSPRNG token and persists its hash.
     */
    public function deliverTicketQrToken(): string
    {
        $token = (string) $this->secure_ticket_token;
        if (($token === '' || str_starts_with($token, 'sha256:')) && !$this->isTicketRevoked() && $this->checked_in_at === null) {
            $freshToken = self::generateSecureTicketToken();
            $hash = self::hashTicketToken($freshToken);
            self::rememberRawToken($hash, $freshToken);
            $this->rawTicketToken = $freshToken;
            self::withoutGlobalScopes()->whereKey($this->id)->update([
                'secure_ticket_token' => 'sha256:' . $hash,
                'secure_ticket_token_hash' => $hash,
            ]);
            $this->attributes['secure_ticket_token'] = 'sha256:' . $hash;
            $this->attributes['secure_ticket_token_hash'] = $hash;
            return $freshToken;
        }

        return $token;
    }

    /**
     * Remember a raw token in memory (and encrypted private vault file outside the DB for cross-request QR rendering).
     */
    private static function rememberRawToken(string $hash, string $rawToken): void
    {
        $hash = strtolower(trim($hash));
        $rawToken = trim($rawToken);
        if ($hash === '' || $rawToken === '' || str_starts_with($rawToken, 'sha256:')) {
            return;
        }

        self::$runtimeTokenVault[$hash] = $rawToken;

        try {
            if (function_exists('app') && app()->bound('config') && !app()->runningUnitTests()) {
                $vaultPath = storage_path('app/private/qr_delivery_vault.enc');
                $dir = dirname($vaultPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $map = self::loadEncryptedVault($vaultPath);
                $map[$hash] = $rawToken;
                self::saveEncryptedVault($vaultPath, $map);
            }
        } catch (\Throwable) {
            // Non-fatal fallback to in-memory vault
        }
    }

    /**
     * Recall a raw token from its SHA-256 hash for QR generation/delivery.
     */
    private static function recallRawToken(string $hash): ?string
    {
        $hash = strtolower(trim($hash));
        if (isset(self::$runtimeTokenVault[$hash])) {
            return self::$runtimeTokenVault[$hash];
        }

        try {
            if (function_exists('app') && app()->bound('config') && !app()->runningUnitTests()) {
                $vaultPath = storage_path('app/private/qr_delivery_vault.enc');
                $map = self::loadEncryptedVault($vaultPath);
                if (isset($map[$hash])) {
                    return self::$runtimeTokenVault[$hash] = $map[$hash];
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function loadEncryptedVault(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $key = hash('sha256', (string) config('app.key', 'eventbite-qr-vault-key'), true);
        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || strlen($decoded) < 48) {
            return [];
        }
        $iv = substr($decoded, 0, 16);
        $mac = substr($decoded, 16, 32);
        $cipher = substr($decoded, 48);
        if (!hash_equals($mac, hash_hmac('sha256', $iv . $cipher, $key, true))) {
            return [];
        }
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($plain)) {
            return [];
        }
        $data = json_decode($plain, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, string> $map
     */
    private static function saveEncryptedVault(string $path, array $map): void
    {
        $key = hash('sha256', (string) config('app.key', 'eventbite-qr-vault-key'), true);
        $iv = random_bytes(16);
        $plain = json_encode($map, JSON_UNESCAPED_SLASHES);
        $cipher = openssl_encrypt((string) $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($cipher)) {
            return;
        }
        $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
        @file_put_contents($path, base64_encode($iv . $mac . $cipher), LOCK_EX);
    }

    /**
     * Redact any raw admission token (tok_...) so raw tokens are NEVER stored in application or security audit logs.
     */
    public static function redactTokenForLog(string $text): string
    {
        return (string) preg_replace_callback('/\btok_[A-Za-z0-9_-]{4,}\b/', function (array $matches): string {
            return 'tok_[REDACTED:sha256:' . substr(hash('sha256', $matches[0]), 0, 12) . ']';
        }, $text);
    }

    /**
     * Generate a unique cryptographically random token with 256 bits (32 bytes) of CSPRNG entropy.
     */
    public static function generateSecureTicketToken(): string
    {
        return 'tok_' . bin2hex(random_bytes(32));
    }

    /**
     * Compute the authoritative SHA-256 hash of a raw ticket token.
     */
    public static function hashTicketToken(string $plainToken): string
    {
        $clean = trim($plainToken);
        if (str_starts_with($clean, 'sha256:')) {
            return strtolower(substr($clean, 7));
        }

        return hash('sha256', $clean);
    }

    /**
     * Scope to look up an order by hashing the submitted raw token with SHA-256 and matching the stored hash.
     */
    public function scopeWhereTicketToken(\Illuminate\Database\Eloquent\Builder $query, string $rawToken): \Illuminate\Database\Eloquent\Builder
    {
        $clean = trim($rawToken);
        $hash = self::hashTicketToken($clean);

        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($hash, $clean) {
            $q->where('secure_ticket_token_hash', $hash)
                ->orWhere('secure_ticket_token', 'sha256:' . $hash)
                ->orWhere(function (\Illuminate\Database\Eloquent\Builder $legacy) use ($clean) {
                    $legacy->whereNull('secure_ticket_token_hash')->where('secure_ticket_token', $clean);
                });
        });
    }

    /**
     * Whether this ticket has been revoked either explicitly or via terminal order status.
     */
    public function isTicketRevoked(): bool
    {
        return $this->token_revoked_at !== null
            || in_array($this->status, ['refunded', 'rejected', 'cancelled', 'expired'], true);
    }

    /**
     * Whether payment is settled and the ticket is eligible to display/redeem its admission QR code.
     */
    public function isSettledForAdmission(): bool
    {
        if ($this->isTicketRevoked()) {
            return false;
        }

        return in_array($this->status, ['paid', 'confirmed', 'partially_refunded'], true);
    }

    /**
     * Return the party size (ticket quantity) and tier summary covered by this order's admission pass.
     *
     * @return array{ticket_quantity: int, ticket_summary: string}
     */
    public function ticketAdmissionSummary(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        $ticketLines = $items->filter(fn (OrderItem $item) => $item->line_type === OrderItem::LINE_TICKET
            || strtolower((string) $item->category) === 'tickets'
            || !empty($item->ticket_tier_id));

        if ($ticketLines->isEmpty()) {
            return [
                'ticket_quantity' => 1,
                'ticket_summary' => '1 × Event Admission',
            ];
        }

        $totalQty = (int) $ticketLines->sum(fn (OrderItem $item) => max(1, (int) $item->quantity));
        $parts = $ticketLines->map(fn (OrderItem $item) => max(1, (int) $item->quantity) . ' × ' . $item->name)->values()->all();

        return [
            'ticket_quantity' => max(1, $totalQty),
            'ticket_summary' => implode(', ', $parts),
        ];
    }

    /**
     * Controlled replacement/reissue of the attendee ticket QR token.
     * Immediately invalidates the old token/hash so any copied screenshot ceases to work.
     */
    public function reissueTicketToken(?User $actor = null, string $reason = 'Ticket QR reissued'): string
    {
        if ($this->checked_in_at !== null) {
            throw new \App\Services\OrderPlacementException('Cannot reissue QR token: this ticket has already been redeemed at the gate.');
        }

        if (in_array($this->status, ['refunded', 'rejected', 'cancelled', 'expired'], true)) {
            throw new \App\Services\OrderPlacementException("Cannot reissue QR token for a {$this->status} order.");
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $reason) {
            $locked = self::withoutGlobalScopes()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($locked->checked_in_at !== null) {
                throw new \App\Services\OrderPlacementException('Cannot reissue QR token: this ticket has already been redeemed at the gate.');
            }

            $newToken = self::generateSecureTicketToken();
            $newHash = self::hashTicketToken($newToken);
            $nextVersion = max(1, (int) $locked->token_version) + 1;

            $locked->forceFill([
                'token_revoked_at' => null,
                'revocation_reason' => null,
                'secure_ticket_token' => $newToken,
                'secure_ticket_token_hash' => $newHash,
                'token_version' => $nextVersion,
                'token_reissued_at' => now(),
            ])->save();

            $this->rawTicketToken = $newToken;
            $this->forceFill([
                'token_revoked_at' => null,
                'revocation_reason' => null,
                'secure_ticket_token' => $newToken,
                'secure_ticket_token_hash' => $newHash,
                'token_version' => $locked->token_version,
                'token_reissued_at' => $locked->token_reissued_at,
            ]);

            \App\Services\AuditLogService::log(
                action: 'ticket_token_reissued',
                targetEntity: 'orders',
                targetId: (string) $locked->id,
                details: "Ticket {$locked->ticket_code} (Order #{$locked->order_number}) QR token reissued (v{$nextVersion}). Old token invalidated. Reason: {$reason}",
                actor: $actor,
                organizerId: (string) $locked->organizer_id,
                eventId: (string) $locked->event_id
            );

            \App\Services\Notifications\NotificationService::notifyOrder(
                $locked->fresh() ?? $locked,
                'ticket_reissued',
                ['version' => $nextVersion],
                'v' . $nextVersion
            );

            return $newToken;
        });
    }

    /**
     * Immediate server-side revocation of a ticket's QR token.
     */
    public function revokeTicketToken(?User $actor = null, string $reason = 'Administrative revocation'): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $reason) {
            $locked = self::withoutGlobalScopes()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $nextVersion = max(1, (int) $locked->token_version) + 1;
            $revokedSentinelHash = hash('sha256', 'revoked_' . bin2hex(random_bytes(32)));

            $locked->forceFill([
                'token_revoked_at' => now(),
                'revocation_reason' => mb_substr(trim($reason) ?: 'Administrative revocation', 0, 255),
                'secure_ticket_token' => 'sha256:' . $revokedSentinelHash,
                'secure_ticket_token_hash' => $revokedSentinelHash,
                'token_version' => $nextVersion,
            ])->save();

            $this->rawTicketToken = null;
            $this->forceFill([
                'token_revoked_at' => $locked->token_revoked_at,
                'revocation_reason' => $locked->revocation_reason,
                'secure_ticket_token' => 'sha256:' . $revokedSentinelHash,
                'secure_ticket_token_hash' => $locked->secure_ticket_token_hash,
                'token_version' => $locked->token_version,
            ]);

            \App\Services\AuditLogService::log(
                action: 'ticket_token_revoked',
                targetEntity: 'orders',
                targetId: (string) $locked->id,
                details: "Ticket {$locked->ticket_code} (Order #{$locked->order_number}) QR token immediately revoked on server. Reason: {$locked->revocation_reason}",
                actor: $actor,
                organizerId: (string) $locked->organizer_id,
                eventId: (string) $locked->event_id
            );

            \App\Services\Notifications\NotificationService::notifyOrder(
                $locked->fresh() ?? $locked,
                'ticket_revoked',
                ['reason' => $locked->revocation_reason],
                'v' . $nextVersion
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function cashPayments(): HasMany
    {
        return $this->hasMany(CashPayment::class, 'order_id');
    }

    public function handoverLogs(): HasMany
    {
        return $this->hasMany(HandoverAuditLog::class, 'order_id');
    }

    /**
     * Check if this order is verified and ready for food handover.
     */
    public function isEligibleForHandover(): bool
    {
        if ($this->isTicketRevoked()) {
            return false;
        }

        return in_array($this->status, ['paid', 'confirmed', 'partially_refunded'], true) || $this->authorized_unpaid === true;
    }

    public function getPickupNumberAttribute(): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->ticket_code ?: $this->order_number ?: $this->id));
        if (strlen($digits) >= 3) {
            return 'PU-' . substr($digits, -3);
        }

        return 'PU-' . (100 + (abs(crc32((string) $this->id)) % 900));
    }


    /** @return array<string, string> */
    protected function minorUnitPairs(): array
    {
        // packaging_fee is the legacy name for all fees on the order (host and platform), kept for older readers.
        return ['subtotal' => 'subtotal_minor', 'packaging_fee' => 'fees_minor', 'total_amount' => 'total_minor', 'amount_paid' => 'amount_paid_minor'];
    }

    protected function minorUnitCurrency(): string
    {
        return (string) ($this->currency ?: \App\Support\Currency::defaultCode());
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /** What is still owed: total less what has been received. Never negative. */
    public function balanceMinor(): int
    {
        return max(0, (int) $this->total_minor - (int) $this->amount_paid_minor);
    }

    /**
     * Add a ticket restriction to this order.
     *
     * @param string $type Restriction type (e.g., 'time_window', 'max_uses', 'age_restriction')
     * @param mixed $value Restriction value
     * @return void
     */
    public function addTicketRestriction(string $type, mixed $value): void
    {
        $restrictions = $this->ticket_restrictions ?? [];
        $restrictions[] = [
            'type' => $type,
            'value' => $value,
            'added_at' => now()->toIso8601String(),
        ];
        $this->ticket_restrictions = $restrictions;
    }

    /**
     * Check if the ticket has a specific restriction type.
     */
    public function hasTicketRestriction(string $type): bool
    {
        if (empty($this->ticket_restrictions) || !is_array($this->ticket_restrictions)) {
            return false;
        }

        foreach ($this->ticket_restrictions as $restriction) {
            if (($restriction['type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a specific ticket restriction by type.
     */
    public function getTicketRestriction(string $type): mixed
    {
        if (empty($this->ticket_restrictions) || !is_array($this->ticket_restrictions)) {
            return null;
        }

        foreach ($this->ticket_restrictions as $restriction) {
            if (($restriction['type'] ?? '') === $type) {
                return $restriction['value'] ?? null;
            }
        }

        return null;
    }
}
