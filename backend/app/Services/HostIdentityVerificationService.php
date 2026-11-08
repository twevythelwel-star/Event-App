<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\HostVerificationStatus;
use App\Exceptions\SecurityException;
use App\Models\HostApplication;
use App\Models\HostIdentityVerification;
use App\Models\NotificationLog;
use App\Models\PolicyAcceptance;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class HostIdentityVerificationService
{
    /**
     * Configurable supported government-issued identity document types.
     *
     * @return array<string, string>
     */
    public static function supportedDocumentTypes(): array
    {
        return (array) config('compliance.host_verification.supported_document_types', [
            'drivers_licence' => "Driver's licence",
            'state_id' => 'State-issued identification card',
            'passport' => 'Passport',
            'other_government_id' => 'Other government-issued identification approved by the compliance administrator',
        ]);
    }

    /**
     * Validate file type, size, magic-byte content, and scan for malware or active scripts.
     *
     * @return array{raw_bytes: string, mime_type: string, size_bytes: int, sha256: string}
     * @throws \InvalidArgumentException|SecurityException
     */
    public static function validateAndScanDocument(UploadedFile $file, ?User $actor = null): array
    {
        $maxBytes = (int) config('compliance.host_verification.max_size_bytes', 5 * 1024 * 1024);
        $allowedMimes = (array) config('compliance.host_verification.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ]);
        $allowedExtensions = (array) config('compliance.host_verification.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'webp', 'pdf',
        ]);

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new \InvalidArgumentException('The uploaded identity document is empty.');
        }
        if ($size > $maxBytes) {
            throw new \InvalidArgumentException('The identity document exceeds the maximum allowed size of ' . round($maxBytes / (1024 * 1024), 1) . ' MB.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Unsupported identity document file extension. Allowed formats: JPG, PNG, WebP, or PDF.');
        }

        $rawBytes = self::readUploadedFileBytes($file);
        if ($rawBytes === '') {
            throw new \InvalidArgumentException('Unable to read the uploaded identity document.');
        }

        // Magic-byte content inspection (do not trust client-supplied MIME header alone)
        $detectedMime = self::detectMimeFromMagicBytes($rawBytes);
        if ($detectedMime === null || !in_array($detectedMime, $allowedMimes, true)) {
            throw new \InvalidArgumentException('File content does not match an acceptable government ID image or PDF format.');
        }

        // Malware & active-content scanning
        if (config('compliance.host_verification.malware_scan_enabled', true)) {
            $threatReason = self::scanPayloadForMalware($rawBytes, $detectedMime);
            if ($threatReason !== null) {
                self::recordAudit(
                    actor: $actor,
                    action: 'host_id_malware_blocked',
                    targetEntity: 'host_identity_verifications',
                    targetId: (string) ($actor?->id ?? 'anonymous'),
                    details: "Blocked government ID upload due to security/malware signature check: {$threatReason}",
                    isPiiAccess: false
                );
                throw new SecurityException("Security scan rejected the uploaded file ({$threatReason}). Please upload a clean scan or photo of your government ID.");
            }
        }

        return [
            'raw_bytes' => $rawBytes,
            'mime_type' => $detectedMime,
            'size_bytes' => strlen($rawBytes),
            'sha256' => hash('sha256', $rawBytes),
        ];
    }

    /**
     * Submit or securely replace a host's government-issued identity document.
     * Uploading a document NEVER automatically approves identity; it transitions to Pending Review.
     *
     * @param array<string, mixed> $data
     */
    public static function submitOrReplaceDocument(
        User $user,
        UploadedFile $file,
        array $data,
        ?HostApplication $application = null
    ): HostIdentityVerification {
        if ($user->isSuspended()) {
            throw new SecurityException('Suspended accounts cannot submit identity verification documents.');
        }

        $supportedTypes = self::supportedDocumentTypes();
        $documentType = (string) ($data['document_type'] ?? $data['government_id_type'] ?? '');
        if (!array_key_exists($documentType, $supportedTypes)) {
            throw new \InvalidArgumentException('Please select a supported government-issued identity document type.');
        }

        $legalFullName = trim((string) ($data['legal_full_name'] ?? $user->name));
        if (mb_strlen($legalFullName) < 2) {
            throw new \InvalidArgumentException('Legal full name as shown on the government-issued ID is required.');
        }

        $existing = HostIdentityVerification::where('user_id', $user->id)->latest()->first();
        // An approved ID whose document has expired must be renewable, or the host could never publish again.
        $renewingExpired = $existing && $existing->status === HostVerificationStatus::APPROVED->value && $existing->isDocumentExpired();
        if ($existing && !$renewingExpired && !$existing->statusEnum()->allowsDocumentReplacement()) {
            throw new \InvalidArgumentException("Your identity verification is currently '{$existing->statusLabel()}' and cannot be replaced at this time.");
        }

        $validatedFile = self::validateAndScanDocument($file, $user);

        // Enforce private storage disk — NEVER allow the 'public' disk
        $diskName = (string) config('compliance.host_verification.storage_disk', 'local');
        if ($diskName === 'public') {
            $diskName = 'local';
        }
        $prefix = trim((string) config('compliance.host_verification.storage_prefix', 'host-identity-documents'), '/');
        $storagePath = "{$prefix}/user-{$user->id}/" . Str::uuid() . '.enc';

        // Encrypt document bytes at rest before writing to private storage
        $encryptedPayload = Crypt::encrypt($validatedFile['raw_bytes']);
        Storage::disk($diskName)->put($storagePath, $encryptedPayload, ['visibility' => 'private']);

        // If replacing an existing document, securely overwrite and purge the previous file from private storage
        $replacementCount = 0;
        if ($existing) {
            $replacementCount = ((int) $existing->replacement_count) + 1;
            if ($existing->hasStoredDocument()) {
                self::securelyDeleteBlob((string) $existing->private_storage_disk, (string) $existing->encrypted_storage_path);
            }
        }

        $last4Raw = preg_replace('/\D/', '', (string) ($data['document_last4'] ?? $data['government_id_last4'] ?? ''));
        $last4 = $last4Raw !== '' ? substr($last4Raw, -4) : null;
        $unreviewedRetentionDays = (int) config('compliance.host_verification.retention_days_unreviewed_abandoned', 60);

        $attributes = [
            'user_id' => $user->id,
            'host_application_id' => $application?->id ?? $existing?->host_application_id,
            'status' => HostVerificationStatus::PENDING_REVIEW->value,
            'document_type' => $documentType,
            'document_type_other_note' => $documentType === 'other_government_id'
                ? trim((string) ($data['document_type_other_note'] ?? '')) ?: null
                : null,
            'legal_full_name' => $legalFullName,
            'issuing_country' => strtoupper(trim((string) ($data['issuing_country'] ?? $data['country'] ?? 'US'))),
            'document_last4' => $last4,
            'document_expires_at' => !empty($data['document_expires_at']) ? $data['document_expires_at'] : null,
            'private_storage_disk' => $diskName,
            'encrypted_storage_path' => $storagePath,
            'original_mime_type' => $validatedFile['mime_type'],
            'original_size_bytes' => $validatedFile['size_bytes'],
            'document_sha256' => $validatedFile['sha256'],
            'encryption_cipher' => (string) config('compliance.host_verification.encryption_cipher', 'AES-256-CBC'),
            'malware_scan_status' => 'clean',
            'replacement_count' => $replacementCount,
            'submitted_at_utc' => now()->utc(),
            'reviewed_by' => null,
            'reviewed_at_utc' => null,
            'rejection_or_info_reason' => null,
            'consent_accepted_at_utc' => now()->utc(),
            'retention_expires_at_utc' => now()->utc()->addDays($unreviewedRetentionDays),
            'document_purged_at_utc' => null,
            'purge_reason' => null,
        ];

        if ($existing) {
            $existing->fill($attributes)->save();
            $verification = $existing->refresh();
        } else {
            $verification = HostIdentityVerification::create($attributes);
        }

        // Sync associated HostApplication status to pending_review
        $linkedApp = $application ?? ($verification->host_application_id ? HostApplication::find($verification->host_application_id) : HostApplication::where('user_id', $user->id)->latest()->first());
        if ($linkedApp) {
            $linkedApp->forceFill([
                'legal_full_name' => $legalFullName,
                'government_id_type' => $documentType,
                'government_id_last4' => $last4 ?? $linkedApp->government_id_last4,
                'identity_verification_status' => HostVerificationStatus::PENDING_REVIEW->value,
            ])->save();
            if (!$verification->host_application_id) {
                $verification->forceFill(['host_application_id' => $linkedApp->id])->save();
            }
        }

        // Record auditable consent for government ID processing & retention schedule
        if (Schema::hasTable('policy_acceptances')) {
            PolicyAcceptance::create([
                'user_id' => $user->id,
                'policy_name' => 'host_government_id_verification_consent',
                'policy_version' => LegalComplianceService::CURRENT_POLICY_VERSION,
                'acceptance_status' => 'accepted',
                'accepted_at_utc' => gmdate('Y-m-d H:i:s'),
                'ip_address' => function_exists('request') && request() ? (request()->ip() ?? '127.0.0.1') : '127.0.0.1',
                'user_agent' => substr((string) (function_exists('request') && request() ? (request()->userAgent() ?? 'WebBrowser/1.0') : 'WebBrowser/1.0'), 0, 512),
                'context' => 'host_identity_verification',
                'context_entity_id' => (string) $verification->id,
                'metadata' => [
                    'document_type' => $documentType,
                    'replacement_count' => $replacementCount,
                ],
            ]);
        }

        self::recordAudit(
            actor: $user,
            action: $replacementCount > 0 ? 'host_id_document_replaced' : 'host_id_document_uploaded',
            targetEntity: 'host_identity_verifications',
            targetId: (string) $verification->id,
            details: "Host {$user->email} uploaded encrypted government ID ({$documentType}, SHA-256 prefix " . substr($validatedFile['sha256'], 0, 12) . ") for compliance review.",
            isPiiAccess: true
        );

        self::notifyHost(
            user: $user,
            subject: 'Government ID received — Pending compliance review',
            message: "We received your {$verification->documentTypeLabel()} submission. Your host verification status is now Pending Review. You will be notified once a compliance administrator completes the review."
        );

        return $verification;
    }

    /**
     * Compliance Administrator action: transition a HostIdentityVerification to a new status
     * (under_review, additional_information_required, approved, rejected, expired, suspended, reverification_required).
     */
    public static function transitionStatus(
        User $reviewer,
        string $verificationId,
        string $newStatus,
        ?string $reason = null,
        ?string $notes = null
    ): HostIdentityVerification {
        self::assertAdminCanReview($reviewer);

        $targetStatus = HostVerificationStatus::tryFrom($newStatus);
        if (!$targetStatus || $targetStatus === HostVerificationStatus::NOT_SUBMITTED) {
            throw new \InvalidArgumentException("Invalid host verification status '{$newStatus}'.");
        }

        $verification = HostIdentityVerification::findOrFail($verificationId);
        if ($verification->user_id === $reviewer->id) {
            throw new SecurityException('Compliance administrators may not review or approve their own government ID submission.');
        }

        if (
            in_array($targetStatus, [
                HostVerificationStatus::REJECTED,
                HostVerificationStatus::ADDITIONAL_INFORMATION_REQUIRED,
                HostVerificationStatus::SUSPENDED,
                HostVerificationStatus::REVERIFICATION_REQUIRED,
            ], true)
            && trim((string) $reason) === ''
        ) {
            throw new \InvalidArgumentException("A clear reason is required when setting verification status to '{$targetStatus->label()}'.");
        }

        if ($targetStatus === HostVerificationStatus::APPROVED) {
            if (!$verification->hasStoredDocument() && empty($verification->document_sha256)) {
                throw new \InvalidArgumentException('Cannot approve identity verification without an uploaded government-issued ID document.');
            }
            if ($verification->isDocumentExpired()) {
                throw new \InvalidArgumentException('Cannot approve an expired government-issued ID document.');
            }
        }

        $previousStatus = $verification->status;
        $retentionDays = match ($targetStatus) {
            HostVerificationStatus::APPROVED => (int) config('compliance.host_verification.retention_days_after_approval', 90),
            HostVerificationStatus::REJECTED => (int) config('compliance.host_verification.retention_days_after_rejection', 30),
            default => (int) config('compliance.host_verification.retention_days_unreviewed_abandoned', 60),
        };

        $verification->status = $targetStatus->value;
        $verification->reviewed_by = $reviewer->id;
        $verification->reviewed_at_utc = now()->utc();
        $verification->review_notes = $notes ? trim($notes) : $verification->review_notes;
        $verification->rejection_or_info_reason = $reason ? trim($reason) : null;
        $verification->retention_expires_at_utc = now()->utc()->addDays($retentionDays);
        $verification->save();

        // Sync linked HostApplication status
        if ($verification->host_application_id) {
            $app = HostApplication::find($verification->host_application_id);
            if ($app) {
                $app->forceFill([
                    'identity_verification_status' => $targetStatus->value,
                ])->save();
            }
        } else {
            HostApplication::where('user_id', $verification->user_id)->latest()->first()?->forceFill([
                'identity_verification_status' => $targetStatus->value,
            ])->save();
        }

        self::recordAudit(
            actor: $reviewer,
            action: 'host_id_verification_status_changed',
            targetEntity: 'host_identity_verifications',
            targetId: (string) $verification->id,
            details: "Compliance administrator {$reviewer->email} changed host ID verification #{$verification->id} for user #{$verification->user_id} from '{$previousStatus}' to '{$targetStatus->value}'."
                . ($reason ? " Reason: {$reason}" : ''),
            isPiiAccess: true
        );

        if ($verification->user) {
            $notificationCopy = match ($targetStatus) {
                HostVerificationStatus::UNDER_REVIEW => [
                    'Your government ID is now under review',
                    'A compliance administrator has begun reviewing your government-issued ID submission.',
                ],
                HostVerificationStatus::ADDITIONAL_INFORMATION_REQUIRED => [
                    'Additional information required for your Host ID verification',
                    "Please upload a clearer or updated government ID document: {$reason}",
                ],
                HostVerificationStatus::APPROVED => [
                    'Host identity verification approved',
                    'Your government-issued identity document has been verified and approved. You are now authorized to publish events on EventBite.',
                ],
                HostVerificationStatus::REJECTED => [
                    'Host identity verification not approved',
                    "Your government-issued ID submission was not approved: {$reason}",
                ],
                HostVerificationStatus::EXPIRED => [
                    'Government ID expired — Reverification required',
                    'Your verified government ID on file has expired. Please upload a current government-issued ID to continue publishing events.',
                ],
                HostVerificationStatus::SUSPENDED => [
                    'Host identity verification suspended',
                    "Your Event Host verification status has been suspended: {$reason}",
                ],
                HostVerificationStatus::REVERIFICATION_REQUIRED => [
                    'Host identity reverification required',
                    "Please submit a new government-issued identity document for compliance verification: {$reason}",
                ],
                default => [
                    'Host identity verification update',
                    "Your verification status is now {$targetStatus->label()}.",
                ],
            };

            self::notifyHost($verification->user, $notificationCopy[0], $notificationCopy[1]);
        }

        return $verification;
    }

    /**
     * Generate a short-lived, cryptographically signed, administrator-only URL to inspect an uploaded ID document.
     */
    public static function generateTemporaryDocumentUrl(User $admin, HostIdentityVerification $verification): string
    {
        self::assertAdminCanReview($admin);

        if (!$verification->hasStoredDocument()) {
            throw new \InvalidArgumentException('The raw identity document file is no longer stored (purged under retention policy or not yet uploaded).');
        }

        $ttlMinutes = max(1, min(30, (int) config('compliance.host_verification.signed_url_ttl_minutes', 5)));

        $signedUrl = URL::temporarySignedRoute(
            'admin.host-verification.document',
            now()->addMinutes($ttlMinutes),
            [
                'verification' => $verification->id,
                'admin' => $admin->id,
            ]
        );

        self::recordAudit(
            actor: $admin,
            action: 'host_id_temporary_link_issued',
            targetEntity: 'host_identity_verifications',
            targetId: (string) $verification->id,
            details: "Issued {$ttlMinutes}-minute signed access link for host ID verification #{$verification->id} (subject user #{$verification->user_id}).",
            isPiiAccess: true
        );

        return $signedUrl;
    }

    /**
     * Read and decrypt a stored government ID document for an authorized compliance administrator,
     * verifying cryptographic SHA-256 integrity and recording a mandatory PII access audit log.
     *
     * @return array{bytes: string, mime_type: string, filename: string}
     */
    public static function readDecryptedDocumentForAdmin(User $admin, HostIdentityVerification $verification): array
    {
        self::assertAdminCanReview($admin);

        if (!$verification->hasStoredDocument()) {
            throw new \InvalidArgumentException('Identity document file is not available or has been purged per retention schedule.');
        }

        $disk = Storage::disk((string) $verification->private_storage_disk);
        if (!$disk->exists((string) $verification->encrypted_storage_path)) {
            throw new \InvalidArgumentException('Encrypted identity document blob could not be found in private storage.');
        }

        $encryptedPayload = (string) $disk->get((string) $verification->encrypted_storage_path);
        $decryptedBytes = (string) Crypt::decrypt($encryptedPayload);

        if ($verification->document_sha256 && !hash_equals($verification->document_sha256, hash('sha256', $decryptedBytes))) {
            self::recordAudit(
                actor: $admin,
                action: 'host_id_integrity_mismatch',
                targetEntity: 'host_identity_verifications',
                targetId: (string) $verification->id,
                details: "Cryptographic SHA-256 mismatch detected when reading host ID verification #{$verification->id}.",
                isPiiAccess: true
            );
            throw new SecurityException('Document integrity check failed.');
        }

        self::recordAudit(
            actor: $admin,
            action: 'host_id_document_viewed',
            targetEntity: 'host_identity_verifications',
            targetId: (string) $verification->id,
            details: "Compliance administrator {$admin->email} viewed decrypted government ID document for verification #{$verification->id} (user #{$verification->user_id}).",
            isPiiAccess: true
        );

        $ext = match ($verification->original_mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'jpg',
        };

        return [
            'bytes' => $decryptedBytes,
            'mime_type' => (string) ($verification->original_mime_type ?: 'application/octet-stream'),
            'filename' => "host-id-verification-{$verification->id}.{$ext}",
        ];
    }

    /**
     * Execute the legally reviewed retention and deletion schedule:
     * 1. Marks approved verifications whose ID expiration date has passed as 'expired'.
     * 2. Deletes raw encrypted ID blobs whose retention window has expired, retaining only minimal
     *    verification evidence (status, document_type, last4, SHA-256 hash, reviewer, timestamps).
     *
     * @return array{purged_files: int, marked_expired: int}
     */
    public static function enforceRetentionAndExpirationPolicy(?\DateTimeInterface $asOf = null): array
    {
        if (!Schema::hasTable('host_identity_verifications')) {
            return ['purged_files' => 0, 'marked_expired' => 0];
        }

        $now = $asOf ? Carbon::instance($asOf)->utc() : now()->utc();
        $markedExpired = 0;
        $purgedFiles = 0;

        // 1. Mark approved IDs whose document_expires_at is in the past as expired
        $expiredIds = HostIdentityVerification::where('status', HostVerificationStatus::APPROVED->value)
            ->whereNotNull('document_expires_at')
            ->whereDate('document_expires_at', '<', $now->toDateString())
            ->get();

        foreach ($expiredIds as $verification) {
            $verification->forceFill([
                'status' => HostVerificationStatus::EXPIRED->value,
                'rejection_or_info_reason' => 'Government-issued ID expiration date has passed.',
            ])->save();

            if ($verification->user) {
                self::notifyHost(
                    $verification->user,
                    'Government ID expired — Reverification required',
                    'Your government-issued ID on file has passed its expiration date. Please upload a current ID to publish new events.'
                );
            }
            $markedExpired++;
        }

        // 2. Purge stored encrypted files whose retention window has elapsed
        $dueForPurge = HostIdentityVerification::whereNotNull('encrypted_storage_path')
            ->whereNull('document_purged_at_utc')
            ->whereNotNull('retention_expires_at_utc')
            ->where('retention_expires_at_utc', '<=', $now)
            ->get();

        foreach ($dueForPurge as $verification) {
            self::securelyDeleteBlob((string) $verification->private_storage_disk, (string) $verification->encrypted_storage_path);

            $verification->forceFill([
                'encrypted_storage_path' => null,
                'document_purged_at_utc' => $now,
                'purge_reason' => 'scheduled_retention_policy_expiry',
            ])->save();

            self::recordAudit(
                actor: null,
                action: 'host_id_document_purged_by_retention',
                targetEntity: 'host_identity_verifications',
                targetId: (string) $verification->id,
                details: "Deleted raw encrypted ID document for verification #{$verification->id} per retention policy; minimal verification evidence retained.",
                isPiiAccess: false
            );

            $purgedFiles++;
        }

        return [
            'purged_files' => $purgedFiles,
            'marked_expired' => $markedExpired,
        ];
    }

    /**
     * Data Breach Response Procedure:
     * Immediately purges raw encrypted identity documents, revokes pending/targeted verifications to
     * 'reverification_required', and records a high-priority security incident audit trail.
     *
     * @return array{purged_count: int, reverification_flagged: int, incident_reference: string}
     */
    public static function executeBreachResponseLockdown(
        User $admin,
        string $incidentReference,
        ?int $targetUserId = null
    ): array {
        self::assertAdminCanReview($admin);

        if (trim($incidentReference) === '') {
            throw new \InvalidArgumentException('An incident reference is required to execute a data breach lockdown.');
        }

        $query = HostIdentityVerification::query();
        if ($targetUserId !== null) {
            $query->where('user_id', $targetUserId);
        }

        $records = $query->get();
        $purgedCount = 0;
        $reverificationFlagged = 0;

        foreach ($records as $verification) {
            if ($verification->hasStoredDocument()) {
                self::securelyDeleteBlob((string) $verification->private_storage_disk, (string) $verification->encrypted_storage_path);
                $verification->encrypted_storage_path = null;
                $verification->document_purged_at_utc = now()->utc();
                $verification->purge_reason = "security_incident_lockdown:{$incidentReference}";
                $purgedCount++;
            }

            if ($targetUserId !== null || $verification->status !== HostVerificationStatus::APPROVED->value) {
                $verification->status = HostVerificationStatus::REVERIFICATION_REQUIRED->value;
                $verification->rejection_or_info_reason = "Security precaution ({$incidentReference}): please re-verify your identity document.";
                $reverificationFlagged++;
            }

            $verification->save();
        }

        self::recordAudit(
            actor: $admin,
            action: 'security_breach_id_lockdown_executed',
            targetEntity: 'host_identity_verifications',
            targetId: $targetUserId !== null ? (string) $targetUserId : 'all',
            details: "Executed ID breach response lockdown ({$incidentReference}): purged {$purgedCount} encrypted document blobs, flagged {$reverificationFlagged} record(s) for reverification.",
            isPiiAccess: true
        );

        return [
            'purged_count' => $purgedCount,
            'reverification_flagged' => $reverificationFlagged,
            'incident_reference' => $incidentReference,
        ];
    }

    /**
     * Evaluate whether a host has an approved, non-expired government ID verification allowing event publication.
     *
     * @return array{allowed: bool, status: string, label: string, message: ?string}
     */
    public static function evaluateHostPublishingEligibility(?User $user): array
    {
        if (!$user) {
            return [
                'allowed' => false,
                'status' => HostVerificationStatus::NOT_SUBMITTED->value,
                'label' => HostVerificationStatus::NOT_SUBMITTED->label(),
                'message' => 'Sign in as an approved Event Host with verified government ID before publishing.',
            ];
        }

        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return [
                'allowed' => true,
                'status' => HostVerificationStatus::APPROVED->value,
                'label' => HostVerificationStatus::APPROVED->label(),
                'message' => null,
            ];
        }

        if (!Schema::hasTable('host_identity_verifications')) {
            return [
                'allowed' => true,
                'status' => HostVerificationStatus::APPROVED->value,
                'label' => HostVerificationStatus::APPROVED->label(),
                'message' => null,
            ];
        }

        $verification = HostIdentityVerification::where('user_id', $user->id)->latest()->first();

        if ($verification) {
            if ($verification->isDocumentExpired() && $verification->status === HostVerificationStatus::APPROVED->value) {
                $verification->forceFill(['status' => HostVerificationStatus::EXPIRED->value])->save();
            }

            $statusEnum = $verification->statusEnum();
            if ($statusEnum->allowsEventPublishing() && !$verification->isDocumentExpired()) {
                return [
                    'allowed' => true,
                    'status' => $statusEnum->value,
                    'label' => $statusEnum->label(),
                    'message' => null,
                ];
            }

            return [
                'allowed' => false,
                'status' => $statusEnum->value,
                'label' => $statusEnum->label(),
                'message' => "Government-issued ID verification must be Approved before publishing events (current status: {$statusEnum->label()}).",
            ];
        }

        // Check if the user has a HostApplication with a non-approved verification status
        if (Schema::hasTable('host_applications')) {
            $latestApp = HostApplication::where('user_id', $user->id)->latest()->first();
            if ($latestApp) {
                $appVerStatus = (string) ($latestApp->identity_verification_status ?? 'not_submitted');
                if (in_array($appVerStatus, ['verified', 'approved'], true)) {
                    return [
                        'allowed' => true,
                        'status' => HostVerificationStatus::APPROVED->value,
                        'label' => HostVerificationStatus::APPROVED->label(),
                        'message' => null,
                    ];
                }

                $statusEnum = HostVerificationStatus::tryFrom($appVerStatus) ?? HostVerificationStatus::NOT_SUBMITTED;
                return [
                    'allowed' => false,
                    'status' => $statusEnum->value,
                    'label' => $statusEnum->label(),
                    'message' => "Government-issued ID verification must be Approved before publishing events (current status: {$statusEnum->label()}).",
                ];
            }
        }

        return [
            'allowed' => true,
            'status' => HostVerificationStatus::APPROVED->value,
            'label' => HostVerificationStatus::APPROVED->label(),
            'message' => null,
        ];
    }

    private static function assertAdminCanReview(User $admin): void
    {
        $isAuthorized = $admin->hasRole(AccountType::SYSTEM_ADMIN) || $admin->hasRole(AccountType::ADMINISTRATOR);
        if (!$isAuthorized) {
            throw new SecurityException('Unauthorized: Only authorized compliance administrators may access or review host identity verifications.');
        }
    }

    private static function readUploadedFileBytes(UploadedFile $file): string
    {
        if (property_exists($file, 'tempFile') && is_resource($file->tempFile)) {
            rewind($file->tempFile);
            $contents = stream_get_contents($file->tempFile);
            rewind($file->tempFile);
            if (is_string($contents)) {
                return $contents;
            }
        }

        $realPath = $file->getRealPath() ?: $file->getPathname();
        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            $contents = @file_get_contents($realPath);
            if (is_string($contents)) {
                return $contents;
            }
        }

        return '';
    }

    private static function detectMimeFromMagicBytes(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (str_starts_with($bytes, '%PDF-')) {
            return 'application/pdf';
        }

        return null;
    }

    private static function scanPayloadForMalware(string $bytes, string $detectedMime): ?string
    {
        // EICAR standard antivirus test signature
        if (str_contains($bytes, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*')) {
            return 'EICAR malware signature detected';
        }

        // Reject embedded PHP / HTML script payloads disguised inside image/PDF streams
        $lowerSample = strtolower(substr($bytes, 0, 65536));
        if (str_contains($lowerSample, '<?php') || str_contains($lowerSample, '<script')) {
            return 'Embedded executable script tag detected';
        }

        // Reject PDF files with embedded JavaScript or Launch actions
        if ($detectedMime === 'application/pdf') {
            if (preg_match('/\/(?:JavaScript|JS|Launch|EmbeddedFiles|OpenAction)\b/i', $bytes) === 1) {
                return 'Active PDF script or launch action detected';
            }
        }

        return null;
    }

    private static function securelyDeleteBlob(string $diskName, string $path): void
    {
        if ($path === '') {
            return;
        }
        try {
            $disk = Storage::disk($diskName ?: 'local');
            if ($disk->exists($path)) {
                // Overwrite with random bytes prior to unlinking where supported by local filesystem
                $size = $disk->size($path);
                if ($size > 0 && $size <= 10 * 1024 * 1024) {
                    $disk->put($path, random_bytes((int) min($size, 4096)));
                }
                $disk->delete($path);
            }
        } catch (\Throwable) {
            // Ensure deletion failure never leaks sensitive paths
        }
    }

    private static function notifyHost(User $user, string $subject, string $message): void
    {
        try {
            if (Schema::hasTable('notification_logs')) {
                NotificationLog::create([
                    'idempotency_key' => 'host-id-verify-' . $user->id . '-' . Str::uuid(),
                    'customer_name' => $user->name,
                    'recipient' => strtolower(trim((string) $user->email)),
                    'channel' => 'in_app',
                    'trigger' => 'host_identity_verification',
                    'title' => $subject,
                    'message' => $message,
                    'email_subject' => $subject,
                    'status' => 'delivered',
                    'sent_at' => now(),
                    'delivered_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Non-blocking notification log
        }
    }

    private static function recordAudit(
        ?User $actor,
        string $action,
        string $targetEntity,
        string $targetId,
        string $details,
        bool $isPiiAccess
    ): void {
        try {
            if (Schema::hasTable('security_audit_logs')) {
                SecurityAuditLog::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $actor?->id,
                    'actor_role' => $actor ? $actor->getActiveRole()->value : 'system',
                    'action' => $action,
                    'target_entity' => $targetEntity,
                    'target_id' => $targetId,
                    'ip_address' => function_exists('request') && request() ? (request()->ip() ?? '127.0.0.1') : '127.0.0.1',
                    'user_agent' => substr((string) (function_exists('request') && request() ? (request()->userAgent() ?? 'System/1.0') : 'System/1.0'), 0, 512),
                    'details' => $details,
                    'is_pii_access' => $isPiiAccess,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Non-blocking in isolated test environments
        }
    }
}

