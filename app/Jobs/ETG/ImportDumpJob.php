<?php

namespace App\Jobs\ETG;

use App\Exceptions\JobCancelledException;
use App\Models\EtgDumpStatus;
use App\Services\ETG\Dumps\AbstractDumpImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 14400;
    public int $backoff = 60;

    private const PROGRESS_EVERY = 5000;
    private const LOCK_TTL       = 18000;

    public function __construct(
        private readonly string  $importerClass,
        private readonly string  $dumpType,
        private readonly string  $jsonlPath,
        private readonly string  $lastUpdate,
        private readonly string  $language,
        private readonly ?string $onlyLanguage = null,
        private readonly bool    $force        = false,
    ) {
        $this->queue = 'etg';
    }

    private function lockKey(): string
    {
        return "etg_import_{$this->dumpType}_{$this->language}";
    }

    public function handle(): void
    {
        /** @var AbstractDumpImporter $importer */
        $importer = app($this->importerClass);

        if (!$importer->requiresImportLock()) {
            $this->runImport();
            return;
        }

        // Force mode (triggered by Reimport / From File): release any stuck lock from a
        // previous crashed or skipped run before attempting to acquire a fresh one.
        if ($this->force) {
            Cache::lock($this->lockKey(), self::LOCK_TTL)->forceRelease();
        }

        $lock = Cache::lock($this->lockKey(), self::LOCK_TTL);

        if (!$lock->get()) {
            Log::channel('etg')->warning("[{$this->dumpType}] ImportDumpJob skipped — another instance already running.", [
                'language' => $this->language,
                'hint'     => 'Use Reimport (force) or From File to clear the lock and retry.',
            ]);
            EtgDumpStatus::where('type', $this->dumpType)
                ->where('language', $this->language)
                ->update([
                    'status'        => 'idle',
                    'error_message' => 'Import skipped: lock held by previous run. Use "Reimport" in Dump Manager to force-clear it.',
                ]);
            return;
        }

        try {
            $this->runImport();
        } finally {
            $lock->release();
        }
    }

    private function runImport(): void
    {
        if (EtgDumpStatus::isCancelled($this->dumpType, $this->language)) {
            return;
        }

        EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->update([
                'status'            => 'importing',
                'progress'          => 0,
                'records_processed' => 0,
                'lines_processed'   => 0,
            ]);

        Log::channel('etg')->info("[{$this->dumpType}] ImportDumpJob started.", [
            'language'   => $this->language,
            'jsonl_path' => $this->jsonlPath,
        ]);

        /** @var AbstractDumpImporter $importer */
        $importer = app($this->importerClass);

        $knownTotal = (int) (EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->value('total_records') ?? 0);

        if ($knownTotal === 0) {
            $knownTotal = $this->countLines($this->jsonlPath);
            EtgDumpStatus::where('type', $this->dumpType)
                ->where('language', $this->language)
                ->update(['total_records' => $knownTotal]);
            Log::channel('etg')->info("[{$this->dumpType}][{$this->language}] Line count completed.", [
                'total' => $knownTotal,
            ]);
        }

        $lastDbWrite = 0;

        $total = $importer->importRecords(
            $this->jsonlPath,
            $this->language,
            function (int $linesProcessed, ?int $recordsInserted = null) use ($knownTotal, &$lastDbWrite): void {
                if (EtgDumpStatus::isCancelled($this->dumpType, $this->language)) {
                    throw new JobCancelledException('Import cancelled by user.');
                }
                $recordsInserted = $recordsInserted ?? $linesProcessed;
                if ($linesProcessed - $lastDbWrite >= self::PROGRESS_EVERY) {
                    $progress    = $knownTotal > 0 ? min(99, (int) (($linesProcessed / $knownTotal) * 100)) : 0;
                    $lastDbWrite = $linesProcessed;
                    EtgDumpStatus::where('type', $this->dumpType)
                        ->where('language', $this->language)
                        ->update([
                            'lines_processed'   => $linesProcessed,
                            'records_processed' => $recordsInserted,
                            'progress'          => $progress,
                        ]);
                }
            }
        );

        // .zst archives are only deleted on success if ETG_DELETE_ARCHIVES=true (server config).
        // Plain .jsonl files (legacy manual decompress path) are always cleaned up.
        if (str_ends_with($this->jsonlPath, '.zst')) {
            if (config('services.etg.delete_archives')) {
                @unlink($this->jsonlPath);
                Log::channel('etg')->info("[{$this->dumpType}] Archive deleted after import (ETG_DELETE_ARCHIVES=true).", [
                    'path' => $this->jsonlPath,
                ]);
            }
        } else {
            @unlink($this->jsonlPath);
        }

        Log::channel('etg')->info("[{$this->dumpType}][{$this->language}] Import pass complete.", [
            'total' => $total,
        ]);

        $this->chainNextLanguageOrFinalize($importer, $total, $knownTotal);
    }

    public function failed(Throwable $exception): void
    {
        Cache::lock($this->lockKey(), self::LOCK_TTL)->forceRelease();

        if ($exception instanceof JobCancelledException) {
            EtgDumpStatus::resetFailed($this->dumpType, $this->language);
            app($this->importerClass)->clearPendingUpdate();
            return;
        }

        Log::channel('etg')->error("[{$this->dumpType}] ImportDumpJob failed.", [
            'language' => $this->language,
            'error'    => $exception->getMessage(),
        ]);

        $current = EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->value('status');

        if ($current === 'finished') {
            return;
        }

        EtgDumpStatus::markFailed($this->dumpType, $this->language, $exception->getMessage());
        // Keep .zst archives on failure so the import can be retried from the existing file.
        // Only clean up plain .jsonl temp files.
        if (!str_ends_with($this->jsonlPath, '.zst')) {
            @unlink($this->jsonlPath);
        }
        app($this->importerClass)->clearPendingUpdate();
    }

    private function chainNextLanguageOrFinalize(AbstractDumpImporter $importer, int $total, int $totalLines): void
    {
        EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->update([
                'status'            => 'finished',
                'progress'          => 100,
                'records_processed' => $total,
                'lines_processed'   => $totalLines,
                'finished_at'       => now(),
            ]);

        if ($this->onlyLanguage !== null) {
            FinalizeDumpJob::dispatch(
                $this->importerClass,
                $this->dumpType,
                $this->lastUpdate,
                $this->onlyLanguage,
            );
            return;
        }

        $languages    = $importer->getSupportedLanguages();
        $currentIndex = array_search($this->language, $languages, true);
        $nextLanguage = $languages[$currentIndex + 1] ?? null;

        if ($nextLanguage !== null) {
            Log::channel('etg')->info("[{$this->dumpType}] Fetching info for next language.", [
                'next_language' => $nextLanguage,
            ]);

            EtgDumpStatus::forTypeAndLanguage($this->dumpType, $nextLanguage);
            $info = $importer->fetchDumpInfo($nextLanguage);

            DownloadDumpJob::dispatch(
                $this->importerClass,
                $this->dumpType,
                $info['download_url'],
                $this->lastUpdate,
                $nextLanguage,
                null,
            );
            return;
        }

        FinalizeDumpJob::dispatch(
            $this->importerClass,
            $this->dumpType,
            $this->lastUpdate,
            null,
        );
    }

    private function countLines(string $path): int
    {
        $count = 0;

        // For .zst archives decompress on the fly — counting newlines in the raw
        // compressed binary gives meaningless results.
        if (str_ends_with($path, '.zst')) {
            $handle = popen('zstd -d -c ' . escapeshellarg($path) . ' 2>/dev/null', 'r');
            if ($handle === false) {
                return 0;
            }
            try {
                while (!feof($handle)) {
                    $count += substr_count(fread($handle, 65536), "\n");
                }
            } finally {
                pclose($handle);
            }
            return $count;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }
        try {
            while (!feof($handle)) {
                $count += substr_count(fread($handle, 65536), "\n");
            }
        } finally {
            fclose($handle);
        }
        return $count;
    }
}
