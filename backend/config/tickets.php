<?php

return [
    // Draw attendee QR artwork on the queue right after an order commits (App\Jobs\RenderAttendeeTicketQrCodes).
    // Off in the test suite (phpunit.xml) so hundreds of order fixtures do not each decode QR artwork; the ticket
    // page draws any missing artwork itself, so turning this off never leaves a ticket without its code.
    'prerender_qr' => (bool) env('TICKET_QR_PRERENDER', true),
];
