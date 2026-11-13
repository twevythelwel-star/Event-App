# Wallet passes: one architecture for Apple, Google and Samsung

EventBite tickets can go into Apple Wallet, Google Wallet and Samsung Wallet through one provider architecture. One
manager decides; each provider adapter only talks to its provider.

| Provider | State | Details |
| :--- | :--- | :--- |
| Apple Wallet | Built and tested with throwaway certificates; real-device test pending | [eventbite-apple-wallet.md](eventbite-apple-wallet.md) |
| Google Wallet | Adapter built and tested against a faked Google Wallet API; needs an issuer account, publishing access and a device test | this document |
| Samsung Wallet | Adapter built to Samsung's documentation and tested with stand-in keys. **Partner approval pending; hidden.** | [eventbite-samsung-wallet.md](eventbite-samsung-wallet.md) |

## Pieces

| Responsibility | Class |
| :--- | :--- |
| Provider contract | `App\Services\Wallet\Contracts\WalletProvider` (`configured`, `available`, `problems`, `save`, `update`, `revoke`, `confirmSaved`) |
| Apple adapter | `AppleWalletService`, built on the existing `AppleWalletConfig`, `AppleWalletPassBuilder` (pkpass/pkpass), `AppleWalletPackageValidator`, `AppleWalletPushService`, the PassKit web service and `WalletPassService` |
| Google adapter | `GoogleWalletService`, `GoogleWalletConfig` |
| Samsung adapter | `SamsungWalletService`, `SamsungWalletConfig`, `SamsungWalletPartnerController` |
| Eligibility | `WalletEligibilityService`: only the holder, only while the ticket is valid for entry |
| Central decision | `WalletPassManager::decide()` returning a `WalletDecision` |
| What a pass shows | `WalletTicketState` (status, barcode yes/no, void) and `WalletTicketContent` (fields and fingerprints), shared by all providers |
| The holder's click | `TicketWalletController` (`GET /tickets/{order}/wallet.pkpass`, `GET /tickets/{order}/wallet/{google\|samsung}`) |
| Queued provider work | `SyncWalletPass`, `ConfirmWalletPassSaved`, and Apple's `PushWalletPassUpdate` |
| Errors | `Exceptions\WalletProviderException` and one subclass per provider |
| Signing and encryption | `Jose` (RS256 JWS, RSA1_5 + A128GCM JWE) on PHP's OpenSSL |
| Configuration and status | `config/wallet.php`, `php artisan wallet:status`, `php artisan wallet:check` (Apple certificate), admin page `/admin/console/wallets` |
| Operation history | `wallet_pass_events` (`WalletPassEvent`): create, update, revoke, saved, removed, confirm, with outcome and error code |
| Pass records | `wallet_passes` (one table; `provider`, `status`, `external_id`, `saved_at`, `revoked_at`, sync fingerprints, `last_error`, `provider_meta`) |

## The decision

`WalletPassManager::decide($provider, $order, $pass, $user)` answers each question from the record as it is now:

| Question | How |
| :--- | :--- |
| Is the ticket eligible? | `WalletEligibilityService`: signed in, the holder, paid, not revoked, cancelled, refunded, used or past |
| Is the provider configured? | `WalletProvider::configured()`: switched on and every credential present and readable |
| Is it available? | `WalletProvider::available()`: configured and every provider gate passed (Google publishing access, Samsung partner approval and card launch, our own device test) |
| Has the pass been created? | a `wallet_passes` row for the provider |
| Was it saved? | `saved_at`, set only on the provider's confirmation or a delivered, validated pass file |
| Does it need updating? | the pass's `sync_fingerprint` differs from a hash of what it shows now |
| Has the ticket been revoked? | `WalletTicketState`: revoked, refunded, cancelled or transferred |
| Has the event changed? | `synced_event_hash` differs from the event's current hash |
| Has the ticket been reissued? | `synced_token_version` differs from the order's `token_version` |

and gives one action: `unavailable`, `ineligible`, `offer`, `up_to_date`, `update` or `revoke`.

## Buttons

`WalletPassManager::offers()` lists the buttons on the ticket page. A provider gets a button only when it is
**available** and the ticket is eligible for this holder. A configured provider that is still waiting for approval or
our device test is offered only to the accounts in `WALLET_TESTER_EMAILS`, labelled "(test)". There is no
placeholder, disabled or "coming soon" button and no link that would not work. While Google or Samsung is not
configured, `/tickets/{order}/wallet/{provider}` answers 404.

