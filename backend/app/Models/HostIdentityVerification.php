<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HostVerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostIdentityVerification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'host_identity_verifications';

    protected $fillable = [
        'user_id',
        'host_application_id',
        'status',
        'document_type',
        'document_type_other_note',
        'legal_full_name',
        'issuing_country',
        'document_last4',
        'document_expires_at',
        'private_storage_disk',
        'encrypted_storage_path',
        'original_mime_type',
        'original_size_bytes',
        'document_sha256',
        'encryption_cipher',
        'malware_scan_status',
        'replacement_count',
        'submitted_at_utc',
        'reviewed_by',
        'reviewed_at_utc',
        'review_notes',
        'rejection_or_info_reason',
        'consent_accepted_at_utc',
        'retention_expires_at_utc',
        'document_purged_at_utc',
        'purge_reason',
    ];

    /**
     * Never expose internal storage paths, disk names, or raw cryptographic digests in default serialization.
     */
    protected $hidden = [
        'private_storage_disk',
        'encrypted_storage_path',
        'document_sha256',
        'encryption_cipher',
    ];

    protected $casts = [
        'document_expires_at' => 'date',
        'original_size_bytes' => 'integer',
        'replacement_count' => 'integer',
        'submitted_at_utc' => 'datetime',
        'reviewed_at_utc' => 'datetime',
        'consent_accepted_at_utc' => 'datetime',
        'retention_expires_at_utc' => 'datetime',
        'document_purged_at_utc' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hostApplication(): BelongsTo
    {
        return $this->belongsTo(HostApplication::class, 'host_application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusEnum(): HostVerificationStatus
    {
        return HostVerificationStatus::tryFrom((string) $this->status) ?? HostVerificationStatus::NOT_SUBMITTED;
    }

    public function statusLabel(): string
    {
        return $this->statusEnum()->label();
    }

    public function documentTypeLabel(): string
    {
        $types = (array) config('compliance.host_verification.supported_document_types', []);
        return (string) ($types[$this->document_type] ?? $this->document_type ?? 'Unspecified');
    }

    public function hasStoredDocument(): bool
    {
        return !empty($this->encrypted_storage_path) && is_null($this->document_purged_at_utc);
    }

    public function isDocumentExpired(): bool
    {
        if (!$this->document_expires_at) {
            return false;
        }

        return $this->document_expires_at->isPast();
    }

    public function isApprovedAndValid(): bool
    {
        return $this->status === HostVerificationStatus::APPROVED->value && !$this->isDocumentExpired();
    }
}

