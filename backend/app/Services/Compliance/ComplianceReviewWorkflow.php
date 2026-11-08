<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\ComplianceAuthority;
use App\Models\Compliance\ComplianceRecordReview;
use App\Models\Compliance\ComplianceRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * How a compliance administrator researches, reviews, approves and updates jurisdiction records.
 *
 *   verification_required ─edit→ draft ─submit→ pending_review ─approve→ verified ─(next review date passes)→ review_due
 *                                   ↑                │                        │
 *                                   └─request changes┘ (changes_requested)    └─edit→ draft (needs approval again)
 *
 * - Submitting needs an official government source (https, a .gov, .mil or .us site, or one the administrator
 *   confirms is the agency's own official site), the date it was checked and, for a requirement, its summary.
 * - Approval is by a second administrator: nobody approves a record they submitted (config compliance.database).
 * - Any change to an approved record's content sends it back to draft, so it is never shown as verified unreviewed.
 * - Every step is kept in compliance_record_reviews with who, when, the comment and a snapshot.
 */
final class ComplianceReviewWorkflow
{
    public const CONTENT_FIELDS = [
        'authority' => ['area', 'role', 'name', 'website_url', 'legislation_url', 'legislation_title'],
        'requirement' => ['authority_id', 'summary', 'value', 'conditions', 'required_documents', 'legislation_citation'],
    ];

    public const SOURCE_FIELDS = ['source_url', 'source_title', 'source_confirmed_official', 'effective_date', 'last_verified_date', 'next_review_date', 'research_notes', 'admin_notes'];

    /** Saves an administrator's edit. Content changes on a submitted or approved record send it back to draft. */
    public function edit(ComplianceAuthority|ComplianceRequirement $record, array $data, User $user): void
    {
        $type = $record::RECORD_TYPE;
        DB::transaction(function () use ($record, $data, $user, $type) {
            $record->fill(collect($data)->only([...self::CONTENT_FIELDS[$type], ...self::SOURCE_FIELDS])->all());
            if (!$record->isDirty()) {
                return;
            }
            $from = $record->status;
            $contentChanged = $record->isDirty([...self::CONTENT_FIELDS[$type], 'source_url', 'effective_date']);
            if ($contentChanged || in_array($from, ['verification_required', 'changes_requested'], true)) {
                $record->status = 'draft';
                $record->approved_by = null;
                $record->approved_at = null;
            }
            $changed = array_keys($record->getDirty());
            $record->save();
            $this->log($record, 'edited', $from, $record->status, $user, 'Changed: ' . implode(', ', array_diff($changed, ['status', 'approved_by', 'approved_at', 'updated_at'])) . '.');
        });
    }

    /** @throws \InvalidArgumentException when the record is not ready for review */
    public function submit(ComplianceAuthority|ComplianceRequirement $record, User $user, ?string $comment = null): void
    {
        if (!in_array($record->status, ['draft', 'changes_requested', 'review_due', 'verification_required'], true)) {
            throw new \InvalidArgumentException('Only a draft, a record sent back for changes or one due for review can be submitted.');
        }
        $missing = [];
        if ($record instanceof ComplianceRequirement && trim((string) $record->summary) === '') {
            $missing[] = 'the requirement summary';
        }
        if ($record instanceof ComplianceAuthority && trim((string) $record->website_url) === '') {
            $missing[] = "the agency's website";
        }
        if (trim((string) $record->source_url) === '') {
            $missing[] = 'the official source link';
        }
        if ($record->last_verified_date === null) {
            $missing[] = 'the date the source was checked';
        }
        if ($missing !== []) {
            throw new \InvalidArgumentException('Add ' . implode(', ', $missing) . ' before submitting for review.');
        }
        foreach (array_filter([$record->source_url, $record->website_url ?? null, $record->legislation_url ?? null]) as $url) {
            if (!self::isOfficialUrl($url) && !$record->source_confirmed_official) {
                throw new \InvalidArgumentException("{$url} is not on a .gov, .mil or .us site. Use the agency's own official source, or confirm that this is the agency's official site.");
            }
        }
        if ($record->last_verified_date->isFuture()) {
            throw new \InvalidArgumentException('The date the source was checked cannot be in the future.');
        }

        $this->transition($record, 'pending_review', 'submitted', $user, $comment, [
            'submitted_by' => $user->id, 'submitted_at' => now(), 'approved_by' => null, 'approved_at' => null,
        ]);
    }

    /** @throws \InvalidArgumentException when the record is not pending or the reviewer submitted it */
    public function approve(ComplianceAuthority|ComplianceRequirement $record, User $user, ?string $comment = null): void
    {
        if ($record->status !== 'pending_review') {
            throw new \InvalidArgumentException('Only a record pending review can be approved.');
        }
        if (config('compliance.database.require_second_reviewer', true) && $record->submitted_by === $user->id) {
            throw new \InvalidArgumentException('A second administrator must approve this: you submitted it.');
        }
        $next = $record->next_review_date && $record->next_review_date->isFuture()
            ? $record->next_review_date
            : now()->addMonths((int) config('compliance.database.review_interval_months', 12))->startOfDay();

        $this->transition($record, 'verified', 'approved', $user, $comment, [
            'approved_by' => $user->id, 'approved_at' => now(), 'next_review_date' => $next,
        ]);
    }

    public function requestChanges(ComplianceAuthority|ComplianceRequirement $record, User $user, string $comment): void
    {
        if ($record->status !== 'pending_review') {
            throw new \InvalidArgumentException('Only a record pending review can be sent back.');
        }
        if (trim($comment) === '') {
            throw new \InvalidArgumentException('Say what needs to change.');
        }
        $this->transition($record, 'changes_requested', 'changes_requested', $user, $comment);
    }

    /** Withdraws a record's verification: it shows as "Verification Required" until researched again. */
    public function markVerificationRequired(ComplianceAuthority|ComplianceRequirement $record, User $user, string $comment): void
    {
        if (trim($comment) === '') {
            throw new \InvalidArgumentException('Say why this needs verification again.');
        }
        $this->transition($record, 'verification_required', 'reset', $user, $comment, ['approved_by' => null, 'approved_at' => null]);
    }

    /** Verified records whose review date has passed become "Review due"; returns how many. */
    public function flagDueReviews(): int
    {
        $count = 0;
        foreach ([ComplianceAuthority::class, ComplianceRequirement::class] as $class) {
            $class::where('status', 'verified')->whereNotNull('next_review_date')->whereDate('next_review_date', '<=', now()->toDateString())
                ->each(function ($record) use (&$count) {
                    $this->transition($record, 'review_due', 'review_due', null, 'The next review date has passed. Check the official source again and resubmit.');
                    $count++;
                });
        }

        return $count;
    }

    public static function isOfficialUrl(?string $url): bool
    {
        $parts = parse_url((string) $url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        return (bool) preg_match('/(^|\.)[a-z0-9-]+\.(gov|mil|us)$/i', $parts['host']);
    }

    private function transition(Model $record, string $to, string $action, ?User $user, ?string $comment, array $extra = []): void
    {
        DB::transaction(function () use ($record, $to, $action, $user, $comment, $extra) {
            $from = $record->status;
            $record->forceFill(['status' => $to] + $extra)->save();
            $this->log($record, $action, $from, $to, $user, $comment);
        });
    }

    private function log(Model $record, string $action, ?string $from, ?string $to, ?User $user, ?string $comment): void
    {
        ComplianceRecordReview::create([
            'record_type' => $record::RECORD_TYPE, 'record_id' => $record->id, 'action' => $action,
            'from_status' => $from, 'to_status' => $to, 'user_id' => $user?->id,
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            'snapshot' => $record->only($record->getFillable()),
        ]);
    }
}
