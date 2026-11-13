<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EventQr\TicketQrDesignSystem;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** The administrators' global ticket QR controls. Readability rules cannot be relaxed: contrast never below 7:1. */
class UpdateTicketQrControlsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Admin::SETTINGS_MANAGE);
    }

    public function rules(): array
    {
        $presetKeys = array_keys(TicketQrDesignSystem::PRESETS);

        return [
            'enabled' => ['boolean'],
            'default_preset' => ['required', 'string', Rule::in(array_merge(['auto'], $presetKeys))],
            'allowed_presets' => ['required', 'array', 'min:1'],
            'allowed_presets.*' => ['string', Rule::in($presetKeys)],
            'allow_custom_palette' => ['boolean'],
            'allow_center_logo' => ['boolean'],
            'min_contrast' => ['required', 'numeric', 'min:' . TicketQrDesignSystem::MIN_CONTRAST, 'max:21'],
        ];
    }

    public function messages(): array
    {
        return ['min_contrast.min' => 'The minimum contrast cannot go below 7:1; that is the floor for reliable scanning.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $default = (string) $this->input('default_preset');
            if ($default !== 'auto' && !in_array($default, (array) $this->input('allowed_presets', []), true)) {
                $validator->errors()->add('default_preset', 'The default design must be one of the allowed designs.');
            }
        }];
    }

    /** @return array{enabled: bool, default_preset: string, allowed_presets: list<string>, allow_custom_palette: bool, allow_center_logo: bool, min_contrast: float} */
    public function controls(): array
    {
        return TicketQrDesignSystem::normaliseControls([
            'enabled' => $this->boolean('enabled'),
            'default_preset' => (string) $this->validated('default_preset'),
            'allowed_presets' => array_values(array_unique((array) $this->validated('allowed_presets'))),
            'allow_custom_palette' => $this->boolean('allow_custom_palette'),
            'allow_center_logo' => $this->boolean('allow_center_logo'),
            'min_contrast' => (float) $this->validated('min_contrast'),
        ]);
    }
}
