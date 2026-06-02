<?php

namespace App\Console\Commands;

use App\Services\ETG\Dumps\HotelDumpImporter;

class UpdateHotelDumpCommand extends AbstractUpdateDumpCommand
{
    protected $signature = 'etg:update-hotel-dump
                            {--force : Force re-import even if last_update has not changed}';

    protected $description = 'Check ETG hotel dump for updates and dispatch download/import jobs if a newer dump is available.';

    protected function getImporterClass(): string
    {
        return HotelDumpImporter::class;
    }

    protected function getDumpType(): string
    {
        return 'hotel';
    }
}