## Customer flow on the ticket page

Buy a ticket → confirmation → **My tickets** → the ticket page (the full collectible EventBite ticket with its QR) →
**Save to your phone** (`resources/views/components/wallet-actions.blade.php`) → pick an available wallet → save →
back on the EventBite ticket with a message:

| Moment | What the customer sees |
| :--- | :--- |
| Apple | The signed `.pkpass` downloads and opens in Wallet |
| Google / Samsung, on return from the provider's page | "Your ticket was sent to Google Wallet. Finish saving it there…" (flash) and a *pending* line |
| The provider confirms | "Saved in your Google Wallet. Changes to this ticket reach it automatically." |
| The provider could not be reached | "We couldn’t reach Google Wallet just now. Please try again in a minute." and the ticket stays as it is |
| A later update keeps failing | "We couldn’t update your … pass just now and will keep trying. This EventBite ticket always works at the gate." |
| Ticket not eligible (unpaid, used, void, past) | No wallet buttons; a refusal message with the reason if the link is opened directly |

Customers without a supported wallet use the EventBite ticket itself: the same page, **Print ticket**, and
**My tickets**; the wallet section says so. Saving a pass never changes payment or admission: it touches only
`wallet_passes` and `wallet_pass_events` (tested in `WalletCustomerFlowTest`).

### Event-day offline copy

For poor signal at the venue, `public/ticket-offline-sw.js` (a service worker registered by
`public/js/ticket-collection.js`, scope `/tickets/`) keeps a copy of each ticket page the holder opens, together with
the styles, scripts and images it uses. It works network first: with a connection the page always comes fresh from the
server. It serves the copy only when the network fails, and then with the notice "Saved copy · you’re offline" and when
it was saved. The notice also says that the page doesn’t confirm admission: only the gate scan, checked live with the
server, does, and the gate refuses a replaced, revoked, transferred or used QR whatever the copy shows. The worker
handles nothing else: no other pages, no forms, no wallet downloads. A copy is dropped when the server stops showing
that ticket to the browser (401, 403, 404, 410 or a redirect), and signing out sends
`Clear-Site-Data: "cache", "storage"`, which removes all copies and the worker. Service workers need HTTPS (or
localhost). Tested in `TicketOfflineAndAccessibilityTest`.

### Mobile layout and accessibility

The ticket page was checked at 320, 360, 375, 390 and 430 px, tablet (768 px) and desktop (1280 px). It has no
horizontal scroll and no clipped content, and the QR code stays at 185–220 px on a plain white quiet zone. Wallet
buttons are at least 44 px high. Status lines use the high-contrast `--color-success-text` and `--color-warning-text`
tokens. Illustrations below the fold load lazily, and motion follows `prefers-reduced-motion` (global rules in the
layout and `animations.css`).

The layout's viewport meta has no `viewport-fit=cover`, so phones keep the page inside the safe area themselves, and
the bottom navigation's `env(safe-area-inset-bottom)` padding takes effect only if that is turned on later.

### Official badges

Each button uses the provider's official "Add to … Wallet" badge when its file is installed under `public/` at
`config('wallet.badges.*')` (defaults `images/wallet/add-to-apple-wallet.svg`, `add-to-google-wallet.svg`,
`add-to-samsung-wallet.png`; override with `APPLE_WALLET_BADGE`, `GOOGLE_WALLET_BADGE`, `SAMSUNG_WALLET_BADGE`).
The artwork is licensed by each provider and is **not committed**: download it from Apple's *Add to Apple Wallet*
guidelines, Google's *Google Wallet brand guidelines* and Samsung's *Image Resources* page, keep it unmodified, at
least the minimum size the guidelines give (rendered 40 px high), with its clear space. Until a badge file is
installed the button is a plain link with the provider's exact wording ("Add to Apple Wallet"). Before going live,
install the official badges and check the guidelines once more (Apple's and Google's forbid home-made badges).

## Admin wallet page

