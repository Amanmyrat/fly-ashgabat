<?php

namespace App\Jobs\ETG;

use App\Models\EtgDumpStatus;
use App\Services\ETG\Dumps\AbstractDumpImporter;
use App\Services\ETG\Dumps\ReviewDumpImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinalizeDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        private readonly string  $importerClass,
        private readonly string  $dumpType,
        private readonly string  $lastUpdate,
        private readonly ?string $onlyLanguage = null,
    ) {
        $this->queue = 'etg';
    }

    public function handle(): void
    {
        /** @var AbstractDumpImporter $importer */
        $importer = app($this->importerClass);

        if ($importer instanceof ReviewDumpImporter) {
            $importer->aggregateHotelReviewStats();
        }

        $importer->saveLastUpdate($this->lastUpdate);

        if ($this->onlyLanguage !== null) {
            EtgDumpStatus::where('type', $this->dumpType)
                ->where('language', $this->onlyLanguage)
                ->update([
                    'status'      => 'finished',
                    'progress'    => 100,
                    'finished_at' => now(),
                    'last_update' => $this->lastUpdate,
                ]);
        } else {
            EtgDumpStatus::stampLastUpdate($this->dumpType, $this->lastUpdate);
        }

        Log::channel('etg')->info("[{$this->dumpType}] FinalizeDumpJob — import cycle complete.", [
            'last_update'   => $this->lastUpdate,
            'only_language' => $this->onlyLanguage,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        /** @var AbstractDumpImporter $importer */
        $importer = app($this->importerClass);
        $language = $this->onlyLanguage ?? $importer->getBaseLanguage();

        Log::channel('etg')->error("[{$this->dumpType}] FinalizeDumpJob failed.", [
            'language' => $language,
            'error'    => $exception->getMessage(),
        ]);

        EtgDumpStatus::markFailed($this->dumpType, $language, $exception->getMessage());
        // Keep pending so "From File" can reimport from the downloaded dump
    }
}
