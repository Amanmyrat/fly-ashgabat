<?php

namespace App\Jobs\ETG;

use App\Models\EtgDumpStatus;
use App\Services\ETG\Dumps\AbstractDumpImporter;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

// DecompressDumpJob is no longer dispatched from here — ImportDumpJob streams directly
// from the .zst archive via a zstd pipe, avoiding the 40-60 GB intermediate .jsonl file.

class DownloadDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 7200;

    private const CHUNK_SIZE      = 65536;
    private const DB_UPDATE_EVERY = 10_000_000;

    public function __construct(
        private readonly string  $importerClass,
        private readonly string  $dumpType,
        private readonly string  $downloadUrl,
        private readonly string  $lastUpdate,
        private readonly string  $language,
        private readonly ?string $onlyLanguage  = null,
        private readonly bool    $forceDownload = false,
    ) {
        $this->queue = 'etg';
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->update([
                'status'           => 'downloading',
                'progress'         => 0,
                'downloaded_bytes' => 0,
                'file_size'        => null,
            ]);

        Log::channel('etg')->info("[{$this->dumpType}] DownloadDumpJob started.", [
            'language' => $this->language,
        ]);

        /** @var AbstractDumpImporter $importer */
        $importer = app($this->importerClass);
        $fullPath = $this->buildFilePath($importer);

        if (!$this->forceDownload && file_exists($fullPath) && filesize($fullPath) > 0) {
            Log::channel('etg')->info("[{$this->dumpType}] File already on disk, skipping download.", [
                'path'  => $fullPath,
                'bytes' => filesize($fullPath),
            ]);
        } else {
            @unlink($fullPath . '.tmp');
            $this->streamDownload($fullPath);
        }

        Log::channel('etg')->info("[{$this->dumpType}] Download complete — dispatching ImportDumpJob (streaming direct from archive).", [
            'language' => $this->language,
            'path'     => $fullPath,
        ]);

        // Stream directly from the .zst archive during import — no decompressed temp file needed.
        ImportDumpJob::dispatch(
            $this->importerClass,
            $this->dumpType,
            $fullPath,
            $this->lastUpdate,
            $this->language,
            $this->onlyLanguage,
            $this->forceDownload,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('etg')->error("[{$this->dumpType}] DownloadDumpJob failed.", [
            'language' => $this->language,
            'error'    => $exception->getMessage(),
        ]);

        EtgDumpStatus::markFailed($this->dumpType, $this->language, $exception->getMessage());
        app($this->importerClass)->clearPendingUpdate();
    }

    private function streamDownload(string $fullPath): void
    {
        $tmpPath = $fullPath . '.tmp';

        $guzzle    = new GuzzleClient(['timeout' => 0, 'connect_timeout' => 30]);
        $response  = $guzzle->request('GET', $this->downloadUrl, ['stream' => true]);
        $totalSize = (int) $response->getHeaderLine('Content-Length');
        $body      = $response->getBody();

        EtgDumpStatus::where('type', $this->dumpType)
            ->where('language', $this->language)
            ->update(['file_size' => $totalSize ?: null]);

        $handle = fopen($tmpPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("[{$this->dumpType}] Cannot open temp file for writing: {$tmpPath}");
        }

        $downloaded  = 0;
        $lastDbWrite = 0;

        try {
            while (!$body->eof()) {
                $chunk       = $body->read(self::CHUNK_SIZE);
                fwrite($handle, $chunk);
                $downloaded += strlen($chunk);

                if ($downloaded - $lastDbWrite >= self::DB_UPDATE_EVERY) {
                    $progress    = $totalSize > 0 ? min(99, (int) (($downloaded / $totalSize) * 100)) : 0;
                    $lastDbWrite = $downloaded;

                    EtgDumpStatus::where('type', $this->dumpType)
                        ->where('language', $this->language)
                        ->update([
                            'downloaded_bytes' => $downloaded,
                            'progress'         => $progress,
                        ]);
                }
            }
        } finally {
            fclose($handle);
        }

        if (!file_exists($tmpPath) || filesize($tmpPath) === 0) {
            @unlink($tmpPath);
            throw new RuntimeException("[{$this->dumpType}] Downloaded file is empty: {$tmpPath}");
        }

        rename($tmpPath, $fullPath);
    }

    private function buildFilePath(AbstractDumpImporter $importer): string
    {
        $date     = Carbon::parse($this->lastUpdate)->format('Y_m_d');
        $filename = "{$importer->getSyncPrefix()}_{$this->language}_{$date}.jsonl.zst";
        $diskPath = $importer->getStorageDir() . '/' . $filename;

        Storage::disk('local')->makeDirectory($importer->getStorageDir());

        return Storage::disk('local')->path($diskPath);
    }
}