`/admin/console/wallets` (`Admin\WalletAdminController`, `WalletAdminStatus`, gate `admin.wallets.view`: active
system administrators only; delegated admins and customers get 403). It shows each provider's configuration,
Apple certificate state and expiry, Google issuer and publishing access, Samsung approval, pass totals (passes,
saved, revoked, successful / failed operations, pending), provider errors of the last 7 days, the last successful
sync and the latest 50 operations from `wallet_pass_events`.

Warnings: missing Apple certificate, expired certificate (and expiring within 30 days), invalid or missing provider
credentials, Samsung not approved, failed pass creations and failed updates (last 7 days), passes that gave up after
retries, and a possible provider outage (3 or more connection or server errors for a provider within 30 minutes).

The page shows identifiers and states only, never certificates, keys, passwords, access tokens, ticket tokens or
signed links (`WalletAdminPageTest` checks this).

## Saved, only when it is true

| Provider | Saved when |
| :--- | :--- |
| Apple | the app generated the signed `.pkpass`, `AppleWalletPackageValidator` accepted it, and it was delivered; or a device registered for updates |
| Google | Google reports `hasUsers` on the object (`ConfirmWalletPassSaved` asks after 2, 10, 60 and 360 minutes) |
| Samsung | Samsung calls Send Card State with `event=ADDED` (`DELETED` clears it) |

Clicking "Add to …" alone never marks a pass saved.

## Updates and revocation

`Order::updated` and `Event::updated` (wired in `AppServiceProvider`) call `WalletPassManager::orderChanged()` /
`eventChanged()`:

* Apple passes follow the PassKit flow already in place: marked modified, devices pushed, the pass rebuilt on fetch.
* Google and Samsung passes get a `SyncWalletPass` job (queue `WALLET_QUEUE`, default `default`). The job decides
  again when it runs, then calls `update()` or `revoke()`:
  * Google: the class (event name, venue, time) and the object (state `ACTIVE` / `INACTIVE` / `EXPIRED`, barcode only
    while valid) are replaced through the API.
  * Samsung: an Update Notification; Samsung fetches the card again and gets `ACTIVE`, `CANCELED` or `EXPIRED`.

Retries: `SyncWalletPass` tries 5 times (backoff 30 s, 2 min, 10 min, 30 min) for retryable errors (connection
failures, 408, 429, 5xx). Other errors fail at once. A job that finally fails marks the pass `failed` with a short code
in `last_error` and lands in `failed_jobs`; the app's `JobFailed` listener logs it (and alerts in production). Each
provider maps its own errors (`GoogleWalletException`, `SamsungWalletException`, `AppleWalletException`).

## Secrets

* Keys and credentials come from the environment only (paths outside the repository or base64 secret variables),
  are held in memory only and are never logged, stored in the database or committed.
* Logs carry the provider, the pass id (a random UUID), the action and a short error code. They never contain ticket
  tokens, provider response bodies, access tokens, private keys, signed `cdata`, or Google save links.
* Save links are made at the moment of the click, sent to the browser once (`Cache-Control: no-store`,
  `Referrer-Policy: no-referrer`) and never stored. `WalletSaveAction` hides its payload from dumps.
* The admission token goes to a provider only inside the pass, only while the ticket is valid for entry.

## Google Wallet settings

| Variable | Meaning |
| :--- | :--- |
| `GOOGLE_WALLET_ENABLED` | Turn the adapter on |
| `GOOGLE_WALLET_ISSUER_ID` | Numeric issuer ID from the Google Pay & Wallet Console |
| `GOOGLE_WALLET_SERVICE_ACCOUNT_PATH` or `_BASE64` | Service-account key JSON (granted access to the issuer). Secret |
| `GOOGLE_WALLET_ORIGINS` | Comma-separated web origins for the save link (default `APP_URL`) |
| `GOOGLE_WALLET_PUBLISHING_APPROVED` | Google has granted publishing access (an issuer starts in demo mode) |
| `GOOGLE_WALLET_VERIFIED` | Tested end to end on an Android phone |

## Tests

`tests/Feature/WalletCustomerFlowTest.php` (buttons per provider, badges, success and failure messages, saving
never marks paid or admitted), `tests/Feature/WalletAdminPageTest.php` (access, each warning, totals, no secrets),
`tests/Feature/WalletProvidersTest.php` (decision, Google and Samsung adapters, buttons, saved state, queued syncs,
retries, failed jobs, no secrets in logs) and `tests/Feature/AppleWalletTest.php` (Apple).
