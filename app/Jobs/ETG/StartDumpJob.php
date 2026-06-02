<?php

namespace App\Jobs\ETG;

use App\Models\EtgDumpStatus;
use App\Services\ETG\Dumps\AbstractDumpImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class StartDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly string  $importerClass,
        private readonly string  $dumpType,
        private readonly bool    $force        = false,
        private readonly ?string $onlyLanguage = null,
        private readonly bool    $skipDownload = false,
    ) {
        $this->queue = 'etg';
    }

    public function handle(): void
    {
        /** @var AbstractDumpImporter $importer */
        $importer      = app($this->importerClass);
        $startLanguage = $this->onlyLanguage ?? $importer->getBaseLanguage();

        if ($this->onlyLanguage !== null && $this->onlyLanguage !== $importer->getBaseLanguage()) {
            $baseStatus = EtgDumpStatus::where('type', $this->dumpType)
                ->where('language', $importer->getBaseLanguage())
                ->first();

            if (!$baseStatus || $baseStatus->status !== 'finished') {
                $msg = "Cannot import {$this->onlyLanguage} translations: base language ({$importer->getBaseLanguage()}) import has not finished yet (status: " . ($baseStatus?->status ?? 'none') . ").";
                Log::channel('etg')->error("[{$this->dumpType}] StartDumpJob aborted. {$msg}");
                EtgDumpStatus::forTypeAndLanguage($this->dumpType, $startLanguage);
                EtgDumpStatus::markFailed($this->dumpType, $startLanguage, $msg);
                return;
            }

            if (($baseStatus->records_processed ?? 0) < 100000) {
                $msg = "Cannot import {$this->onlyLanguage} translations: base language only has " . number_format($baseStatus->records_processed) . " records (minimum 100,000 required).";
                Log::channel('etg')->error("[{$this->dumpType}] StartDumpJob aborted. {$msg}");
                EtgDumpStatus::forTypeAndLanguage($this->dumpType, $startLanguage);
                EtgDumpStatus::markFailed($this->dumpType, $startLanguage, $msg);
                return;
            }
        }

        EtgDumpStatus::forTypeAndLanguage($this->dumpType, $startLanguage);
        EtgDumpStatus::markStarted($this->dumpType, $startLanguage);

        Log::channel('etg')->info("[{$this->dumpType}] StartDumpJob — fetching dump info.", [
            'start_language' => $startLanguage,
            'only_language'  => $this->onlyLanguage,
            'force'          => $this->force,
            'skip_download'  => $this->skipDownload,
        ]);

        // When forcing a re-run, release any stuck import lock so ImportDumpJob can acquire it.
        // This covers both a crashed previous run that never released and the common case of
        // clicking "Reimport" / "From File" after a skipped import.
        if ($this->force || $this->skipDownload) {
            $lockKey = "etg_import_{$this->dumpType}_{$startLanguage}";
            Cache::lock($lockKey, 1)->forceRelease();
            Log::channel('etg')->info("[{$this->dumpType}] Force mode: released any held import lock.", [
                'language' => $startLanguage,
                'lock_key' => $lockKey,
            ]);
        }

        if ($this->skipDownload) {
            $this->handleSkipDownload($importer, $startLanguage);
            return;
        }

        $baseLanguage     = $importer->getBaseLanguage();
        $info             = $importer->fetchDumpInfo($baseLanguage);
        $remoteLastUpdate = $info['last_update'];

        $isTranslationOnly = $this->onlyLanguage !== null && $this->onlyLanguage !== $baseLanguage;

        if (!$this->force && !$isTranslationOnly) {
            $storedLastUpdate = $importer->getStoredLastUpdate();

            if ($storedLastUpdate === $remoteLastUpdate) {
                Log::channel('etg')->info("[{$this->dumpType}] Dump already up to date — skipping download and import.", [
                    'last_update' => $remoteLastUpdate,
                    'hint'        => 'Use Dump Manager “Reimport” (force) or “From File” to run the import pipeline anyway.',
                ]);

                EtgDumpStatus::where('type', $this->dumpType)
                    ->where('language', $startLanguage)
                    ->update([
                        'status'      => 'finished',
                        'progress'    => 100,
                        'finished_at' => now(),
                        'last_update' => $remoteLastUpdate,
                    ]);

                return;
            }
        }

        $importer->markUpdatePending($remoteLastUpdate);

        $downloadUrl = ($this->onlyLanguage !== null && $this->onlyLanguage !== $baseLanguage)
            ? $importer->fetchDumpInfo($this->onlyLanguage)['download_url']
            : $info['download_url'];

        DownloadDumpJob::dispatch(
            $this->importerClass,
            $this->dumpType,
            $downloadUrl,
            $remoteLastUpdate,
            $startLanguage,
            $this->onlyLanguage,
            $this->force,
        );
    }

    public function failed(Throwable $exception): void
    {
        /** @var AbstractDumpImporter $importer */
        $importer      = app($this->importerClass);
        $startLanguage = $this->onlyLanguage ?? $importer->getBaseLanguage();

        Log::channel('etg')->error("[{$this->dumpType}] StartDumpJob failed.", [
            'language' => $startLanguage,
            'error'    => $exception->getMessage(),
        ]);

        EtgDumpStatus::markFailed($this->dumpType, $startLanguage, $exception->getMessage());
    }

    private function handleSkipDownload(AbstractDumpImporter $importer, string $startLanguage): void
    {
        $lastUpdate = $importer->getPendingUpdate()
            ?? $importer->getStoredLastUpdate()
            ?? $importer->getLastUpdateFromExistingFile($startLanguage);

        if (!$lastUpdate) {
            throw new RuntimeException("[{$this->dumpType}] Cannot reimport from file: no stored last_update and no dump file found on disk.");
        }

        $zstPath = $importer->getDumpFilePath($lastUpdate, $startLanguage);

        if (!file_exists($zstPath)) {
            throw new RuntimeException("[{$this->dumpType}] Dump file not found on disk: {$zstPath}");
        }

        Log::channel('etg')->info("[{$this->dumpType}] Reimporting from existing file (skipping download).", [
            'language'    => $startLanguage,
            'zst_path'    => $zstPath,
            'last_update' => $lastUpdate,
        ]);

        $importer->markUpdatePending($lastUpdate);

        // Stream directly from the .zst archive — no decompression step needed.
        // skipDownload implies a forced re-run, so pass force=true to ImportDumpJob.
        ImportDumpJob::dispatch(
            $this->importerClass,
            $this->dumpType,
            $zstPath,
            $lastUpdate,
            $startLanguage,
            $this->onlyLanguage,
            true,
        );
    }
}
