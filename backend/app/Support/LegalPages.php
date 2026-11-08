<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The user-facing legal and policy pages, and the original wording of the ones that are new.
 *
 * Each page is backed by a versioned policy (PolicyCatalog): it shows the version in force, so wording is changed by
 * drafting and publishing a new version, never by editing a page. The original wording below is stored as version 1
 * of each new policy by migration 000063.
 *
 * DRAFT WORDING: every page must be reviewed by qualified US legal counsel before production release
 * (config compliance.legal.counsel_reviewed). Until then each page says so.
 */
final class LegalPages
{
    public const ORIGINAL_VERSION = '2026.11-v1';

    /**
     * slug => page. "policy" is the policy identifier whose version in force is shown; "related" lists other
     * policies whose current text is shown on the page.
     */
    public const PAGES = [
        'terms' => ['title' => 'EventBite Terms of Service', 'policy' => 'platform_terms_and_conditions', 'audience' => 'Everyone who uses EventBite',
            'summary' => 'The terms for using EventBite to find events, buy tickets and order food.'],
        'food-safety' => ['title' => 'Food Safety Policy', 'policy' => 'food_safety_disclaimer', 'audience' => 'Attendees, hosts and food vendors',
            'summary' => 'Who is responsible for food safety at events listed on EventBite.'],
        'alcohol' => ['title' => 'Alcohol Service and Responsible Consumption Policy', 'policy' => 'alcohol_policy', 'audience' => 'Attendees and hosts',
            'summary' => 'When alcohol may be sold or served at events, and age verification.'],
        'host-agreement' => ['title' => 'Event Host Agreement', 'policy' => 'event_host_agreement', 'audience' => 'Event hosts',
            'summary' => 'What a host agrees to when hosting events on EventBite.', 'related_host_acknowledgements' => true],
        'host-identity-privacy' => ['title' => 'Host Identity Verification and Privacy Notice', 'policy' => 'host_identity_verification_privacy_notice', 'audience' => 'Event hosts',
            'summary' => 'Why and how EventBite verifies a host\'s government ID, and what happens to it.'],
        'cancellations-refunds' => ['title' => 'Event Cancellation and Refund Policy', 'policy' => 'event_cancellation_refund_policy', 'audience' => 'Attendees and hosts',
            'summary' => 'How cancellations and refunds work for tickets and food bought on EventBite.'],
        'attendee-safety' => ['title' => 'Attendee Safety Notice', 'policy' => 'attendee_safety_notice', 'audience' => 'Attendees',
            'summary' => 'Staying safe at events, and how to report a safety issue.'],
        'privacy' => ['title' => 'Privacy Policy', 'policy' => 'privacy_policy', 'audience' => 'Everyone who uses EventBite',
            'summary' => 'What personal information EventBite collects, why, and your choices.'],
        'jurisdictions' => ['title' => 'Jurisdiction-Specific Compliance Information', 'policy' => null, 'audience' => 'Hosts and attendees',
            'summary' => 'The government agencies responsible for food and alcohol in each state, and how to check local requirements.'],
    ];

    /** The statement every page carries. It never limits EventBite's own obligations or vouches for any host. */
    public const NOT_LEGAL_ADVICE = 'EventBite\'s policies and the information on this site are general information only. They are not legal advice, and they do not replace the laws and official requirements of federal, state, county or city governments, or advice from a qualified attorney or other professional. Always confirm requirements with the government agency responsible.';

    public const NO_GUARANTEE = 'Nothing on this page limits any legal obligation EventBite has. EventBite does not guarantee that any host, vendor or event complies with the law: where EventBite reviews a document or declaration, that review is limited to what the page describes.';

