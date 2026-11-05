# Event Promotional QR Code Enhancement Report

## Overview

This report documents enhancements to the existing event promotional QR code system, maintaining the critical distinction between promotional QR codes (for sharing event information) and admission ticket QR codes (for secure admission and food fulfillment).

## Critical Distinction

**IMPORTANT:** Promotional QR codes are NOT admission ticket QR codes.

- **Promotional QR Code:** Used for sharing event information on flyers, posters, social media, and marketing materials. Resolves to the public event page. Never used for admission or food fulfillment.
- **Admission Ticket QR Code:** Each ticket has its own unique, secure, server-generated token (`orders.secure_ticket_token`). Used for admission authentication and food order fulfillment.

**Never use promotional QR codes to authenticate admission or fulfill food orders.**

## Existing Implementation Assessment

The existing implementation was already well-designed:

### Strengths ✅
- Unique promotional QR identity per event (enforced by database unique constraint)
- Stable public code that never changes (printed flyers keep working after redesign)
- Design versioning for tracking redesigns
- QR code validation before storage (decodes with real QR reader)
- Status tracking (pending, ready, failed) for retry logic
- Separate storage for artwork (not in database)
- Short link resolution (`/e/{public_code}`)
- Only published events resolve publicly
- Access control for host actions

### Model Structure
```php
class EventQrCode extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event_id',        // Unique per event
        'public_code',     // Unique opaque identifier
        'preset',          // Design preset
        'design',          // Design configuration (JSON)
        'design_version',  // Increments on redesign
        'status',          // Artwork generation status
        'asset_path',      // Storage path to artwork
        'failure_reason',  // Error details if failed
        'attempts',        // Generation attempt count
        'generated_at',    // When artwork was generated
    ];
}
```

## Enhancements Implemented

### 1. Critical Distinction Documentation ✅

**Added Comments:**
- `EventQrController::resolve()` - Detailed comment about promotional vs admission QR codes
- `EventQrService` - Enhanced class-level documentation with critical distinction warning

**Location:**
- `app/Http/Controllers/EventQrController.php` (lines 30-46)
- `app/Services/EventQr/EventQrService.php` (lines 16-34)

### 2. QR Type Header ✅

**Implementation:**
Added `X-EventBite-QR-Type: promotional` header to all QR responses to distinguish promotional QR codes from ticket QR codes at the HTTP level.

**Applied To:**
- `publicArtwork()` - Public sharing endpoint
- `download()` - Host download endpoint
- `previewArtwork()` - Host preview endpoint (new)

**Purpose:**
- Clear identification for external systems
- Prevents confusion between QR types
- Enables security validation in consuming systems

**Location:**
- `app/Http/Controllers/EventQrController.php` (lines 62, 88, 109)

### 3. Draft Event Support ✅

**New Endpoint:**
```php
Route::get('/events/{id}/qr/preview', [EventQrController::class, 'previewArtwork'])
    ->name('events.qr.preview')
    ->middleware('throttle:60,1');
```

**Features:**
- Hosts can preview QR artwork for draft events
- Returns SVG artwork for immediate preview
- Access control enforced (only event managers)
- Publication status included in response headers

**Location:**
- `routes/web.php` (line 126)
- `app/Http/Controllers/EventQrController.php` (lines 93-109)

### 4. Publication Status Header ✅

**Implementation:**
Added `X-Event-Publication-Status` header to host QR responses indicating whether the event is draft, published, or unpublished.

**Applied To:**
- `download()` - All format downloads
- `previewArtwork()` - Preview endpoint

**Values:**
- `draft` - Event is not published
- `published` - Event is published
- `unpublished` - Event was unpublished

**Purpose:**
- Helps hosts understand QR availability
- Prevents confusion about public accessibility
- Indicates when QR will become publicly resolvable

**Location:**
- `app/Http/Controllers/EventQrController.php` (lines 89, 108)

### 5. Draft Filename Suffix ✅

