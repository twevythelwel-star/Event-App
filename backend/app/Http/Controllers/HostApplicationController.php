<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Exceptions\SecurityException;
use App\Models\HostApplication;
use App\Services\HostApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostApplicationController extends Controller
{
    /**
     * Show Event Host application form for an authenticated Attendee.
     */
    public function showApplyForm(Request $request): View|RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole(AccountType::EVENT_HOST)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'You already hold active Event Host privileges.'], 200);
            }
            return redirect()->route('host.dashboard')->with('status', 'You already have active Event Host privileges.');
        }

        $existingApplication = HostApplication::where('user_id', $user->id)
            ->latest()
            ->first();
        $identityVerification = \Illuminate\Support\Facades\Schema::hasTable('host_identity_verifications')
            ? \App\Models\HostIdentityVerification::where('user_id', $user->id)->latest()->first()
            : null;

        if ($request->wantsJson()) {
            return response()->json([
                'user' => $user,
                'existing_application' => $existingApplication,
                'identity_verification' => $identityVerification,
            ]);
        }

        return view('host.apply', [
            'user' => $user,
            'existingApplication' => $existingApplication,
            'identityVerification' => $identityVerification,
            'supportedDocumentTypes' => \App\Services\HostIdentityVerificationService::supportedDocumentTypes(),
        ]);
    }

    /**
     * Handle submission of a Host application.
     */
    public function submit(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['required', 'string', 'in:JM,US'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'website_or_social' => ['nullable', 'string', 'max:255'],
            'legal_full_name' => ['nullable', 'string', 'max:255'],
            'business_entity_type' => ['nullable', 'string', 'max:64'],
            'government_id_type' => ['nullable', 'string', 'max:64'],
            'government_id_last4' => ['nullable', 'string', 'max:16'],
            'document_expires_at' => ['nullable', 'date'],
            'identity_document' => ['nullable', 'file'],
            'food_handler_permit_number' => ['nullable', 'string', 'max:120'],
            'alcohol_license_number' => ['nullable', 'string', 'max:120'],
            'ack_independent_compliance' => ['sometimes', 'accepted'],
            'ack_food_safety_temperatures' => ['sometimes', 'accepted'],
            'ack_allergen_disclosure' => ['sometimes', 'accepted'],
            'ack_permits_and_inspections' => ['sometimes', 'accepted'],
            'ack_alcohol_compliance' => ['sometimes', 'accepted'],
            'ack_identity_accountability' => ['sometimes', 'accepted'],
            'enforce_host_compliance' => ['nullable', 'boolean'],
        ]);

        try {
            $application = HostApplicationService::submitApplication($request->user(), $validated);
            if ($request->hasFile('identity_document')) {
                \App\Services\HostIdentityVerificationService::submitOrReplaceDocument(
                    user: $request->user(),
                    file: $request->file('identity_document'),
                    data: $validated,
                    application: $application
                );
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Your Event Host application has been submitted and is under review.',
                    'application' => $application,
                ], 201);
            }

            return redirect()->route('attendee.dashboard')->with(
                'status',
                "Your Event Host application for '{$application->organization_name}' has been submitted and is currently under review by our operations team."
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withInput()->withErrors(['application' => $e->getMessage()]);
        } catch (SecurityException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 403);
            }
            return back()->withInput()->withErrors(['security' => $e->getMessage()]);
        }
    }

    /**
     * Administrator Desk: Review pending Host applications.
     */
    public function indexAdmin(Request $request): View|JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole(AccountType::ADMINISTRATOR) && !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            abort(403, 'Unauthorized access to administrator desk.');
        }

        $pending = HostApplicationService::getPendingApplications();
        // Identity submissions only for administrators specifically granted identity document access.
        $identityVerifications = \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\HostIdentityVerification::class)
            && \Illuminate\Support\Facades\Schema::hasTable('host_identity_verifications')
            ? \App\Models\HostIdentityVerification::with(['user', 'reviewer'])
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get()
            : collect();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count' => $pending->count(),
                'applications' => $pending,
                'identity_verifications' => $identityVerifications,
            ]);
        }

        return view('admin.host-applications', [
            'pendingApplications' => $pending,
            'identityVerifications' => $identityVerifications,
            'user' => $user,
        ]);
    }

    /**
     * Administrator Action: Approve Host application.
     */
    public function approve(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        try {
            $application = HostApplicationService::approveApplication(
                reviewer: $request->user(),
                applicationId: $id,
                notes: $request->input('notes')
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Application #{$application->id} approved. Event Host role granted.",
                    'application' => $application,
                ]);
            }

            return back()->with('status', "Host application for '{$application->organization_name}' has been approved successfully.");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
            return back()->withErrors(['approval' => $e->getMessage()]);
        }
    }

    /**
     * Administrator Action: Reject Host application.
     */
    public function reject(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $application = HostApplicationService::rejectApplication(
                reviewer: $request->user(),
                applicationId: $id,
                reason: $validated['reason'],
                notes: $validated['notes'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Application #{$application->id} rejected.",
                    'application' => $application,
                ]);
            }

            return back()->with('status', "Host application for '{$application->organization_name}' has been rejected.");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
            return back()->withErrors(['rejection' => $e->getMessage()]);
        }
    }
}
