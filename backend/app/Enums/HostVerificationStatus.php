<?php

declare(strict_types=1);

namespace App\Enums;

enum HostVerificationStatus: string
{
    case NOT_SUBMITTED = 'not_submitted';
    case PENDING_REVIEW = 'pending_review';
    case UNDER_REVIEW = 'under_review';
    case ADDITIONAL_INFORMATION_REQUIRED = 'additional_information_required';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case SUSPENDED = 'suspended';
    case REVERIFICATION_REQUIRED = 'reverification_required';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SUBMITTED => 'Not Submitted',
            self::PENDING_REVIEW => 'Pending Review',
            self::UNDER_REVIEW => 'Under Review',
            self::ADDITIONAL_INFORMATION_REQUIRED => 'Additional Information Required',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
            self::SUSPENDED => 'Suspended',
            self::REVERIFICATION_REQUIRED => 'Reverification Required',
        };
    }

    public function allowsEventPublishing(): bool
    {
        return $this === self::APPROVED;
    }

    public function allowsDocumentReplacement(): bool
    {
        return in_array($this, [
            self::NOT_SUBMITTED,
            self::PENDING_REVIEW,
            self::ADDITIONAL_INFORMATION_REQUIRED,
            self::REJECTED,
            self::EXPIRED,
            self::REVERIFICATION_REQUIRED,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

