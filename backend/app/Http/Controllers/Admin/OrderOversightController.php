<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Services\AuditLogService;
use App\Services\OrderOperationsService;
use App\Support\AdminAuthorization as Admin;
use App\Support\PaymentStatusPresenter as Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderOversightController extends Controller
{
    public function __construct(private readonly OrderOperationsService $operations)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize(Admin::PAYMENTS_VIEW);
        $admin = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(array_keys(Status::GROUPS))],
            'method' => ['nullable', Rule::in(['bank', 'cash', 'card', 'zelle', 'cashapp'])],
            'event' => ['nullable', 'string', 'max:36'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Admin::applyOrganizerScope(Order::allTenants(), $admin)
            ->with(['event' => fn ($q) => $q->allTenants()->select('id', 'title')]);

        if ($term = strtoupper(trim((string) ($filters['q'] ?? '')))) {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$term}%")->orWhere('ticket_code', 'like', "%{$term}%"));
        }
        if ($group = $filters['status'] ?? null) {
            $query->whereIn('status', Status::statusesFor($group));
        }
        if ($method = $filters['method'] ?? null) {
            $query->where('method', $method);
        }
        if ($eventId = $filters['event'] ?? null) {
            $query->where('event_id', $eventId);
        }
        if ($from = $filters['from'] ?? null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->where('created_at', '<', \Carbon\Carbon::parse($to)->addDay());
        }

        return view('admin.orders.index', [
            'orders' => $query->latest()->paginate(25)->withQueryString(),
            'filters' => $filters,
            'groups' => Status::GROUPS,
            'events' => Admin::applyOrganizerScope(Event::allTenants(), $admin)->orderBy('title')->get(['id', 'title']),
            'canSeeCustomers' => Gate::allows(Admin::ORDER_CUSTOMER, new Order()),
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $record = $this->findOrder($order);
        Gate::authorize(Admin::ORDER_VIEW, $record);

        $revealCustomer = $request->boolean('reveal') && Gate::allows(Admin::ORDER_CUSTOMER, $record);
        if ($revealCustomer) {
            AuditLogService::log(
                action: 'pii_record_viewed',
                targetEntity: 'orders',
                targetId: (string) $record->id,
                details: "Customer contact details viewed for order {$record->order_number} in the administration console.",
                actor: $request->user(),
                isPiiAccess: true,
                organizerId: (string) $record->organizer_id,
                eventId: $record->event_id ? (string) $record->event_id : null,
            );
        }

        return view('admin.orders.show', [
            'order' => $record->load(['items', 'event' => fn ($q) => $q->allTenants()->select('id', 'title', 'organizer_name')]),
            'revealCustomer' => $revealCustomer,
            'canSeeCustomer' => Gate::allows(Admin::ORDER_CUSTOMER, $record),
            'canReview' => Gate::allows(Admin::PAYMENT_REVIEW, $record) && $record->status !== 'refunded',
        ]);
    }

    public function review(Request $request, string $order): RedirectResponse
    {
        $record = $this->findOrder($order);
        Gate::authorize(Admin::PAYMENT_REVIEW, $record);

        $data = $request->validate([
            'status' => ['required', Rule::in(OrderOperationsService::MANUAL_PAYMENT_STATUSES)],
            'amount_paid' => ['nullable', 'required_if:status,partially_paid', 'numeric', 'min:0.01'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'amount_paid.required_if' => 'Enter the amount received for a partial payment.',
            'rejection_reason.required_if' => 'Give a reason for rejecting this payment. The customer is told.',
            'confirm.accepted' => 'Tick the box to confirm this payment decision.',
        ]);

        try {
            // Same validated path the host's payment desk uses: checks capability, applies the
            // transition rules, records the audit entry and notifies the customer.
            $this->operations->updatePaymentStatus($request->user(), $record, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (SecurityException) {
            abort(403);
        }

        return back()->with('success', "Order {$record->order_number} marked " . Status::label($data['status']) . '.');
    }

    private function findOrder(string $id): Order
    {
        return Order::allTenants()->whereKey($id)->firstOrFail();
    }
}
