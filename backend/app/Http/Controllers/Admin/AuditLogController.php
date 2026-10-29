<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only view of the security audit trail. There is deliberately no edit or delete route,
 * and the model refuses updates and deletes.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Admin::AUDIT_VIEW);
        $admin = $request->user();

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'actor' => ['nullable', 'string', 'max:100'],
            'outcome' => ['nullable', Rule::in(['success', 'denied', 'failed'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'pii' => ['nullable', 'boolean'],
        ]);

        $base = Admin::applyAuditScope(SecurityAuditLog::query(), $admin);

        $query = (clone $base)->with('user:id,name');
        if ($action = $filters['action'] ?? null) {
            $query->where('action', $action);
        }
        if ($actor = trim((string) ($filters['actor'] ?? ''))) {
            $query->whereIn('user_id', fn ($q) => $q->select('id')->from('users')->where('name', 'like', "%{$actor}%"));
        }
        if ($outcome = $filters['outcome'] ?? null) {
            $query->where('outcome', $outcome);
        }
        if ($from = $filters['from'] ?? null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->where('created_at', '<', \Carbon\Carbon::parse($to)->addDay());
        }
        if (!empty($filters['pii'])) {
            $query->where('is_pii_access', true);
        }

        $showFullDetails = Admin::isSystemAdmin($admin);

        return view('admin.audit.index', [
            'logs' => $query->latest('created_at')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'actions' => (clone $base)->distinct()->orderBy('action')->pluck('action'),
            'redact' => fn (?string $text) => $showFullDetails ? (string) $text : self::redact((string) $text),
        ]);
    }

    /**
     * Mask contact details inside free-text audit details for viewers below System Administrator.
     */
    public static function redact(string $text): string
    {
        $text = preg_replace_callback('/([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', fn ($m) => $m[1] . '***@' . $m[2], $text) ?? $text;

        // Phone numbers: ten or more digits, e.g. +1 (876) 555-0142. Dates and order numbers stay readable.
        return preg_replace_callback('/\+?\d{0,3}[\s.-]?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', fn ($m) => '***' . substr(preg_replace('/\D/', '', $m[0]), -3), $text) ?? $text;
    }
}
