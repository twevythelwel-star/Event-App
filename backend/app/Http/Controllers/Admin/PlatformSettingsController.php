<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPlatformSetting;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform-wide settings stored in system_platform_settings. Credentials (payment gateway keys,
 * SMS/email provider secrets, storage keys) live in the server environment and are never shown here.
 */
class PlatformSettingsController extends Controller
{
    private const CURRENCIES = ['USD'];

    private const PAYMENT_METHODS = ['bank' => 'Bank transfer', 'cash' => 'Cash', 'card' => 'Card', 'zelle' => 'Zelle', 'cashapp' => 'Cash App'];

    private const TIMEZONES = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Phoenix', 'America/Anchorage', 'Pacific/Honolulu'];

    private const LOCALES = ['en-US' => 'English (United States)'];

    /** Fields shown in the form; anything else on the model is operational data, not configuration. */
    private const EDITABLE = [
        'platform_name', 'default_timezone', 'default_locale', 'supported_currencies', 'supported_payment_methods',
        'mandatory_2fa_for_admins', 'admin_session_timeout_minutes', 'attendee_session_timeout_days',
        'audit_retention_days', 'pii_scrub_days',
    ];

    public function edit(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        return view('admin.settings.edit', [
            'settings' => $this->settings(),
            'currencies' => self::CURRENCIES,
            'paymentMethods' => self::PAYMENT_METHODS,
            'timezones' => self::TIMEZONES,
            'locales' => self::LOCALES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $settings = $this->settings();

        $data = $request->validate([
            'platform_name' => ['required', 'string', 'min:2', 'max:80'],
            'default_timezone' => ['required', Rule::in(self::TIMEZONES)],
            'default_locale' => ['required', Rule::in(array_keys(self::LOCALES))],
            'supported_currencies' => ['required', 'array', 'min:1'],
            'supported_currencies.*' => [Rule::in(self::CURRENCIES)],
            'supported_payment_methods' => ['required', 'array', 'min:1'],
            'supported_payment_methods.*' => [Rule::in(array_keys(self::PAYMENT_METHODS))],
            'mandatory_2fa_for_admins' => ['nullable', 'boolean'],
            'admin_session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'attendee_session_timeout_days' => ['required', 'integer', 'min:1', 'max:90'],
            'audit_retention_days' => ['required', 'integer', 'min:365', 'max:3650'],
            'pii_scrub_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'confirm_risky' => ['nullable', 'boolean'],
        ], [
            'audit_retention_days.min' => 'Audit history must be kept for at least 365 days.',
            'supported_payment_methods.min' => 'Keep at least one payment method so attendees can pay.',
        ]);

        $data['mandatory_2fa_for_admins'] = $request->boolean('mandatory_2fa_for_admins');
        $data['supported_currencies'] = array_values(array_unique($data['supported_currencies']));
        $data['supported_payment_methods'] = array_values(array_unique($data['supported_payment_methods']));

        $risks = $this->risks($settings, $data);
        if ($risks !== [] && !$request->boolean('confirm_risky')) {
            return back()->withInput()
                ->withErrors(['confirm_risky' => 'These changes need confirmation: ' . implode(' ', $risks)]);
        }

        $changes = [];
        foreach (self::EDITABLE as $field) {
            $old = $settings->{$field};
            $new = $data[$field];
            if ($old != $new) {
                $changes[] = $field . ': ' . json_encode($old) . ' -> ' . json_encode($new);
            }
            $settings->{$field} = $new;
        }

        if ($changes === []) {
            return back()->with('success', 'No changes to save.');
        }

        $settings->save();

        AuditLogService::log(
            action: 'platform_settings_changed',
            targetEntity: 'system_platform_settings',
            targetId: (string) $settings->id,
            details: implode('; ', $changes),
            actor: $request->user(),
        );

        return back()->with('success', 'Platform settings saved.');
    }

    /**
     * Changes that weaken security or stop people paying, explained in plain words.
     *
     * @return array<int, string>
     */
    private function risks(SystemPlatformSetting $settings, array $data): array
    {
        $risks = [];
        if ($settings->mandatory_2fa_for_admins && !$data['mandatory_2fa_for_admins']) {
            $risks[] = 'Administrators will no longer need two-factor sign-in.';
        }
        if ((int) $data['audit_retention_days'] < (int) $settings->audit_retention_days) {
            $risks[] = 'Audit history will be kept for a shorter time.';
        }
        if ((int) $data['admin_session_timeout_minutes'] > (int) $settings->admin_session_timeout_minutes) {
            $risks[] = 'Administrator sessions will stay signed in longer.';
        }
        $removedMethods = array_diff($settings->supported_payment_methods ?? [], $data['supported_payment_methods']);
        if ($removedMethods !== []) {
            $risks[] = 'Attendees will no longer be able to pay by ' . implode(', ', array_map(fn ($m) => self::PAYMENT_METHODS[$m] ?? $m, $removedMethods)) . '.';
        }
        $removedCurrencies = array_diff($settings->supported_currencies ?? [], $data['supported_currencies']);
        if ($removedCurrencies !== []) {
            $risks[] = 'Events can no longer be priced in ' . implode(', ', $removedCurrencies) . '.';
        }

        return $risks;
    }

    private function settings(): SystemPlatformSetting
    {
        return SystemPlatformSetting::first() ?? SystemPlatformSetting::create([
            'supported_currencies' => self::CURRENCIES,
            'supported_payment_methods' => ['bank', 'cash'],
        ])->fresh();
    }
}
