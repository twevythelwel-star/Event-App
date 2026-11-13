# Samsung Wallet: research findings and integration status

> **Status: approval pending. Not live.** EventBite has not been approved as a Samsung Wallet partner, no ticket card
> has been launched in the Samsung Wallet Partners Portal, and the integration has not been tested on a Galaxy
> device. The adapter (`App\Services\Wallet\SamsungWalletService`) is built to Samsung's published documentation and
> unit-tested with stand-in keys, but **the "Add to Samsung Wallet" button stays hidden** and no link can be made
> until all three switches below are true in fact. Nothing in the app produces a placeholder or fake wallet link.

Researched on 12 November 2026 from Samsung's official developer documentation at
[developer.samsung.com/wallet](https://developer.samsung.com/wallet/api_new/overview.html). Samsung Wallet is
**not** like Apple Wallet (a signed file we hand over) or Google Wallet (objects we create through Google's API):
it is a partner programme with Samsung's approval, Samsung-issued certificates, and a two-way server integration in
which Samsung calls our server.

## Findings, checked against Samsung's documentation

| Question | What Samsung's documentation says | Source |
| :--- | :--- | :--- |
| Partner programme | Partners register on the Samsung Wallet Partners Portal (partner.walletsvc.samsung.com) with a Samsung account, then create a **Samsung Developer Business Account** and accept the Samsung Developer terms. One account per company. | [Onboarding guide](https://developer.samsung.com/wallet/overview/onboardingguide.html), [Prerequisites](https://developer.samsung.com/wallet/welcome/prerequisites.html) |
| Merchant / service-provider eligibility | Company-based: a business account with a company profile (name, website). The pages state no further eligibility criteria (no list of business types, no individual accounts mentioned). Confirm EventBite's eligibility with Samsung during onboarding. | [Onboarding guide](https://developer.samsung.com/wallet/overview/onboardingguide.html) |
| Required approval | Yes, at two levels. The submitted company information is reviewed: "After verification, the partner will get the authority to use all services." Card templates must be submitted and **approved before launching** Wallet Cards. No review time is published. | [Onboarding guide](https://developer.samsung.com/wallet/overview/onboardingguide.html), [Prerequisites](https://developer.samsung.com/wallet/welcome/prerequisites.html) |
| Test mode | New cards start in **Testing Mode**; partners must test before requesting activation. | [Prerequisites](https://developer.samsung.com/wallet/welcome/prerequisites.html) |
| API access | Server APIs need the CSR-based certificates and authorization tokens, and **permission must be requested** in the portal for the services used (e.g. card issuance, notifications). Samsung's server IPs must be allowed through our firewall (listed in the portal). | [Prerequisites](https://developer.samsung.com/wallet/welcome/prerequisites.html), [Manage wallet card](https://developer.samsung.com/wallet/addtosamsungwallet/managewalletcard.html) |
| Ticket and pass types | Card types: Boarding Pass, **Event Ticket**, Coupon, Gift Card, Loyalty, Digital IDs, Digital Keys, Pay As You Go, Generic Card, Clip, Reservation. Event ticket: `type: "ticket"`, `subType` one of `performances`, `sports`, `movies`, `entrances`, `others`. | [Overview](https://developer.samsung.com/wallet/api_new/overview.html), [Event ticket spec](https://developer.samsung.com/wallet/addtosamsungwallet/walletcards/eventticket.html) |
| Event-ticket fields | Required: `title` (32), `mainImg` (URL, ≤512 KB), `logoImage` (+ `lightUrl`/`darkUrl`, ≤256 KB), `providerName`, `issueDate`, `reservationNumber`, `startDate` (epoch ms), `noticeDesc` (HTML allowed), `appLinkLogo`, `appLinkName`, `appLinkData`. Optional include `endDate` (if missing the card expires 10 h after start), `holderName`, `grade`, `seatNumber`, `bgColor`, `fontColor`, `preventCaptureYn`, and the barcode fields `barcode.value`, `barcode.serialType`, `barcode.ptFormat`, `barcode.ptSubFormat`. Some fields (`provision.*`, `selectorId`) need Samsung's approval. | [Event ticket spec](https://developer.samsung.com/wallet/addtosamsungwallet/walletcards/eventticket.html) |
| Signing and security | Onboarding: we create an RSA key and a **CSR** (Common Name = our service domain) and upload it; Samsung signs it and issues the certificates (JWS and JWE). After that the encryption settings can't be edited. Card data (`cdata`) is a **JWS (RS256) wrapping a JWE** (`alg` RSA1_5, `enc` A128GCM). JWS header: `cty` = `CARD`, `ver` = `3`, `certificateId`, `partnerId`, `utc` (epoch ms). REST calls carry `Authorization: Bearer <JWS>` with `cty` = `AUTH` and a payload binding the call's `API.method` and `API.path`; validators must check both. `cdata` is time-sensitive (30-second TTL per the portal guide): make it right after the user's click. | [Onboarding guide](https://developer.samsung.com/wallet/overview/onboardingguide.html), [Card data token](https://developer.samsung.com/wallet/securityauthentication/carddatatoken.html), [REST API token](https://developer.samsung.com/wallet/securityauthentication/restapiauthorizationtoken.html), [Key lifecycle](https://developer.samsung.com/wallet/securityauthentication/keycertificatelifecycle.html), [Manage wallet card](https://developer.samsung.com/wallet/addtosamsungwallet/managewalletcard.html) |
| Key rotation | Generate a new key and CSR, register the new certificate while the old one stays active, switch to the new `certificateId`, then retire the old key. On suspected compromise, stop issuing tokens with the key immediately. | [Key lifecycle](https://developer.samsung.com/wallet/securityauthentication/keycertificatelifecycle.html) |
| Add-to-wallet links | Data Transmit Link `https://a.swallet.link/atw/v3/{cardId}#Clip?cdata={cdata}`; Data Fetch Link `https://a.swallet.link/atw/v3/{certificateId}/{cardId}#Clip?pdata={refId}` (Samsung then fetches the card from us). | [API guidelines](https://developer.samsung.com/wallet/addtosamsungwallet/apiguidelines.html) |
| Supported countries | **No country list is published in the developer documentation.** Samsung's blog says the Wallet service area follows Samsung Pay's launch countries. Availability is checked per device and country at run time (below). Confirm the countries for EventBite's markets with Samsung during onboarding. | [Check service available devices](https://developer.samsung.com/wallet/references/checkserviceavailabledevices.html) |
| Supported devices | A compatible Samsung (Galaxy) phone or wearable with Samsung Wallet and a Samsung account, on a supported Android / One UI version (no version numbers published for cards). Samsung's button script shows the button only on supported devices; the availability API is `GET https://api-us3.mpay.samsung.com/wallet/cmn/v2.0/device/available?serviceType=WALLET&modelName=…` with a `partnerCode` header. The Device Compatibility page covers only Digital Keys and IDs. | [Prerequisites](https://developer.samsung.com/wallet/welcome/prerequisites.html), [Implementing the button](https://developer.samsung.com/wallet/addtosamsungwallet/implementingatwbutton.html), [Check service available devices](https://developer.samsung.com/wallet/references/checkserviceavailabledevices.html) |
| Branding and artwork | Official "Add to Samsung Wallet" button assets (`btnATSW`, normal / with icon / vertical, light and dark) from Samsung's CDN and the Logo Toolkit; the button script takes `buttonType` (`btnSW`, `btnATSW`, `qrcode`) and `mediaTheme`. Locale variants are "to be provided"; ask Samsung Wallet Support for a missing language. Card artwork limits: `mainImg` ≤512 KB, logos ≤256 KB, `bgColor` hex, `fontColor` `dark`/`light`. | [Image resources](https://developer.samsung.com/wallet/references/imageresources.html), [Implementing the button](https://developer.samsung.com/wallet/addtosamsungwallet/implementingatwbutton.html), [Event ticket spec](https://developer.samsung.com/wallet/addtosamsungwallet/walletcards/eventticket.html) |
| Updates | **Update Notification** `POST {cc2}/wltex/cards/{cardId}/updates` on `https://tsapi-card.walletsvc.samsung.com` (headers `Authorization`, `x-smcs-partner-id`, `x-request-id`; body `card.type`, `card.data[].refId`, `card.data[].state`). Samsung then calls our **Get Card Data** `GET {partner server}/cards/{cardId}/{refId}` for the new content. | [API guidelines](https://developer.samsung.com/wallet/addtosamsungwallet/apiguidelines.html) |
| Revocation | Card states include `ACTIVE`, `UPDATED`, `EXPIRED`, `REDEEMED`, `HELD`, `DELETED`, `CANCELED`, `PENDING`, `SUSPENDED`. Per ticket: Update Notification, then Get Card Data returns the new state. Per event: **Cancel Notification** `POST {cc2}/wltex/cards/{cardId}/cancels` with the `eventId` and state `CANCELED`. | [API guidelines](https://developer.samsung.com/wallet/addtosamsungwallet/apiguidelines.html) |
| Saved confirmation | Samsung calls our **Send Card State** `POST {partner server}/cards/{cardId}/{refId}?cc2={country}&event={ADDED\|UPDATED\|DELETED\|PROVISIONED}`. `cc2` is the country code we need for later Update Notifications. | [API guidelines](https://developer.samsung.com/wallet/addtosamsungwallet/apiguidelines.html) |

Gaps in Samsung's public pages, to settle with Samsung during onboarding: the exact JWE key (we encrypt to the
Samsung certificate issued at onboarding and sign with our own key, the usual JOSE arrangement; Samsung's
`Generate_Cdata` sample code is the reference), the published review time, the country list, and whether the
per-ticket revocation should be signalled with a state other than `UPDATED` in the notification body.

## What is built

| Piece | Where |
| :--- | :--- |
| Settings and keys | `config/wallet.php` `samsung`, `App\Services\Wallet\SamsungWalletConfig` |
| Card JSON, `cdata`, link, Update Notification, inbound token check | `App\Services\Wallet\SamsungWalletService` |
| JWS / JWE (RS256, RSA1_5 + A128GCM) on PHP's OpenSSL | `App\Services\Wallet\Jose` |
| Partner endpoints Samsung calls (Get Card Data, Send Card State) | `App\Http\Controllers\SamsungWalletPartnerController`, `routes/wallet.php` (`/wallet/samsung/cards/{cardId}/{refId}`) |
| Decision, queueing, saved state | `App\Services\Wallet\WalletPassManager` (see [eventbite-wallet-architecture.md](eventbite-wallet-architecture.md)) |
| Tests | `tests/Feature/WalletProvidersTest.php` |

Design choices:

* **Data Transmit Link** (`cdata`) for adding, made at the moment of the click and never stored or logged. No `refId`
  that could be guessed is ever in a link; the `refId` is `eb-` plus a random UUID.
* **Saved only on Samsung's `ADDED`.** The click alone never marks a pass saved. `DELETED` clears it.
* **Inbound calls** must carry Samsung's RS256 token: verified with Samsung's certificate, `cty` = `AUTH`, our
  `partnerId`, `utc` within `SAMSUNG_WALLET_TOKEN_MAX_AGE` seconds (300 by default), and `API.method` / `API.path`
  equal to the actual request. Anything else gets 401; while Samsung is not configured, 404.
* **Updates and revocation:** an order or event change queues `SyncWalletPass`; the adapter sends an Update
  Notification (only for cards Samsung reported as added, since we need `cc2`), and Samsung's Get Card Data call
  then receives the card rebuilt from the ticket record: `ACTIVE` with the current QR, `CANCELED` without a barcode
  (revoked, refunded, cancelled, transferred), or `EXPIRED` after the event.
* Artwork on the card is only the official EventBite logo and mark (`public/images/brand`), never generated or
  library imagery. Card data marks screenshots as prevented (`preventCaptureYn`).

## Switches (all off by default)

| Variable | Meaning |
| :--- | :--- |
| `SAMSUNG_WALLET_ENABLED` | Turn the adapter on (it still needs the IDs and keys below). |
| `SAMSUNG_WALLET_PARTNER_APPROVED` | Samsung has approved the partner account **and** launched the ticket card. Set only when that is true. |
| `SAMSUNG_WALLET_VERIFIED` | We have added, updated and revoked a ticket end to end on a Galaxy device in Samsung's Testing Mode. |
| `SAMSUNG_WALLET_PARTNER_ID`, `SAMSUNG_WALLET_CARD_ID`, `SAMSUNG_WALLET_CERTIFICATE_ID` | From the Partners Portal. |
| `SAMSUNG_WALLET_CARD_SUB_TYPE` | `entrances` (default), `performances`, `sports`, `movies` or `others`. |
| `SAMSUNG_WALLET_PRIVATE_KEY_PATH` or `_BASE64` (+ `_PASSWORD`) | Our RSA private key behind the CSR. Secret: environment only, never committed. |
| `SAMSUNG_WALLET_SAMSUNG_CERTIFICATE_PATH` or `_BASE64` | The certificate Samsung issued at onboarding. |
| `WALLET_TESTER_EMAILS` | Accounts that may see the button (marked "(test)") while approval or our testing is pending. |

The button shows to ticket holders only when enabled, configured, approved **and** verified. `php artisan
wallet:status` prints each provider's state and what is still missing, never a secret.

## Go-live checklist

1. Register EventBite on the Samsung Wallet Partners Portal with the business account; wait for verification.
2. Generate an RSA key and a CSR (CN = the production domain); upload the CSR; keep the key in the secret store.
3. Create the ticket card (type Ticket, sub type Entrances); set "Partner Get card data" and "Partner Send card state"
   to `https://<domain>/wallet/samsung` (Samsung appends `/cards/{cardId}/{refId}`); allow Samsung's server IPs.
4. Request the permissions for card issuance and notifications.
5. Set the IDs, key and certificate variables and `SAMSUNG_WALLET_ENABLED=true`; add testers to `WALLET_TESTER_EMAILS`.
6. In Testing Mode on a Galaxy device: add a ticket, check the `ADDED` call marks it saved, change the event time,
   reissue the QR and refund a ticket, and check each reaches the card. Compare our `cdata` with Samsung's sample code.
7. Submit the card for approval; once launched, set `SAMSUNG_WALLET_PARTNER_APPROVED=true` and, after a final check,
   `SAMSUNG_WALLET_VERIFIED=true`.