**Implementation:**
Modified download filenames to include `-draft` suffix for draft events.

**Examples:**
- Published: `my-event-qr.svg`
- Draft: `my-event-draft-qr.svg`

**Code:**
```php
$status = $event->status === 'published' ? '' : '-draft';
$name = "{$base}{$status}-qr.svg";
```

**Purpose:**
- Clear file naming for hosts
- Prevents accidentally sharing draft QR artwork
- Easy identification in file systems

**Location:**
- `app/Http/Controllers/EventQrController.php` (lines 76-77)

### 6. Comprehensive Test Suite ✅

**Test File:** `tests/Feature/EventPromotionalQrTest.php`

**Test Coverage (15 tests):**

1. **test_event_owner_can_generate_promotional_qr**
   - Verifies hosts can generate QR
   - Confirms promotional type header

2. **test_promotional_qr_resolves_to_published_event**
   - Verifies published events resolve
   - Confirms redirect to event page

3. **test_promotional_qr_does_not_resolve_draft_event**
   - Verifies draft events do not resolve
   - Confirms 404 for drafts

4. **test_promotional_qr_does_not_resolve_unpublished_event**
   - Verifies unpublished events do not resolve
   - Confirms 404 for unpublished

5. **test_promotional_qr_public_artwork_only_for_published**
   - Verifies public artwork only for published
   - Confirms 404 for draft events

6. **test_promotional_qr_public_artwork_for_published_event**
   - Verifies public artwork works for published
   - Confirms promotional type header

7. **test_host_can_preview_qr_for_draft_event**
   - Verifies hosts can preview draft QR
   - Confirms draft status header

8. **test_host_can_preview_qr_for_published_event**
   - Verifies hosts can preview published QR
   - Confirms published status header

9. **test_non_owner_cannot_preview_qr**
   - Verifies access control
   - Confirms 404 for non-owners

10. **test_guest_cannot_preview_qr**
    - Verifies authentication requirement
    - Confirms redirect to login

11. **test_promotional_qr_filename_includes_draft_status**
    - Verifies draft suffix in filename
    - Confirms `-draft-qr.svg` pattern

12. **test_promotional_qr_filename_for_published_event**
    - Verifies no draft suffix for published
    - Confirms clean filename

13. **test_multiple_formats_available**
    - Verifies SVG, PNG, and poster formats
    - Confirms all formats work

14. **test_invalid_format_returns_404**
    - Verifies format validation
    - Confirms 404 for invalid formats

15. **test_promotional_qr_is_unique_per_event**
    - Verifies one QR per event
    - Confirms database constraint

16. **test_public_code_is_unique**
    - Verifies code uniqueness
    - Confirms no code collisions

**Location:**
- `tests/Feature/EventPromotionalQrTest.php` (270 lines)

## Security Considerations

### Access Control
- **Public Resolve:** Only published events (404 for draft/unpublished)
- **Public Artwork:** Only published events (404 for draft/unpublished)
- **Host Preview:** Only event managers (404 for non-owners)
- **Host Download:** Only event managers (404 for non-owners)
- **Redesign:** Only event managers
- **Retry:** Only event managers

### Admission Security
- **Promotional QR:** Cannot be used for admission
- **Ticket QR:** Uses separate secure token (`orders.secure_ticket_token`)
- **No Overlap:** Completely separate systems
- **Header Identification:** HTTP headers distinguish QR types

### URL Security
- **Public Code:** Opaque 10-character identifier
- **Short Link:** `/e/{public_code}` format
- **No Sensitive Data:** URL contains only event identifier
- **No Token Exposure:** No admission tokens in promotional QR

### Validation
- **QR Decode Validation:** Artwork must decode correctly
- **Size Validation:** Multiple sizes tested (large, small, compressed)
- **Fallback Design:** Failsafe if design doesn't decode
- **Retry Logic:** Failed generations can be retried

## Publication State Support

