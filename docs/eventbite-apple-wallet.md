# Apple Wallet passes

EventBite issues real, signed Apple Wallet event-ticket passes (`.pkpass`). The pass is a clean entry pass; the
EventBite app keeps the richer collectible ticket. Packaging and signing use the maintained
[`pkpass/pkpass`](https://github.com/includable/php-pkpass) library (MIT, `^2.5`); EventBite builds the pass
content, serves Apple's PassKit web service for updates and sends the update pushes itself.

Status: **built and tested with throwaway certificates shaped like Apple's. Not yet tested with a real Pass Type
ID certificate on an iPhone** (Verification Required, see the go-live checklist at the end).

## What the pass shows

Style `eventTicket`, EventBite navy background (`rgb(7, 17, 31)`), white text, lime labels.

| Place | Field |
| :--- | :--- |
| Header | Status: Valid for entry, Checked in, Awaiting payment, Event over, Transferred, Revoked, Refunded, Cancelled |
| Primary | Event name |
| Secondary | Date and time (a real date, so Wallet formats it in the holder's locale), venue name |
| Auxiliary | Ticket type, ticket number |
| Back | Holder name, venue address, order number, entry rules, link to the full ticket, organizer |
| Barcode | QR code: the order's current admission token, alt text = ticket number |
| Images | `icon` (EventBite mark), `logo` (official EventBite logo, scaled, never redrawn), `strip` (only the host's own uploaded flyer) |

Library and generated artwork never appear on a pass: there is no room on a Wallet pass to label an illustrative
photo, and it must not look like the real event. Sizes follow Apple's limits (icon 29×29, logo up to 160×50,
strip 375×98, each at @2x and @3x); `wallet:validate` checks them.

Other keys: `relevantDate` (event start), `expirationDate` (the moment the ticket becomes a keepsake,
`TicketLifecycle::overAt`), `locations` when the event has coordinates, `sharingProhibited: true`, and
`changeMessage` on status, time, venue and title, so Wallet shows a notification when they change.

## Admission credential and lifecycle

The pass is rebuilt from the ticket record every time it is downloaded or fetched, so it is never out of step:

* **Barcode only while valid for entry**: paid (or partly refunded), not revoked, not cancelled or refunded, the
  event not over, and the pass belongs to the order's current holder.
* **Reissue** (`Order::reissueTicketToken`): the pass carries the new token; the old QR stops working at the gate.
  The pass never mints a token of its own: it uses `Order::deliverTicketQrToken()`, the same delivery the ticket
  page uses.
* **Revocation, refund, cancellation**: the pass turns `voided` and the barcode is removed.
* **Transfer**: the sender's pass turns void (status Transferred, no barcode); the recipient adds their own pass.
* **After the event**: no barcode, and the pass expires. The gate rejects the ticket anyway.
* **Event changes** (time, date, venue, address, title, status, artwork): every pass for the event updates.

Downloads: `GET /tickets/{order}/wallet.pkpass` (signed-in holder only; anyone else gets 404, the same as the
ticket page). Unpaid, void and past tickets are refused with a message on the ticket page.

## Updates (PassKit web service)

Each pass carries `webServiceURL` = `APP_URL/wallet` (or `APPLE_WALLET_WEB_SERVICE_URL`) and its own
`authenticationToken` (48 random characters, stored encrypted). Routes (`routes/wallet.php`, no session or CSRF):

| Method and path | Purpose |
| :--- | :--- |
| `POST /wallet/v1/devices/{device}/registrations/{passTypeId}/{serial}` | Device registers for updates (201 new, 200 already) |
| `DELETE …/registrations/{passTypeId}/{serial}` | Device stops updates |
| `GET /wallet/v1/devices/{device}/registrations/{passTypeId}?passesUpdatedSince=tag` | Serials changed since the tag (204 when none) |
| `GET /wallet/v1/passes/{passTypeId}/{serial}` | Latest pass; 304 when not modified since `If-Modified-Since` |
| `POST /wallet/v1/log` | Device error reports, logged |

Calls about a pass need `Authorization: ApplePass <token>`; a wrong or missing token is 401. When an order or its
event changes, `WalletPassService` moves the pass's `last_modified_at` forward and queues `PushWalletPassUpdate`,
which sends an empty push to each registered device through Apple Push Notification service (HTTP/2,
`api.push.apple.com`, topic = Pass Type ID, authenticated with the pass certificate). A 410 answer removes that
registration. Changes made with raw database queries bypass the model events and do not push: change orders and
events through their models.

The web service URL must be HTTPS. Without it, passes are still issued but cannot update.

## Configuration

All from the environment. Never commit a certificate, private key or password: `.p12` files belong outside the
repository (or in a secret environment variable), and `wallet:check` never prints the password.

```dotenv
APPLE_WALLET_ENABLED=true
APPLE_WALLET_PASS_TYPE_ID=pass.com.example.eventbite.ticket   # your registered Pass Type ID
APPLE_WALLET_TEAM_ID=ABCDE12345                               # 10-character Team ID
APPLE_WALLET_ORGANIZATION_NAME=EventBite

# The Pass Type ID certificate with its private key, as .p12. One of:
APPLE_WALLET_CERTIFICATE_PATH=/run/secrets/eventbite-pass.p12   # a file outside the repository, mode 0600
APPLE_WALLET_CERTIFICATE_BASE64=                                 # base64 of the .p12 (hosts without a disk, e.g. Laravel Cloud)
APPLE_WALLET_CERTIFICATE_PASSWORD=...                            # the .p12 export password

# Optional
APPLE_WALLET_WWDR_CERTIFICATE_PATH=        # Apple WWDR G4 intermediate (PEM); default: the copy in pkpass/pkpass (valid to 2030-12-10)
APPLE_WALLET_WEB_SERVICE_URL=              # default APP_URL/wallet; must be HTTPS
APPLE_WALLET_PUSH_ENABLED=true
APPLE_WALLET_APNS_HOST=https://api.push.apple.com
APPLE_WALLET_APNS_TIMEOUT=10
```

A queue worker must be running for update pushes (`PushWalletPassUpdate`, retried 3 times). Check the setup with:

```bash
php artisan wallet:check
```

It confirms the certificate opens with the password, has not expired, and was issued for the configured Pass Type
ID and Team ID, and prints the expiry date. The "Add to Apple Wallet" button appears only when this passes and
`APPLE_WALLET_ENABLED=true`. To check a downloaded pass:

```bash
php artisan wallet:validate path/to/ticket.pkpass
```

It checks the zip, that every file is in the manifest with a matching SHA-1, the signature over the manifest, that
the signer was issued by the WWDR intermediate and matches the Pass Type ID and Team ID, required keys, barcode
format and image sizes.

## Getting the certificate (first time)

Needs a paid Apple Developer Program membership.

1. In the Apple Developer account, **Certificates, Identifiers & Profiles → Identifiers → +**, choose
   **Pass Type IDs**, and register an identifier such as `pass.com.example.eventbite.ticket`.
2. Create a private key and a certificate signing request, on a trusted machine, never in the repository:
   ```bash
   openssl req -new -newkey rsa:2048 -nodes -keyout eventbite-pass.key -out eventbite-pass.csr -subj "/CN=EventBite Pass/emailAddress=you@example.com"
   ```
3. Open the Pass Type ID, **Create Certificate**, upload `eventbite-pass.csr`, and download `pass.cer`.
4. Bundle certificate and key as a `.p12` with a strong password:
   ```bash
   openssl x509 -inform der -in pass.cer -out pass.pem
   openssl pkcs12 -export -inkey eventbite-pass.key -in pass.pem -out eventbite-pass.p12
   ```
   (On a Mac you can instead add `pass.cer` to Keychain Access and export the certificate with its key as `.p12`.)
5. Store the `.p12` and its password in the secret store, then delete the loose `.key`, `.csr`, `.cer` and `.pem`
   files or keep them only in the same secret store.
6. Set the environment variables above and run `php artisan wallet:check`.

## Deployment

* **Laravel Cloud** (no persistent disk): `base64 -w0 eventbite-pass.p12` into `APPLE_WALLET_CERTIFICATE_BASE64`,
  and the password into `APPLE_WALLET_CERTIFICATE_PASSWORD`, both as secret environment variables.
* **Servers with a disk**: put the `.p12` outside the project (for example `/etc/eventbite/`), owned by the app user,
  mode `0600`, and point `APPLE_WALLET_CERTIFICATE_PATH` at it.
* Run `php artisan migrate` (tables `wallet_passes`, `wallet_device_registrations`), make sure a queue worker runs,
  then `php artisan wallet:check`.
* The scheduler runs `wallet:check --warn-days=30` every Monday while Wallet is enabled; a failure is logged.

## Renewing the certificate

Pass Type ID certificates expire; `wallet:check` prints the date. Renew **before** it expires: an expired certificate
can no longer sign new or updated passes or send update pushes, so passes already in Wallet stop updating.

1. Repeat steps 2 to 4 above for the **same** Pass Type ID (a new key and CSR are best practice).
2. Replace the secret (`APPLE_WALLET_CERTIFICATE_BASE64` or the file) and password, and redeploy or restart
   workers so they pick it up.
3. Run `php artisan wallet:check`, add a ticket to Wallet on a test iPhone, then change that test event's time and
   confirm the pass updates.
4. Revoke the old certificate in the Apple Developer account only after the new one is confirmed working.

Passes already in Wallet keep working with the new certificate, because they are tied to the Pass Type ID and Team
ID, not to one certificate. Do not change the Pass Type ID: passes issued under the old one could no longer update.

If the WWDR intermediate changes (the bundled G4 copy is valid to 2030-12-10), download the current one from
Apple's certificate authority page and set `APPLE_WALLET_WWDR_CERTIFICATE_PATH`.

If a certificate or its password leaks: revoke it in the Apple Developer account at once, issue a new one, and
replace the secrets. Admission is unaffected (the gate checks tokens on the server), but anyone holding the
leaked key could sign passes that look like EventBite's.

## Go-live checklist (Verification Required)

* Add a pass from Safari on a real iPhone and on Apple Watch; check the layout, the strip with a real host flyer, and
  that the QR scans at the gate.
* Change a test event's time and venue, and reissue, revoke, refund and transfer a test ticket: confirm each pass
  updates on the device and shows Wallet's change notification.
* Confirm pushes reach `api.push.apple.com` from production (outbound HTTP/2 on port 443).
* Replace the plain "Add to Apple Wallet" button with Apple's official badge artwork, following Apple's Wallet
  marketing guidelines. Not done here: the badge is Apple's artwork, to be downloaded by the account holder under
  Apple's terms.
* Have the pass reviewed against Apple's Human Interface Guidelines for Wallet.

## Code

| Part | File |
| :--- | :--- |
| Settings | `config/wallet.php`, `app/Services/Wallet/AppleWalletConfig.php` |
| Pass content and signing | `app/Services/Wallet/AppleWalletPassBuilder.php`, `AppleWalletImages.php` |
| Package validation | `app/Services/Wallet/AppleWalletPackageValidator.php`, `php artisan wallet:validate` |
| Issue and change handling | `app/Services/Wallet/WalletPassService.php`, model hooks in `AppServiceProvider` |
| Pushes | `app/Services/Wallet/AppleWalletPushService.php`, `app/Jobs/PushWalletPassUpdate.php` |
| Endpoints | `AppleWalletController` (download), `AppleWalletWebServiceController`, `routes/wallet.php` |
| Data | migration `2026_11_12_000071_create_wallet_pass_tables` |
| Tests | `tests/Feature/AppleWalletTest.php` (throwaway certificates: `tests/Support/WalletTestCertificates.php`) |
