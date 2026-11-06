<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Checkout\TicketTransferService;
use App\Services\OrderPlacementException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ticket holders transferring tickets, and recipients accepting them. Rules live in TicketTransferService. */
class TicketTransferController extends Controller
{
    public function __construct(private readonly TicketTransferService $transfers) {}

    public function start(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        try {
            $result = $this->transfers->start($request->user(), $order, $data['email']);
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        // The link is shown once, to the sender, to pass on; only its hash is stored.
        return back()->with('status', "Transfer offered to {$data['email']}. They accept it by signing in with that email.")
            ->with('transfer_link', $result['link']);
    }

    public function cancel(Request $request, string $transferId): RedirectResponse
    {
        $this->transfers->cancel($request->user(), $transferId);

        return back()->with('status', 'Transfer cancelled. The tickets stay yours.');
    }

    public function show(Request $request, string $token): View
    {
        $transfer = $this->transfers->findByToken($token);

        return view('tickets.transfer-accept', [
            'transfer' => $transfer,
            'order' => $transfer?->order?->load('items'),
            'token' => $token,
            'emailMatches' => $transfer && mb_strtolower((string) $request->user()->email) === $transfer->to_email,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        try {
            $order = $this->transfers->accept($request->user(), $token);
        } catch (OrderPlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $order)->with('status', 'The tickets are now yours. This QR code is your ticket.');
    }
}