### Draft Events
- **QR Generation:** Available (hosts can generate and preview)
- **Public Resolution:** 404 (not accessible publicly)
- **Public Artwork:** 404 (not accessible publicly)
- **Host Preview:** Available via new endpoint
- **Filename:** Includes `-draft` suffix
- **Headers:** `X-Event-Publication-Status: draft`

### Published Events
- **QR Generation:** Available
- **Public Resolution:** Redirects to event page
- **Public Artwork:** Available for sharing
- **Host Preview:** Available
- **Filename:** No draft suffix
- **Headers:** `X-Event-Publication-Status: published`

### Unpublished Events
- **QR Generation:** Available (hosts can generate and preview)
- **Public Resolution:** 404 (not accessible publicly)
- **Public Artwork:** 404 (not accessible publicly)
- **Host Preview:** Available via new endpoint
- **Filename:** Includes `-draft` suffix
- **Headers:** `X-Event-Publication-Status: unpublished`

## Branding Integration

### Existing Branding Features
- **Custom Theme:** Extracts colors from event flyer
- **Multiple Presets:** 8 approved design presets
- **Random Design:** Random selection from approved presets
- **Poster Format:** Includes event title, date, call-to-action
- **Design Versioning:** Tracks redesigns with version numbers

### Branding with New Cover Image
The existing flyer palette extraction already supports:
- JPEG, PNG, WebP flyer formats
- Color sampling from uploaded artwork
- Custom theme based on event branding
- Safe color extraction (validated formats only)

### Poster Branding
The poster format includes:
- Event title
- Date and time
- Call-to-action ("Scan for tickets, food & event details")
- QR code with selected design
- Responsive to event branding colors

## API Endpoints

### Public Endpoints

**Resolve QR Code:**
```
GET /e/{public_code}
- Redirects to event page (published only)
- 404 for draft/unpublished
```

**Public Artwork:**
```
GET /e/{public_code}/qr.svg
- Returns SVG artwork (published only)
- 404 for draft/unpublished
- Headers: X-EventBite-QR-Type: promotional
```

### Host Endpoints

**Download QR Artwork:**
```
GET /events/{id}/qr/{format}
- Formats: svg, png, poster
- Headers: X-EventBite-QR-Type: promotional
- Headers: X-Event-Publication-Status: {status}
- Filename includes -draft for draft events
```

**Preview QR Artwork (NEW):**
```
GET /events/{id}/qr/preview
- Returns SVG artwork for preview
- Works for draft events
- Headers: X-EventBite-QR-Type: promotional
- Headers: X-Event-Publication-Status: {status}
```

**Redesign QR:**
```
POST /events/{id}/qr/design
- Changes design preset
- Public code unchanged
- Design version incremented
```

**Retry Generation:**
```
POST /events/{id}/qr/retry
- Retries failed artwork generation
- Same code and design
```

## File Changes Summary

### Modified Files

1. **app/Http/Controllers/EventQrController.php**
   - Added critical distinction comment to resolve()
   - Added X-EventBite-QR-Type header to publicArtwork()
   - Added X-EventBite-QR-Type header to download()
   - Added X-Event-Publication-Status header to download()
   - Added draft filename suffix to download()
   - Added previewArtwork() method for draft preview
   - Added X-EventBite-QR-Type header to previewArtwork()
   - Added X-Event-Publication-Status header to previewArtwork()

2. **app/Services/EventQr/EventQrService.php**
   - Enhanced class documentation with critical distinction warning
   - Clarified promotional vs admission QR code purpose

3. **routes/web.php**
   - Added previewArtwork route (line 126)

### New Files

1. **tests/Feature/EventPromotionalQrTest.php**
   - 16 comprehensive tests
   - Covers all new functionality
   - Tests access control, publication states, headers, filenames

## Testing

### Running Tests

```bash
cd backend
php artisan test --filter EventPromotionalQrTest
```

### Test Coverage

