# EventBite QR Code System Documentation

## Overview

EventBite implements a dual QR code system for events and tickets:

1. **Event QR Codes** - Promotional codes that resolve to public event pages
2. **Ticket QR Codes** - Secure, unique codes for individual ticket verification

## Event QR Codes

### Purpose

Event QR codes are for promotion and discovery:
- Flyers and posters
- Social media sharing
- Vendor materials
- Printed event information
- Event marketing

**Important**: Event QR codes must NEVER be treated as proof of ticket purchase. They only direct users to the event page.

### Structure

Each event has exactly one event QR code with:
- **public_code**: A 16-character opaque identifier (e.g., "ABC123XYZ456")
- **URL format**: `/e/{public_code}` (e.g., `/e/ABC123XYZ456`)
- **Stability**: The public_code never changes, ensuring printed materials remain valid

### Database Schema

```php
Schema::create('event_qr_codes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('event_id')->unique();           // One QR per event
    $table->string('public_code', 16)->unique();  // Opaque stable identifier
    $table->string('preset', 32)->nullable();      // Design preset name
    $table->json('design')->nullable();            // Visual configuration
    $table->unsignedInteger('design_version')->default(0);
    $table->string('status', 16)->default('pending'); // pending, ready, failed
    $table->string('asset_path')->nullable();     // Storage path for artwork
    $table->string('failure_reason', 255)->nullable();
    $table->unsignedInteger('attempts')->default(0);
    $table->timestamp('generated_at')->nullable();
    $table->timestamps();
});
```

### Visual Design System

Event QR codes use approved visual design variations from `QrDesignCatalog`:

#### Design Presets

1. **Geometric** - Modern geometric patterns with EventBite branding
2. **Circular** - Circular module shapes for a softer look
3. **Neon** - High-contrast neon colors for nightlife events
4. **Premium Minimal** - Clean, minimal black-on-white design
5. **Festival** - Vibrant festival-style colors and patterns
6. **Editorial** - Editorial magazine-style design
7. **Custom Event Theme** - Colors extracted from event flyer
8. **Random Combination** - Mix of approved design elements

#### Design Rules Enforced

All designs must pass these scanning reliability rules:

1. **Normal Polarity**: Modules are darker than the background (no inverted codes)
2. **Minimum Contrast**: 7:1 contrast ratio between modules and background
3. **Error Correction**:
   - Dot-shaped modules require Q or H error correction
   - Center logos require H error correction
4. **Whitelisted Elements**: Only approved module shapes, finder shapes, and color pairs

#### Color Palette

Brand colors:
- Navy: `#07111F`
- Ink: `#0B0F14`
- Lime: `#C7FF45`
- Cyan: `#7FF0F7`
- Coral: `#FF6B5A`

### API Endpoints

#### Resolve Event QR Code
```
GET /e/{public_code}
```
Redirects to the public event page. Only published events resolve.

#### Public Artwork
```
GET /qr/artwork/{public_code}
```
Returns SVG artwork for a published event (for sharing).

#### Download QR Artwork (Host Only)
```
GET /host/events/{id}/qr/{format}?inline=true
```
Formats:
- `svg` - Vector format for digital use
- `png` - Print-resolution raster (24x scale)
- `poster` - Poster format with event details

#### Redesign QR Code (Host Only)
```
POST /host/events/{id}/qr/redesign
```
Parameters:
- `preset` - Design preset name or "random"

#### Retry Generation (Host Only)
```
POST /host/events/{id}/qr/retry
```
Retries artwork generation after a failure.

## Ticket QR Codes

### Purpose

Ticket QR codes are for secure admission verification:
- Individual ticket validation
- Check-in at event entry
- Proof of purchase verification
- Preventing duplicate admission

### Structure

Each ticket has a unique secure token:
- **secure_ticket_token**: 37-character cryptographically random token (e.g., `tok_` + 32 random chars)
- **Format**: `tok_{32_random_characters}`
- **Uniqueness**: Generated using Laravel's `Str::random(32)` with high entropy
- **Storage**: Stored in `orders.secure_ticket_token`

### Security Properties

1. **Cryptographically Random**: Uses cryptographically secure random generation
2. **Unguessable**: 32 characters of random entropy (192 bits)
3. **Unique per Ticket**: No two tickets share the same token
4. **Server-Side Validation**: All verification happens server-side
5. **No Sensitive Data**: QR payload contains only the token, not payment details or PII

### Database Schema

The secure token is stored in the `orders` table:

```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('secure_ticket_token', 255)->unique();
});
```

### Ticket Verification Process

The `TicketVerificationService` enforces all validation requirements:

#### Verification Checks

1. **Ticket Identity**
   - Validates ticket_code and order_number exist
   - Confirms the order is legitimate

2. **Event Identity**
   - Verifies ticket belongs to the correct event
   - Prevents cross-event ticket use

3. **Payment or Authorized Admission State**
   - Status must be: `paid`, `confirmed`, or `authorized_unpaid`
   - Rejected statuses: `refunded`, `rejected`, `cancelled`, `expired`

4. **Revocation Status**
   - Checks if ticket has been revoked
   - Validates current status allows admission

5. **Prior Admission (Duplicate Redemption Prevention)**
   - Checks `checked_in_at` timestamp
   - Prevents concurrent redemption using row locking
   - Atomic operation to prevent race conditions

6. **Ticket Restrictions**
   - Time window restrictions
   - Maximum use limits
   - Age restrictions (future implementation)
   - Custom restrictions (JSON field)

#### Row Locking for Concurrency

Verification uses database row locking to prevent concurrent redemption:

```php
return DB::transaction(function () use ($order) {
    $locked = Order::query()->withoutGlobalScopes()
        ->where('id', $order->id)
        ->lockForUpdate()
        ->first();

    // Verification logic here
});
```

