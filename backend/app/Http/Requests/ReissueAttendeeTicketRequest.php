<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AttendeeTicket;
use Illuminate\Foundation\Http\FormRequest;

/** Reissuing one guest's ticket: the holder or the event's staff (AttendeeTicketPolicy), always with a reason. */
class ReissueAttendeeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('attendeeTicket');

        return $ticket instanceof AttendeeTicket && $this->user()?->can('reissue', $ticket) === true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:200']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why this QR code is being replaced (for example, the screenshot was shared).'];
    }
}
