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
use Throwable;

class DecompressDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 7200;
    public int $backoff = 60;

    private const LOCK_TTL = 7800;

    public function __construct(
        private readonly string  $importerClass,
        private readonly string  $dumpType,
        private readonly string  $zstPath,
        private readonly string  $lastUpdate,
        private readonly string  $language,
        private readonly ?string $onlyLanguage = null,
    ) {
        $this->queue = 'etg';
    }

    private function lockKey(): string
    {
        return "etg_decompress_{$this->dumpType}_{$this->language}";
    }

    public function handle(): void
    {
        $lock = Cache::lock($this->lockKey(), self::LOCK_TTL);

        if (!$lock->get()) {
            Log::channel('etg')->warning("[{$this->dumpType}] DecompressDumpJob skipped — another instance already running.", [
                'language' => $this->language,
            ]);
            return;
        }

        try {
            $this->runDecompress();
        } finally {
            $lock->release();
        }
    }

    private function runDecompress(): void
    {
        EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->update(['status' => 'decompressing', 'progress' => 0]);

        Log::channel('etg')->info("[{$this->dumpType}] DecompressDumpJob started.", [
            'language' => $this->language,
            'zst_path' => $this->zstPath,
        ]);

        /** @var AbstractDumpImporter $importer */
        $importer  = app($this->importerClass);
        $jsonlPath = $importer->decompressDump($this->zstPath);

        if (config('services.etg.delete_archives')) {
            @unlink($this->zstPath);
            Log::channel('etg')->info("[{$this->dumpType}] Archive deleted after decompression (ETG_DELETE_ARCHIVES=true).", [
                'path' => $this->zstPath,
            ]);
        }

        Log::channel('etg')->info("[{$this->dumpType}] Decompression complete — dispatching ImportDumpJob.", [
            'language'   => $this->language,
            'jsonl_path' => $jsonlPath,
        ]);

        ImportDumpJob::dispatch(
            $this->importerClass,
            $this->dumpType,
            $jsonlPath,
            $this->lastUpdate,
            $this->language,
            $this->onlyLanguage,
        );
    }

    public function failed(Throwable $exception): void
    {
        Cache::lock($this->lockKey(), self::LOCK_TTL)->forceRelease();

        Log::channel('etg')->error("[{$this->dumpType}] DecompressDumpJob failed.", [
            'language' => $this->language,
            'error'    => $exception->getMessage(),
        ]);

        EtgDumpStatus::markFailed($this->dumpType, $this->language, $exception->getMessage());
        @unlink(str_replace('.zst', '', $this->zstPath));
        app($this->importerClass)->clearPendingUpdate();
    }
}