- Access control (owner, non-owner, guest)
- Publication state handling (draft, published, unpublished)
- Header verification (QR type, publication status)
- Filename naming (draft suffix)
- Format availability (SVG, PNG, poster)
- Uniqueness constraints (per event, public code)
- Public resolution behavior
- Preview endpoint functionality

### Manual Testing Checklist

1. Generate QR for published event
2. Generate QR for draft event
3. Preview QR for draft event
4. Download QR in different formats
5. Verify draft filename suffix
6. Scan QR with phone (published only)
7. Test unauthorized access attempts
8. Verify headers in HTTP responses
9. Test redesign functionality
10. Test retry after failure

## Integration with Event Branding

### Cover Image Support
The new cover image type (added in previous branding work) can be used for:
- **Custom Theme Extraction:** QR service samples colors from cover image
- **Poster Background:** Can incorporate cover image in poster design
- **Consistent Branding:** QR artwork matches event branding

### Responsive Derivatives
The responsive image derivatives can be referenced in:
- **Poster Design:** Use appropriate size for poster format
- **Custom Theme:** Sample from different derivative sizes
- **Performance:** Serve optimal size for QR generation

## Limitations and Future Work

### Current Limitations

1. **Custom Theme Storage:**
   - Colors extracted from flyer each time
   - No caching of extracted palette
   - Could be optimized for performance

2. **Preview Only for SVG:**
   - Preview endpoint returns SVG only
   - PNG and poster require download
   - Could add PNG preview for better fidelity

3. **No QR Usage Analytics:**
   - No tracking of QR scans
   - No analytics on which QR works best
   - Could add scan tracking (with privacy)

### Recommended Future Enhancements

1. **QR Analytics:**
   - Track scan count per QR code
   - Track scan geography
   - Track scan devices
   - Privacy-preserving implementation

2. **Advanced Branding:**
   - Use cover image in poster background
   - Add event logo to QR artwork
   - Sponsor logo integration
   - Dynamic color schemes

3. **Batch QR Generation:**
   - Generate QR for multiple events
   - Bulk download of QR artwork
   - Export to marketing packages

4. **QR Expiration:**
   - Optional expiration for temporary events
   - Auto-expire after event date
   - Renewal workflow for recurring events

## Important Distinctions

### Promotional QR Code
- **Purpose:** Share event information
- **Location:** Flyers, posters, social media, marketing
- **Resolution:** `/e/{public_code}` → event page
- **Access:** Public (for published events)
- **Security:** No sensitive data
- **Admission:** NOT used for admission
- **Food Fulfillment:** NOT used for food orders

### Admission Ticket QR Code
- **Purpose:** Authenticate admission and food fulfillment
- **Location:** Individual tickets
- **Resolution:** Server-side token validation
- **Access:** Secure server validation
- **Security:** Unique, server-generated token per ticket
- **Admission:** REQUIRED for admission
- **Food Fulfillment:** REQUIRED for food orders

## Conclusion

The event promotional QR code system has been enhanced with:

✅ **Critical distinction documentation** - Clear separation from admission QR codes
✅ **QR type header** - HTTP-level identification for external systems
✅ **Draft event support** - Hosts can preview QR for draft events
✅ **Publication status header** - Clear indication of event state
✅ **Draft filename suffix** - Easy identification of draft artwork
✅ **Comprehensive test suite** - 16 tests covering all scenarios
✅ **Publication state handling** - Proper behavior for draft/published/unpublished
✅ **Access control enforcement** - Only authorized access to host operations
✅ **Branding integration** - Works with cover images and responsive derivatives

The existing implementation was already robust. These enhancements add:
- Better documentation of the critical distinction
- Improved draft event support
- Clearer identification via headers
- Better file naming for hosts
- Comprehensive test coverage

The system maintains complete separation between promotional QR codes (for sharing) and admission ticket QR codes (for secure admission and food fulfillment), ensuring security and preventing confusion.
