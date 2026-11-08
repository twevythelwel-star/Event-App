<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\HostVerificationStatus;
use App\Exceptions\SecurityException;
use App\Models\HostIdentityVerification;
use App\Services\HostIdentityVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HostIdentityVerificationController extends Controller
{
    /**
     * Return current user's host identity verification status (never exposes storage paths or document bytes).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $verification = HostIdentityVerification::where('user_id', $user->id)->latest()->first();

        return response()->json([
            'data' => $this->presentForHost($verification),
            'supported_document_types' => HostIdentityVerificationService::supportedDocumentTypes(),
            'publishing_eligibility' => HostIdentityVerificationService::evaluateHostPublishingEligibility($user),
        ]);
    }

    /**
     * Upload or replace the authenticated user's government-issued ID document.
     */
    public function store(\App\Http\Requests\Compliance\StoreIdentityDocumentRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        try {
            $verification = HostIdentityVerificationService::submitOrReplaceDocument(
                user: $request->user(),
                file: $request->file('identity_document'),
                data: $data
            );

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'data' => $this->presentForHost($verification),
                    'message' => 'Government-issued ID uploaded and queued for compliance review.',
                ], 201);
            }

            return back()->with('status', 'Your government-issued identity document has been securely stored and is now Pending Review.');
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withInput()->withErrors(['identity_document' => $e->getMessage()]);
        } catch (SecurityException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withInput()->withErrors(['security' => $e->getMessage()]);
        }
    }

    /**
     * Compliance Administrator: list host identity verifications (never includes raw storage paths or document URLs).
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        $this->authorizeComplianceAdmin($request->user());

        $status = $request->query('status');
        $query = HostIdentityVerification::with(['user', 'reviewer'])->latest();
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->limit(250)->get()->map(fn (HostIdentityVerification $v) => $this->presentForAdmin($v))->values();

        return response()->json(['data' => $items]);
    }

    /**
     * Compliance Administrator: transition verification status
     * (under_review, additional_information_required, approved, rejected, expired, suspended, reverification_required).
     */
    public function updateStatus(\App\Http\Requests\Compliance\UpdateIdentityVerificationStatusRequest $request, string $verificationId): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        try {
            $verification = HostIdentityVerificationService::transitionStatus(
                reviewer: $request->user(),
                verificationId: $verificationId,
                newStatus: $data['status'],
                reason: $data['reason'] ?? null,
                notes: $data['notes'] ?? null
            );

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'data' => $this->presentForAdmin($verification->load(['user', 'reviewer'])),
                    'message' => "Host identity verification status updated to {$verification->statusLabel()}.",
                ]);
            }

            return back()->with('status', "Host identity verification updated to {$verification->statusLabel()}.");
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['verification' => $e->getMessage()]);
        } catch (SecurityException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => $e->getMessage()], 403);
            }
            return back()->withErrors(['security' => $e->getMessage()]);
        }
    }

    /**
     * Compliance Administrator: generate a temporary, cryptographically signed URL to inspect the ID document.
     */
    public function issueTemporaryLink(Request $request, string $verificationId): JsonResponse
    {
        $this->authorizeComplianceAdmin($request->user());

        $verification = HostIdentityVerification::findOrFail($verificationId);

        try {
            $signedUrl = HostIdentityVerificationService::generateTemporaryDocumentUrl($request->user(), $verification);

            return response()->json([
                'temporary_url' => $signedUrl,
                'expires_in_minutes' => (int) config('compliance.host_verification.signed_url_ttl_minutes', 5),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Compliance Administrator: stream decrypted government ID document via valid temporary signed link.
     */
    public function viewDocument(Request $request, string $verificationId): Response|JsonResponse
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'Temporary document access link has expired or has an invalid signature.'], 403);
        }

        $user = $request->user();
        $this->authorizeComplianceAdmin($user);

        // Ensure the temporary link was issued specifically to the currently authenticated administrator
        if ((string) $request->query('admin') !== (string) $user->id) {
            return response()->json(['error' => 'Temporary document link is bound to a different administrator session.'], 403);
        }

        $verification = HostIdentityVerification::findOrFail($verificationId);

        try {
            $doc = HostIdentityVerificationService::readDecryptedDocumentForAdmin($user, $verification);

            return response($doc['bytes'], 200, [
                'Content-Type' => $doc['mime_type'],
                'Content-Disposition' => 'inline; filename="' . $doc['filename'] . '"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Compliance Administrator: trigger emergency data breach lockdown & raw document purge.
     */
    public function breachLockdown(Request $request): JsonResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('lockdown', HostIdentityVerification::class);

        $data = $request->validate([
            'incident_reference' => ['required', 'string', 'min:3', 'max:120'],
            'target_user_id' => ['nullable', 'integer'],
        ]);

        $summary = HostIdentityVerificationService::executeBreachResponseLockdown(
            admin: $request->user(),
            incidentReference: $data['incident_reference'],
            targetUserId: isset($data['target_user_id']) ? (int) $data['target_user_id'] : null
        );

        return response()->json(['data' => $summary]);
    }

    /** Identity verifications are only for administrators specifically granted identity document access. */
    private function authorizeComplianceAdmin(?object $user): void
    {
        if (!$user || !\Illuminate\Support\Facades\Gate::forUser($user)->allows('viewAny', HostIdentityVerification::class)) {
            abort(403, 'Unauthorized: only administrators granted access to host identity documents may do this.');
        }
    }

    /**
     * Safe representation for the host themselves (never exposes internal storage paths or raw hashes).
     */
    private function presentForHost(?HostIdentityVerification $verification): array
    {
        if (!$verification) {
            return [
                'status' => HostVerificationStatus::NOT_SUBMITTED->value,
                'statusLabel' => HostVerificationStatus::NOT_SUBMITTED->label(),
                'allowsEventPublishing' => false,
                'allowsDocumentReplacement' => true,
            ];
        }

        return [
            'id' => $verification->id,
            'status' => $verification->status,
            'statusLabel' => $verification->statusLabel(),
            'documentType' => $verification->document_type,
            'documentTypeLabel' => $verification->documentTypeLabel(),
            'legalFullName' => $verification->legal_full_name,
            'documentLast4' => $verification->document_last4,
            'documentExpiresAt' => $verification->document_expires_at?->toDateString(),
            'submittedAtUtc' => $verification->submitted_at_utc?->toIso8601String(),
            'reviewedAtUtc' => $verification->reviewed_at_utc?->toIso8601String(),
            'rejectionOrInfoReason' => $verification->rejection_or_info_reason,
            'allowsEventPublishing' => $verification->isApprovedAndValid(),
            'allowsDocumentReplacement' => $verification->statusEnum()->allowsDocumentReplacement(),
            'documentPurgedAtUtc' => $verification->document_purged_at_utc?->toIso8601String(),
        ];
    }

    /**
     * Safe representation for compliance administrators (never exposes raw storage path; requires temporary signed link to view).
     */
    private function presentForAdmin(HostIdentityVerification $verification): array
    {
        return array_merge($this->presentForHost($verification), [
            'userId' => $verification->user_id,
            'applicantName' => $verification->user?->name,
            'applicantEmail' => $verification->user?->email,
            'hostApplicationId' => $verification->host_application_id,
            'hasStoredDocument' => $verification->hasStoredDocument(),
            'originalMimeType' => $verification->original_mime_type,
            'originalSizeBytes' => $verification->original_size_bytes,
            'malwareScanStatus' => $verification->malware_scan_status,
            'replacementCount' => $verification->replacement_count,
            'reviewedBy' => $verification->reviewer?->name,
            'reviewNotes' => $verification->review_notes,
            'retentionExpiresAtUtc' => $verification->retention_expires_at_utc?->toIso8601String(),
            'purgeReason' => $verification->purge_reason,
        ]);
    }
}

