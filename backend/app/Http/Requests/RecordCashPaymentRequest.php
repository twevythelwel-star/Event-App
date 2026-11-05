<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RecordCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        $order = $this->route('order');

        if (!$user || !$order) {
            return false;
        }

        // Use policy to check authorization
        return $user->can('recordCashPayment', $order, $this->attributes->get('worker_assignment'));
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
