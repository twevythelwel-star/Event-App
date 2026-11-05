<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        $order = $this->route('order');

        if (!$user || !$order) {
            return false;
        }

        // Use policy to check authorization
        return $user->can('verifyPayment', $order, $this->attributes->get('worker_assignment'));
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'amount_verified_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
