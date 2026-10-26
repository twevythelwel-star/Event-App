<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Models\HostApplication;
use App\Models\TicketDispute;
use App\Services\DisputeService;
use App\Services\HostApplicationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Event Host applications and ticket disputes: attendees submit, administrators review.
 */
class SupportRequestsController extends Controller
{
    public function __construct(
        protected DisputeService $disputes
    ) {}

    // ---- Event Host applications ------------------------------------------------------------

    public function submitHostApplication(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['required', Rule::in(['JM', 'US'])],
            'contact_phone' => ['required', 'string', 'max:32'],
            'website_or_social' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->run(fn () => response()->json(
            ['data' => $this->presentApplication(HostApplicationService::submitApplication($request->user(), $data))],
            201
        ));
    }

    public function myHostApplications(Request $request): JsonResponse
    {
        $applications = HostApplication::where('user_id', $request->user()->id)->latest()->get();

        return response()->json(['data' => $applications->map(fn ($a) => $this->presentApplication($a))->values()]);
    }

    public function adminHostApplications(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $query = HostApplication::with(['user', 'reviewer'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->limit(500)->get()->map(fn ($a) => $this->presentApplication($a, true))->values()]);
    }

    public function approveHostApplication(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        return $this->run(fn () => response()->json([
            'data' => $this->presentApplication(
                HostApplicationService::approveApplication($request->user(), $id, $data['notes'] ?? null)->load(['user', 'reviewer']),
                true
            ),
        ]));
    }

    public function rejectHostApplication(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(fn () => response()->json([
            'data' => $this->presentApplication(
                HostApplicationService::rejectApplication($request->user(), $id, $data['reason'], $data['notes'] ?? null)->load(['user', 'reviewer']),
                true
            ),
        ]));
    }

    // ---- Ticket disputes ----------------------------------------------------------------------

    public function submitDispute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'dispute_type' => ['nullable', Rule::in(TicketDispute::TYPES)],
            'reason' => ['required', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->run(fn () => response()->json(
            ['data' => DisputeService::present($this->disputes->submit($request->user(), $data))],
            201
        ));
    }

    public function myDisputes(Request $request): JsonResponse
    {
        $rows = $this->disputes->mine($request->user())->get();

        return response()->json(['data' => $rows->map(fn ($d) => DisputeService::present($d))->values()]);
    }

    public function adminDisputes(Request $request): JsonResponse
    {
        $rows = $this->disputes->adminQuery($request->user())->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($d) => DisputeService::present($d))->values()]);
    }

    public function updateDispute(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->run(fn () => response()->json([
            'data' => DisputeService::present($this->disputes->updateStatus($request->user(), $id, $data['status'], $data['notes'] ?? null)),
        ]));
    }

    // ---- Helpers ------------------------------------------------------------------------------

    private function run(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (ModelNotFoundException) {
            // Laravel's default message names internal model classes
            return response()->json(['error' => 'Not found.'], 404);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function presentApplication(HostApplication $application, bool $forAdmin = false): array
    {
        $data = [
            'id' => $application->id,
            'organizationName' => $application->organization_name,
            'description' => $application->description,
            'country' => $application->country,
            'contactPhone' => $application->contact_phone,
            'websiteOrSocial' => $application->website_or_social,
            'status' => $application->status,
            'rejectionReason' => $application->rejection_reason,
            'reviewedAt' => $application->reviewed_at?->toIso8601String(),
            'createdAt' => $application->created_at?->toIso8601String(),
        ];

        if ($forAdmin) {
            $data['applicantName'] = $application->user?->name;
            $data['applicantEmail'] = $application->user?->email;
            $data['reviewedBy'] = $application->reviewer?->name;
            $data['reviewNotes'] = $application->review_notes;
        }

        return $data;
    }
}
