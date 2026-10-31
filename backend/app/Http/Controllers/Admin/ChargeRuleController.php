<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargeRule;
use App\Models\Organizer;
use App\Models\UsState;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fees, taxes and discounts as configuration (see App\Services\Pricing\ChargeCalculator).
 * Rules are never deleted, only switched off, so the rule behind any past order line stays on record.
 */
class ChargeRuleController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        return view('admin.charges.index', [
            'rules' => ChargeRule::query()->orderByDesc('is_active')->orderBy('kind')->orderBy('sort_order')->get(),
            'organizers' => Organizer::query()->orderBy('name')->get(['id', 'name'])->pluck('name', 'id'),
            'states' => class_exists(UsState::class) ? UsState::query()->orderBy('name')->get(['code', 'name']) : collect(),
            'currencies' => Currency::supportedCodes(),
            'defaultCurrency' => Currency::defaultCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $data = $request->validate([
            'kind' => ['required', Rule::in(ChargeRule::KINDS)],
            'label' => ['required', 'string', 'max:120'],
            'method' => ['required', Rule::in(ChargeRule::METHODS)],
            'rate_percent' => ['nullable', 'required_if:method,percent', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'amount' => ['nullable', 'required_unless:method,percent', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'currency' => ['nullable', 'required_unless:method,percent', Rule::in(Currency::supportedCodes())],
            'applies_to' => ['required', Rule::in(ChargeRule::APPLIES_TO)],
            'organizer_id' => ['nullable', 'string', Rule::exists('organizers', 'id')],
            'state_code' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'rate_percent.regex' => 'Enter the rate as a percentage, e.g. 7.5 (up to four decimal places).',
            'amount.regex' => 'Enter the amount like 2.50.',
        ]);

        // Percent to basis points as text, without floating point: "7.5" → 750; more than two decimals is refused.
        $rateBp = null;
        if ($data['method'] === 'percent') {
            [$whole, $fraction] = array_pad(explode('.', $data['rate_percent']), 2, '');
            if (strlen(rtrim($fraction, '0')) > 2) {
                return back()->withInput()->withErrors(['rate_percent' => 'Rates can have at most two decimal places (0.01%).']);
            }
            $rateBp = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
            if ($rateBp <= 0 || $rateBp > 10000) {
                return back()->withInput()->withErrors(['rate_percent' => 'Enter a rate above 0% and at most 100%.']);
            }
        }

        $rule = ChargeRule::create([
            'organizer_id' => $data['organizer_id'] ?? null,
            'kind' => $data['kind'],
            'label' => $data['label'],
            'method' => $data['method'],
            'rate_bp' => $rateBp,
            'amount_minor' => $data['method'] === 'percent' ? null : Money::parse($data['amount'], $data['currency'])->minor(),
            'currency' => $data['method'] === 'percent' ? null : strtoupper($data['currency']),
            'applies_to' => $data['applies_to'],
            'state_code' => isset($data['state_code']) && $data['state_code'] !== '' ? strtoupper($data['state_code']) : null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        AuditLogService::log(
            action: 'charge_rule_created',
            targetEntity: 'charge_rules',
            targetId: $rule->id,
            details: "Charge rule '{$rule->label}' ({$rule->kind}, {$rule->method}"
                . ($rateBp !== null ? ', ' . intdiv($rateBp, 100) . '.' . str_pad((string) ($rateBp % 100), 2, '0', STR_PAD_LEFT) . '%' : '')
                . ($rule->amount_minor !== null ? ', ' . Money::formatMinor($rule->amount_minor, $rule->currency) : '') . ') created.',
            actor: $request->user(),
        );

        return back()->with('success', 'Rule added. It applies to orders placed from now on; existing orders keep the charges they were placed with.');
    }

    public function toggle(Request $request, ChargeRule $rule): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $rule->is_active = !$rule->is_active;
        $rule->save();

        AuditLogService::log(
            action: $rule->is_active ? 'charge_rule_enabled' : 'charge_rule_disabled',
            targetEntity: 'charge_rules',
            targetId: $rule->id,
            details: "Charge rule '{$rule->label}' " . ($rule->is_active ? 'switched on' : 'switched off') . '.',
            actor: $request->user(),
        );

        return back()->with('success', $rule->is_active ? 'Rule switched on.' : 'Rule switched off. Existing orders keep their charges.');
    }
}
