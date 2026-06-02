<?php

namespace App\Console\Commands;

use App\Jobs\ETG\StartDumpJob;
use Illuminate\Console\Command;

abstract class AbstractUpdateDumpCommand extends Command
{
    abstract protected function getImporterClass(): string;
    abstract protected function getDumpType(): string;

    final public function handle(): int
    {
        StartDumpJob::dispatch(
            $this->getImporterClass(),
            $this->getDumpType(),
            (bool) $this->option('force'),
        );

        $this->info('[ETG] StartDumpJob dispatched for ' . $this->getDumpType() . '.');

        return self::SUCCESS;
    }
}
