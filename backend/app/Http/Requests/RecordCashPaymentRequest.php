<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RecordCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        $event = $this->route('event');
        if (!$user || !$event instanceof \App\Models\Event) {
            return false;
        }

        // The worker route names the event; the order comes in the body, by id or order number, and only orders of
        // this event count. An order that is not found is answered by the controller (404), not here.
        $order = \App\Models\Order::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->when($this->filled('order_id'),
                fn ($q) => $q->whereKey($this->input('order_id')),
                fn ($q) => $q->where('order_number', trim((string) $this->input('order_number'))))
            ->first();
        if (!$order) {
            return true;
        }

        return $user->can('recordCashPayment', [$order, $this->attributes->get('worker_assignment')]);
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required_without:order_id', 'nullable', 'string', 'max:40'],
            'order_id' => ['required_without:order_number', 'nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