    /**
     * Original wording of the policies that are new with these pages (policy identifier => title, category, roles, text).
     * Lines starting "## " are headings.
     */
    public static function originals(): array
    {
        return [
            'event_host_agreement' => ['title' => 'Event Host Agreement', 'category' => 'host_responsibility', 'roles' => ['event_host'], 'body' => <<<'TXT'
## About this agreement
This agreement applies when you create, publish or manage events on EventBite as an event host. It adds to the EventBite Terms of Service.
## Your responsibilities as a host
You are responsible for your events: their legality and safety, the accuracy of what you publish, and complying with the federal, state, county and city laws that apply where they take place.
You will obtain the permits, licences, inspections and insurance your events need before selling tickets, food or alcohol, and keep them valid for the whole event.
You will give accurate information about your identity, your organization, your events, their food and alcohol arrangements and the jurisdiction where they take place, and update it when it changes.
You are responsible for the people working your events, including staff, volunteers and vendors you engage.
## Acknowledgements
Before you can publish events you confirm each host responsibility acknowledgement listed on this page, and for each event you confirm its Event Compliance declarations. EventBite asks you to confirm them again when they materially change.
## What EventBite does
EventBite provides tools to list events, sell tickets and food, and record the documents and declarations you provide. EventBite may verify your identity, review permits and licences you submit, and suspend events or publishing privileges on compliance grounds.
EventBite's reviews are limited to what is described on the relevant pages. A review, approval or listing is not a statement that you or your event comply with the law, and it does not transfer your responsibilities to EventBite.
## Refunds and cancellations
You choose your events' refund policy within the options EventBite offers and must honour it, and any rights attendees have under applicable law. See the Event Cancellation and Refund Policy.
TXT],
            'host_identity_verification_privacy_notice' => ['title' => 'Host Identity Verification and Privacy Notice', 'category' => 'privacy', 'roles' => ['event_host'], 'body' => <<<'TXT'
## Why EventBite verifies hosts
Before a host's events can be published, EventBite verifies the host's identity with a government-issued photo ID. This helps attendees, vendors and authorities know who is accountable for an event.
## What EventBite collects
The ID document you upload, your legal name as shown on it, the document type, the issuing country, optionally the last characters of the document number, and the document's expiry date. EventBite also records when you submitted it and your consent.
## How it is protected
The document is encrypted when stored and is kept in private storage that is not publicly accessible. Only EventBite administrators specifically granted access to identity documents can open it, through a short-lived link, and every time a document is opened is recorded in an audit log.
## How it is used
Only to verify your identity as a host, to keep the verification current, and to meet legal or safety obligations, such as responding to lawful requests from authorities.
## How long it is kept
The document file is deleted on a schedule after review: currently 90 days after approval, 30 days after rejection, and 60 days for submissions that are never completed. A minimal record of the verification (its status, dates and reviewer) is kept with your account's compliance history.
## Your choices
Providing ID is required to publish events as a host. You can replace a document while it is awaiting review or after it expires. To ask about your information, contact EventBite support.
TXT],
            'event_cancellation_refund_policy' => ['title' => 'Event Cancellation and Refund Policy', 'category' => 'payments_refunds', 'roles' => ['all'], 'body' => <<<'TXT'
## Each event has its own refund policy
The event host sets the refund policy for each event, and it is shown on the event page before you buy. Hosts choose one of: refunds of the full amount only; full or partial refunds; or no refunds. A host may also set a deadline before the event after which refund requests are not accepted.
## Asking for a refund
Signed-in attendees can request a cancellation and refund from their orders. The host reviews each request under the event's refund policy. Approved refunds are arranged by the host, and each refund is recorded on your order.
## If an event is cancelled or changed
If a host cancels an event, the host is responsible for refunding attendees as the event's refund policy and applicable law require. If an event is postponed, rescheduled or affected by weather, the host's stated postponement and weather policies apply.
## If EventBite suspends an event
EventBite may suspend an event on compliance or safety grounds. A suspended event cannot be sold. Refunds for a suspended event remain the host's responsibility under the event's refund policy and applicable law.
## Your legal rights
Nothing in this policy or in a host's refund policy removes rights you have under applicable consumer protection law.
TXT],
            'attendee_safety_notice' => ['title' => 'Attendee Safety Notice', 'category' => 'safety', 'roles' => ['all'], 'body' => <<<'TXT'
## In an emergency
If someone is in danger or needs urgent medical help, call 911 first.
## Food and allergies
Hosts and food vendors are responsible for the food they serve. Menus show allergen and ingredient information provided by the host. If you have a severe allergy, confirm ingredients and cross-contamination precautions with the host or vendor before ordering or eating.
## Alcohol
Alcohol may only be served where the host is legally authorized to serve it. You may be asked for valid government-issued photo ID. Buying a ticket does not entitle you to be served alcohol. Please drink responsibly and never drive after drinking.
## At the venue
Follow the instructions of event staff and venue security, know where exits are, and keep your belongings with you.
## Reporting a safety issue
If something at an event was unsafe, such as suspected food poisoning, an allergic reaction to an unlabelled ingredient, underage drinking, crowding or harassment, sign in and use "Report a safety issue" on the event page. Reports go to EventBite's compliance team, not to the host.
TXT],
            'privacy_policy' => ['title' => 'Privacy Policy', 'category' => 'privacy', 'roles' => ['all'], 'body' => <<<'TXT'
## Information EventBite collects
Account information: your name, email address, phone number and account preferences.
Orders and payments: tickets and food you order, payment method chosen, payment references and confirmations you submit, refunds and cancellations.
Event check-in: ticket and order codes scanned at events.
Hosts: organization details, events, and the identity verification described in the Host Identity Verification and Privacy Notice.
Policy acceptances: which policy versions you accepted, when, and the IP address and browser used, so EventBite can show what was agreed.
Safety reports you submit, and messages you send to EventBite support.
Technical information: IP address, browser and security logs used to keep accounts and payments secure.
## How EventBite uses it
To run your account, sell and deliver tickets and food orders, let hosts and their staff check you in and fulfil orders, send messages about your orders and events, keep the platform secure, meet legal obligations, and handle safety reports and disputes.
## Who it is shared with
The host of an event you buy from receives the information needed to fulfil your order and check you in. Event staff assigned by the host see what their role needs. Service providers that send messages or host EventBite's systems process information on EventBite's behalf. Information may be disclosed where the law requires it.
## Keeping it secure
Access to personal information is limited by role, sensitive documents are encrypted, and access to them is logged.
## Your choices
You can update your account details and message preferences in your settings. To ask about your information or request its deletion, contact EventBite support. Some records, such as orders, payments and policy acceptances, may need to be kept to meet legal obligations.
TXT],
        ];
    }

    /** Split stored policy text into headings and paragraphs for display. */
    public static function blocks(string $body): array
    {
        $blocks = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $blocks[] = str_starts_with($line, '## ') ? ['type' => 'heading', 'text' => substr($line, 3)] : ['type' => 'paragraph', 'text' => $line];
        }

        return $blocks;
    }

    public static function counselReviewed(): bool
    {
        return (bool) config('compliance.legal.counsel_reviewed', false);
    }
}
