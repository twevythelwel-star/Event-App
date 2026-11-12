<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TicketCollectionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "My Tickets": the attendee's ticket collection, from upcoming passes to past-event keepsakes. */
class TicketCollectionController extends Controller
{
    public function index(Request $request, TicketCollectionService $collection): View
    {
        $data = $collection->forUser($request->user());

        return view('tickets.collection', $data + [
            'tabMeta' => TicketCollectionService::TABS,
            'current' => TicketCollectionService::initialTab($request->query('tab'), $data['counts']),
        ]);
    }
}