This ensures two scanners cannot both admit the same ticket simultaneously.

### API Endpoints

#### Scan/Verify Ticket
```
POST /worker/events/{event}/scan-ticket
```
Parameters:
- `ticket_code` - Secure token (tok_...), ticket code, or order number

Returns:
```json
{
  "success": true,
  "valid": true,
  "already_admitted": false,
  "rejection_reason": null,
  "ticket": {
    "order_number": "ORD-2026-123456",
    "ticket_code": "SFF-ABC12345",
    "customer_name": "John Doe",
    "status": "paid",
    "checked_in_at": "2026-10-31 14:30:00"
  }
}
```

Note: Payment details and sensitive PII are excluded from the response.

### Ticket Restrictions

Tickets can have custom restrictions stored in JSON format:

```php
$ticket_restrictions = [
    [
        'type' => 'time_window',
        'value' => [
            'start' => '2026-10-31 10:00:00',
            'end' => '2026-10-31 22:00:00'
        ],
        'added_at' => '2026-10-30T15:00:00Z'
    ],
    [
        'type' => 'max_uses',
        'value' => 1,
        'added_at' => '2026-10-30T15:00:00Z'
    ]
];
```

#### Supported Restriction Types

1. **time_window** - Admission only valid within specific time range
2. **max_uses** - Maximum number of times ticket can be used
3. **age_restriction** - Minimum age requirement (future)

#### Model Helper Methods

```php
// Add a restriction
$order->addTicketRestriction('time_window', [
    'start' => '2026-10-31 10:00:00',
    'end' => '2026-10-31 22:00:00'
]);

// Check if restriction exists
if ($order->hasTicketRestriction('time_window')) {
    // ...
}

// Get restriction value
$window = $order->getTicketRestriction('time_window');
```

## Security Best Practices

### Event QR Codes

✅ **DO**:
- Use event QR codes for promotion and marketing
- Print on flyers, posters, and vendor materials
- Share on social media
- Keep the public_code stable (never change it)

❌ **DO NOT**:
- Use event QR codes as proof of ticket purchase
- Expect event QR codes to admit anyone
- Embed sensitive information in event QR payloads
- Change the public_code after printing materials

### Ticket QR Codes

✅ **DO**:
- Generate cryptographically random tokens
- Validate all tickets server-side
- Use row locking to prevent concurrent redemption
- Sanitize ticket data before display (no payment details)
- Log all verification attempts in audit trail

❌ **DO NOT**:
- Expose payment details in QR payloads
- Expose sensitive personal information in QR payloads
- Allow client-side only validation
- Reuse ticket tokens across orders
- Guess or predict token values

## Audit Trail

All ticket verification attempts are logged in the security audit log:

```php
AuditLogService::log(
    action: 'ticket_redemption_verified', // or 'ticket_redemption_rejected'
    targetEntity: 'orders',
    targetId: (string) $order->id,
    details: "Ticket {$order->ticket_code} redemption details...",
    actor: $verifier
);
```

Audit logs include:
- Action performed (verified or rejected)
- Target order ID
- Verification details
- Performing user
- Timestamp

## Worker Permissions

Different worker roles have different verification capabilities:

### Ticket Checker
- Can scan QR tickets (secure tokens)
- Cannot manually admit by typed code (requires supervisor)
- Sees sanitized ticket data (no payment details)

### Event Supervisor
- Can scan QR tickets
- Can manually admit by typed code or order number
- Sees full ticket details

### System Administrator
- All verification capabilities
- Full access to ticket data

## Error Handling

### Event QR Code Errors

| Error | Cause | Resolution |
|-------|-------|------------|
| 404 Not Found | Event not published or deleted | Publish event or create new QR |
| 409 Conflict | Artwork generation failed | Retry generation or use different preset |

### Ticket Verification Errors

| Error | Cause | Resolution |
|-------|-------|------------|
| 404 Not Found | No matching ticket for event | Verify correct event and code |
| 403 Forbidden | Unauthorized worker | Check worker assignment and permissions |
| Rejection: Invalid status | Ticket not paid/confirmed | Customer must complete payment |
| Rejection: Already admitted | Duplicate redemption attempt | Ticket already used |
| Rejection: Event mismatch | Wrong event | Verify correct event |
| Rejection: Revoked | Ticket refunded/cancelled | Contact support |

## Implementation Notes

### Generating Secure Tokens

```php
use App\Services\TicketVerificationService;

$token = TicketVerificationService::generateSecureToken();
// Returns: tok_abc123xyz456... (37 characters)
```

### Validating Secure Tokens

```php
$service = new TicketVerificationService();

if ($service->isValidSecureToken($token)) {
    // Token is properly formatted
}
```

### Verifying Tickets

```php
use App\Services\TicketVerificationService;

$service = new TicketVerificationService();

$result = $service->verifyTicket(
    identifier: 'tok_abc123xyz456...',
    event: $event,
    verifier: $user,
    manual: false
);

if ($result['valid']) {
    // Ticket is valid, admit the guest
} else {
    // Show rejection reason
    echo $result['rejection_reason'];
}
```

## Testing

### Unit Tests

Test ticket verification logic:
- Valid tickets pass all checks
- Invalid status tickets are rejected
- Already admitted tickets are rejected
- Wrong event tickets are rejected
- Restrictions are enforced
- Concurrent redemption is prevented

### Integration Tests

Test full verification flow:
- QR scan with secure token
- Manual verification with ticket code
- Row locking prevents race conditions
- Audit logs are created
- Worker permissions are enforced

## Migration Run

To apply the ticket restrictions migration:

```bash
cd backend
php artisan migrate
```

This adds the `ticket_restrictions` JSON field to the `orders` table.
