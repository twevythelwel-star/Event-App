<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EventQr\QrDesignCatalog;
use App\Services\EventQr\TicketQrDesignSystem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A host's ticket QR design choice. Who may change the event is decided by the controller (only events the host
 * manages are found); whether the choice keeps the code readable is decided by TicketQrDesignSystem::choiceProblem().
 */
class UpdateTicketQrDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'preset' => ['required', 'string', Rule::in(array_merge(['auto'], array_keys(TicketQrDesignSystem::PRESETS)))],
            'module' => ['nullable', 'string', Rule::in(QrDesignCatalog::MODULE_SHAPES)],
            'finder' => ['nullable', 'string', Rule::in(QrDesignCatalog::FINDER_SHAPES)],
            'border' => ['nullable', 'string', Rule::in(QrDesignCatalog::BORDERS)],
            'logo' => ['nullable', 'string', Rule::in(QrDesignCatalog::LOGOS)],
            'monogram' => ['nullable', 'string', 'max:3', 'regex:/^[A-Za-z0-9&]*$/'],
            'primary' => $hex, 'secondary' => $hex, 'background' => $hex, 'frame' => $hex,
        ];
    }

    public function messages(): array
    {
        return ['*.regex' => 'Colours must be hex values like #12351F; initials may be up to three letters or digits.'];
    }

    /** The choice to store: empty fields dropped; custom colours only for the Cultural Celebration preset. @return array<string, string> */
    public function choice(): array
    {
        $choice = array_filter($this->validated(), fn ($v) => $v !== null && $v !== '');
        if (($choice['preset'] ?? '') !== 'cultural_celebration') {
            unset($choice['primary'], $choice['secondary'], $choice['background'], $choice['frame']);
        }

        return $choice;
    }
}
