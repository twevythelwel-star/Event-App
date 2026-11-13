# EventBite Ticket QR Design System

**Date:** 2026-11-13
**Branch:** `claude/busy-feynman-15b28d`
**Scope:** How attendee ticket QR codes look, who chooses the look, and the rules that keep every look scannable.

Related: [security](eventbite-ticket-qr-security.md) ·
[generation workflow](eventbite-ticket-generation-workflow.md) ·
[scanner integration](eventbite-ticket-scanner-integration.md) ·
[test report](eventbite-ticket-qr-test-report.md)

---

## 1. One design system, two uses

EventBite already had a design system for the **promotional event QR** (`QrDesignCatalog` + `QrArtworkRenderer`,
see `EVENT_PROMOTIONAL_QR_CODE_REPORT.md`). Ticket QR codes reuse it rather than building a parallel system:

| Layer | Class | Role |
|---|---|---|
| Schema and rules | `App\Services\EventQr\QrDesignCatalog` | The design array, whitelists of shapes, colour maths (WCAG luminance and contrast), `problemWith()` |
| Drawing | `App\Services\EventQr\QrArtworkRenderer` | One shape list rendered to SVG (ticket page) or PNG (decode checks); the QR matrix comes from `chillerlan/php-qrcode` and is never altered |
| Ticket presets and controls | `App\Services\EventQr\TicketQrDesignSystem` | Event-themed presets, host choice, administrator controls, readability report |
| Ticket artwork | `App\Services\EventQr\TicketQrService` | Renders each attendee's code in the event design after a decode check |

Visual styling never touches credentials. The renderer receives an opaque string to encode; it does not know
what a ticket is (see the security document).

Additions to the shared layers for tickets:

- **Leaf** finder style: three nested squares of standard proportions with two opposite corners rounded.
- **Monogram** centre mark: the event's initials (one to three capitals or digits) on a badge.
- `QrArtworkRenderer::QUIET_ZONE` is now public so the readability report can state it.
- Promotional random designs keep exactly their previous ingredients; the new styles are ticket-only.

## 2. Approved presets

Each preset whitelists its options; the first entry of each list is the default. Hosts can only choose from these
lists. Colours: **primary** = modules, **secondary** = corner markers, **background** = the plate behind the code,
**frame** and **border** sit outside the quiet zone.

| Key | Label | Primary | Secondary | Background | Frame | Modules | Corner markers | Centre mark | Matches theme |
|---|---|---|---|---|---|---|---|---|---|
| `eventbite_classic` | EventBite Classic | `#07111F` navy | `#1E2B4A` | `#FFFFFF` | navy, lime brackets | rounded, square, circle | rounded, square, circle | none, EventBite mark, initials | classic |
| `caribbean_festival` | Caribbean Festival | `#0B0F0C` black | `#00552A` green | `#FFFBEA` cream | `#FED100` gold, zigzag | rounded, circle, square | leaf, rounded, circle | none, initials, EventBite mark | island |
| `music_festival` | Music Festival | `#14061F` | `#4B1585` purple | `#FFFFFF` | `#120A24` dark, lime double border | circle, rounded | circle, rounded | none, EventBite mark, initials | stage |
| `food_festival` | Food Festival | `#7A1408` chilli red | `#8C2A00` | `#FFF6E5` cream | `#FF7A1A` orange, dashed | rounded, circle | rounded, leaf | none, EventBite mark, initials | feast |
| `corporate` | Corporate | `#0B1F3A` navy | `#1C3A63` | `#FFFFFF` | `#C9CED6` silver | square, rounded | square, rounded | none, initials | professional |
| `wedding` | Wedding | `#2E2A26` charcoal | `#5B4636` taupe | `#FFFDF7` ivory | `#EFE6D6` champagne, double border | circle, rounded | circle, leaf, rounded | none, initials | celebration |
| `cultural_celebration` | Cultural Celebration | host's choice | host's choice | host's choice | host's choice | rounded, circle, square | rounded, leaf, circle, square | none, initials, EventBite mark | community |

"Matches theme" connects to `App\Support\TicketTheme`, which already chooses a ticket theme from the kind of event
the host declared. A host who never opens the QR tab gets the preset of their event's theme ("Match my event").

## 3. How a ticket's design is decided

`TicketQrDesignSystem::design($event)` → `resolve($event, $choice)`:

1. If administrators turned themed codes off, return the **conventional design** (black squares on white,
   standard corner markers, error correction Q).
2. Preset = the host's choice if approved; otherwise the administrators' default; `auto` = the event theme's preset;
   otherwise the first approved preset.
3. Every option (shape, corner style, border, centre mark) is taken from the preset's whitelist, else its default.
   Centre marks become `none` when administrators disallow them. Custom colours are used only with Cultural
   Celebration and only when administrators allow custom palettes.
