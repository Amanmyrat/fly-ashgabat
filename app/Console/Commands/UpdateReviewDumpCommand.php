<?php

namespace App\Console\Commands;

use App\Services\ETG\Dumps\ReviewDumpImporter;

class UpdateReviewDumpCommand extends AbstractUpdateDumpCommand
{
    protected $signature = 'etg:update-review-dump
                            {--force : Force re-import even if last_update has not changed}';

    protected $description = 'Check ETG review dump for updates and dispatch download/import jobs if a newer dump is available.';

    protected function getImporterClass(): string
    {
        return ReviewDumpImporter::class;
    }

    protected function getDumpType(): string
    {
        return 'review';
    }
}
