<?php

namespace App\Console\Commands;

use App\Services\ETG\Dumps\RegionDumpImporter;

class UpdateRegionDumpCommand extends AbstractUpdateDumpCommand
{
    protected $signature = 'etg:update-region-dump
                            {--force : Force re-import even if last_update has not changed}';

    protected $description = 'Check ETG region dump for updates and dispatch download/import jobs if a newer dump is available.';

    protected function getImporterClass(): string
    {
        return RegionDumpImporter::class;
    }

    protected function getDumpType(): string
    {
        return 'region';
    }
}