4. Error correction: **Q**, or **H** whenever a centre mark is drawn.
5. The design is checked against every rule (section 5). Any failure returns the conventional design with a
   `fallback_reason`. The ticket page and the host screen say when this happened.

The result is deterministic, so its fingerprint (`TicketQrDesignSystem::fingerprint()`) identifies it. Tickets
re-render only when their event's fingerprint changes; their credentials never change because of a design.

## 4. Who controls what

### Hosts: ticketing studio, "QR design" tab

Route `POST /events/{id}/ticketing/qr-design` (`events.ticketing.qr-design`, `TicketingStudioController::saveQrDesign`).
Only hosts who manage the event's organizer reach it (the event is looked up through `EventManagementService::findEvent`,
404 for anyone else).

The tab shows a live preview of every approved preset (rendered with a sample credential, never a real one), the
shape and centre-mark choices, the Cultural Celebration colour fields, and the readability report for the design in
use. A choice that breaks a rule is **refused with the reason** (`TicketQrDesignSystem::choiceProblem()`), not
silently replaced. The choice is stored in `events.ticket_qr_design` (JSON) and audited as `ticket_qr_design_changed`.

### Administrators: console, "QR Codes"

Route `POST /admin/console/qr-codes/ticket-controls` (`admin.console.qr-codes.ticket-controls`), gate
`admin.settings.manage`. Stored in `system_platform_settings.ticket_qr_controls` (JSON), audited as
`ticket_qr_controls_changed`.

| Control | Default | Effect |
|---|---|---|
| Themed ticket QR codes | on | Off: every ticket uses the conventional design |
| Designs hosts may choose | all seven | A host's preset outside the list falls back to the default |
| Default design | Match the kind of event | Used when the host chose nothing or a removed preset |
| Custom colours | allowed | Off: Cultural Celebration uses its own colours |
| Centre marks | allowed | Off: no centre mark on any ticket |
| Minimum contrast | 7:1 | Can be raised (up to 21:1); a request below 7:1 is refused |

The readability rules themselves cannot be switched off by any control. The console page shows each preset's preview
under the current controls and flags presets that would fall back (for example, Wedding's 8.7:1 corner markers under
a 10:1 floor).

The same page also hosts the existing promotional-QR settings. Its view had been missing (the route would have
failed with a missing view); it now exists and the page is in the console navigation.

## 5. Readability rules

`TicketQrDesignSystem::readability($design, $data)` returns each check with its evidence. All must pass.

| Check | Rule |
|---|---|
| Polarity | Modules darker than the plate. Light-on-dark codes are not read by every scanner. |
| Contrast | Modules and corner markers at least 7:1 against the plate (WCAG relative luminance), or the administrators' higher floor |
| Quiet zone | 4 modules in the plate colour on every side. Frame, border and label are drawn outside it. |
| Error correction | Q or H; H whenever a centre mark is drawn |
| Finder patterns | All three drawn whole in an approved shape; never covered or omitted |
| Design rules | Approved shapes, borders, centre marks and colours only (`QrDesignCatalog::problemWith`) |
| Centre mark | Only inside the area the encoder clears (`addLogoSpace`), at most 6% of the symbol, clear of the finder patterns |
| Resolution | At least 3 screen pixels per module at the 260 px display size |
| Decode | A real QR decoder (chillerlan) reads back the exact credential at print size (8 px/module), small size (4 px/module) and after JPEG compression at quality 60 |

Never done, by construction: distorting or stretching the code, removing or restyling the finder patterns beyond
the three approved shapes, drawing in the quiet zone, or placing photographs or flyers on the code. Event artwork
appears elsewhere on the ticket, never on the QR.

## 6. Rendering

- **Ticket page:** inline SVG per guest, sized by CSS (`.eb-attendee-qr-art`), with an accessible name
  ("QR code for ticket SFF-…-02, guest 2 of 3"). The SVG carries its own white plate and frame; the page draws
  nothing over it.
- **Decode checks:** the same shape list painted with GD as PNG, so what is checked is what is shown. (GD has no
  reliable font, so the PNG omits label text, which is outside the quiet zone anyway.)
- **Certification cache:** each event design is decode-certified once with a credential of the real length; only a
  pass is cached (30 days), so a transient failure is checked again.
- **Motion:** each guest's code fades up in turn, only under `prefers-reduced-motion: no-preference`. There is no
  hidden start state outside the animation, so the code is fully visible with motion off or unsupported.

## 7. Visual reference

Rendered from the presets with a sample credential (4 px per module, PNG):

- Row 1: EventBite Classic, Caribbean Festival (leaf corners, zigzag gold frame), Music Festival (circle corners,
  dark frame, lime double border), Food Festival (dashed orange frame)
- Row 2: Corporate (silver frame, squares), Wedding (ivory and champagne, circles), Cultural Celebration defaults,
  Caribbean Festival with the EventBite centre mark

All of them pass every check in section 5. See the test report for the automated evidence.
