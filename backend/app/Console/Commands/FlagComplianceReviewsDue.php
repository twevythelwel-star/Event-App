<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compliance\ComplianceReviewWorkflow;
use Illuminate\Console\Command;

/** Verified compliance records past their next review date become "Review due" (scheduled daily). */
class FlagComplianceReviewsDue extends Command
{
    protected $signature = 'compliance:flag-due-reviews';

    protected $description = 'Mark verified compliance records whose review date has passed as due for review';

    public function handle(ComplianceReviewWorkflow $workflow): int
    {
        $this->info('Records now due for review: ' . $workflow->flagDueReviews());

        return self::SUCCESS;
    }
}
