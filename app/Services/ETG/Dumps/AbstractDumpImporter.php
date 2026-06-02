<?php

namespace App\Services\ETG\Dumps;

use App\Models\EtgSyncState;
use App\Services\ETG\EtgClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class AbstractDumpImporter
{
    protected const BATCH_SIZE         = 2000;
    protected const COMMIT_EVERY       = 100;
    protected const DECOMPRESS_TIMEOUT = 7200;

    public const SUPPORTED_LANGUAGES = ['en', 'ru'];
    public const BASE_LANGUAGE       = 'en';

    public function __construct(protected readonly EtgClient $client) {}

    abstract public function getApiEndpoint(): string;
    abstract public function getTable(): string;
    abstract public function getSyncPrefix(): string;
    abstract public function getStorageDir(): string;
    abstract public function getPrimaryKey(): string;

    abstract protected function getRequestBody(string $language): object|array;
    abstract protected function mapBaseRecord(array $data, Carbon $now): ?array;
    abstract protected function mapTranslationFields(array $data, string $language): ?array;

    /**
     * Map one JSONL line to zero or more DB rows. Default: single record via mapBaseRecord.
     * Override to expand one line into multiple rows (e.g. hotel with reviews array).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapLineToRecords(array $data, Carbon $now): array
    {
        $row = $this->mapBaseRecord($data, $now);

        return $row !== null ? [$row] : [];
    }

    public function getSupportedLanguages(): array
    {
        return static::SUPPORTED_LANGUAGES;
    }

    public function getBaseLanguage(): string
    {
        return static::BASE_LANGUAGE;
    }

    public function isBaseLanguage(string $language): bool
    {
        return $language === static::BASE_LANGUAGE;
    }

    /**
     * Whether ImportDumpJob should use a Redis lock to prevent parallel imports.
     * Override to false for importers that handle their own concurrency internally
     * or are simple enough that a stuck lock would be more harmful than a parallel run.
     */
    public function requiresImportLock(): bool
    {
        return true;
    }

    public function fetchDumpInfo(string $language): array
    {
        return $this->client->getDumpInfo($this->getApiEndpoint(), $this->getRequestBody($language));
    }

    public function getDumpFilePath(string $lastUpdate, string $language): string
    {
        $date     = Carbon::parse($lastUpdate)->format('Y_m_d');
        $filename = "{$this->getSyncPrefix()}_{$language}_{$date}.jsonl.zst";

        return Storage::disk('local')->path($this->getStorageDir() . '/' . $filename);
    }

    public function getStoredLastUpdate(): ?string
    {
        return EtgSyncState::getValue($this->lastUpdateKey());
    }

    public function getPendingUpdate(): ?string
    {
        return EtgSyncState::getValue($this->pendingUpdateKey());
    }

    /**
     * Scan storage for an existing dump file and return its last_update string.
     * Override in subclasses (e.g. ReviewDumpImporter) to support "From File" when DB has no stored date.
     */
    public function getLastUpdateFromExistingFile(string $language): ?string
    {
        return null;
    }

    public function markUpdatePending(string $lastUpdate): void
    {
        EtgSyncState::setValue($this->pendingUpdateKey(), $lastUpdate);
        $this->log()->info("[{$this->getSyncPrefix()}] Marked update as pending.", ['last_update' => $lastUpdate]);
    }

    public function saveLastUpdate(string $lastUpdate): void
    {
        EtgSyncState::setValue($this->lastUpdateKey(), $lastUpdate);
        EtgSyncState::removeKey($this->pendingUpdateKey());
        $this->log()->info("[{$this->getSyncPrefix()}] Saved last_update and cleared pending flag.", ['last_update' => $lastUpdate]);
    }

    public function clearPendingUpdate(): void
    {
        EtgSyncState::removeKey($this->pendingUpdateKey());
        $this->log()->info("[{$this->getSyncPrefix()}] Cleared pending_update flag after failure.");
    }

    public function decompressDump(string $zstPath): string
    {
        $jsonlPath = str_replace('.zst', '', $zstPath);

        if (file_exists($jsonlPath) && filesize($jsonlPath) > 0) {
            $this->log()->info("[{$this->getSyncPrefix()}] Decompressed file already on disk, skipping.", [
                'path'  => $jsonlPath,
                'bytes' => filesize($jsonlPath),
            ]);
            return $jsonlPath;
        }

        $this->log()->info("[{$this->getSyncPrefix()}] Decompressing dump.", [
            'source' => $zstPath,
            'target' => $jsonlPath,
        ]);

        $process = new Process(['zstd', '-d', '-f', $zstPath, '-o', $jsonlPath]);
        $process->setTimeout(self::DECOMPRESS_TIMEOUT);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (!file_exists($jsonlPath) || filesize($jsonlPath) === 0) {
            throw new RuntimeException("[{$this->getSyncPrefix()}] Decompression produced no output file: {$jsonlPath}");
        }

        $this->log()->info("[{$this->getSyncPrefix()}] Decompression complete.", ['bytes' => filesize($jsonlPath)]);

        return $jsonlPath;
    }

    public function importRecords(string $jsonlPath, string $language, ?callable $onBatch = null): int
    {
        if ($this->isBaseLanguage($language)) {
            return $this->importBaseRecords($jsonlPath, $onBatch);
        }

        return $this->importTranslations($jsonlPath, $language, $onBatch);
    }

    private const INFILE_TMPDIR = 'etg/tmp';

    /**
     * Open a dump file for line-by-line reading without writing a decompressed temp file.
     *
     * - .zst  → pipes through `zstd -d -c` (decompress to stdout on the fly).
     *           Saves 40-60 GB of intermediate disk space compared to decompressing first.
     * - .jsonl → plain fopen (legacy / manual path).
     *
     * @return array{resource, bool} [$handle, $isPipe]
     */
    private function openDumpStream(string $path): array
    {
        if (str_ends_with($path, '.zst')) {
            $handle = popen('zstd -d -c ' . escapeshellarg($path) . ' 2>/dev/null', 'r');
            if ($handle === false) {
                throw new RuntimeException("[{$this->getSyncPrefix()}] Cannot open zstd pipe for: {$path}");
            }
            return [$handle, true];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("[{$this->getSyncPrefix()}] Cannot open file: {$path}");
        }
        return [$handle, false];
    }

    private function closeDumpStream(mixed $handle, bool $isPipe): void
    {
        if ($isPipe) {
            pclose($handle);
        } else {
            fclose($handle);
        }
    }

    /**
     * Orchestrates the zero-downtime base-language import:
     *   1. Create a staging table (empty clone of the live table).
     *   2. Fill it via LOAD DATA LOCAL INFILE (fast path) or batched INSERT (fallback).
     *   3. Atomic RENAME TABLE to swap staging → live.
     *
     * The live table is never empty or partially updated during the process.
     */
    private function importBaseRecords(string $jsonlPath, ?callable $onBatch = null): int
    {
        $table        = $this->getTable();
        $stagingTable = $table . '_import';

        $this->log()->info("[{$this->getSyncPrefix()}][en] Creating staging table.", [
            'staging' => $stagingTable,
            'method'  => config('services.etg.use_load_data') ? 'LOAD DATA LOCAL INFILE' : 'batch INSERT',
        ]);

        DB::statement("DROP TABLE IF EXISTS `{$stagingTable}`");
        DB::statement("CREATE TABLE `{$stagingTable}` LIKE `{$table}`");

        try {
            $count = config('services.etg.use_load_data')
                ? $this->loadViaInfile($jsonlPath, $stagingTable, $onBatch)
                : $this->loadViaBatchInsert($jsonlPath, $stagingTable, $onBatch);
        } catch (Throwable $e) {
            DB::statement("DROP TABLE IF EXISTS `{$stagingTable}`");
            throw $e;
        }

        // Atomic swap — RENAME TABLE is instantaneous and non-blocking for readers.
        $this->log()->info("[{$this->getSyncPrefix()}][en] Atomically swapping staging table into place.");
        DB::statement("RENAME TABLE `{$table}` TO `{$table}_old`, `{$stagingTable}` TO `{$table}`");
        DB::statement("DROP TABLE IF EXISTS `{$table}_old`");

        $this->log()->info("[{$this->getSyncPrefix()}][en] Base import complete.", ['total' => $count]);

        return $count;
    }

    /**
     * Fast path: stream JSONL → TSV on disk, then let MySQL bulk-load it with
     * LOAD DATA LOCAL INFILE. Bypasses PDO parameter binding entirely; MySQL reads
     * the file in a single native pass and builds indexes in sorted bulk order.
     *
     * Typical speedup over batch INSERT: 10-50× for the DB write step.
     *
     * Requires:
     *  - ETG_USE_LOAD_DATA=true in .env
     *  - MySQL server: SET GLOBAL local_infile = 1 (or local_infile=ON in my.cnf)
     */
    private function loadViaInfile(string $dumpPath, string $stagingTable, ?callable $onBatch): int
    {
        Storage::disk('local')->makeDirectory(self::INFILE_TMPDIR);
        $tsvPath = Storage::disk('local')->path(
            self::INFILE_TMPDIR . '/' . $stagingTable . '_' . time() . '.tsv'
        );

        $this->log()->info("[{$this->getSyncPrefix()}][en] Streaming dump → TSV.", ['tsv' => $tsvPath]);

        $columns        = null;
        $count          = 0;
        $linesProcessed = 0;
        $now            = now();

        [$readFp, $isPipe] = $this->openDumpStream($dumpPath);
        $writeFp           = fopen($tsvPath, 'wb');

        if ($writeFp === false) {
            $this->closeDumpStream($readFp, $isPipe);
            throw new RuntimeException("[{$this->getSyncPrefix()}][en] Cannot open TSV for writing: {$tsvPath}");
        }

        try {
            while (($line = fgets($readFp)) !== false) {
                $line = trim($line);
                if ($line === '' || !is_array($data = json_decode($line, true))) {
                    continue;
                }

                $linesProcessed++;

                foreach ($this->mapLineToRecords($data, $now) as $row) {
                    if ($columns === null) {
                        $columns = array_keys($row);
                    }
                    $this->writeTsvRow($writeFp, $row);
                    $count++;
                }

                // Fire progress callbacks at the same cadence as batch INSERT would.
                if ($onBatch !== null && $linesProcessed % (static::BATCH_SIZE * static::COMMIT_EVERY) === 0) {
                    ($onBatch)($linesProcessed, $count);
                }
            }
        } finally {
            $this->closeDumpStream($readFp, $isPipe);
            fclose($writeFp);
        }

        if ($count === 0 || $columns === null) {
            @unlink($tsvPath);
            $this->log()->warning("[{$this->getSyncPrefix()}][en] No records written to TSV — nothing to load.");
            return 0;
        }

        $colList = '`' . implode('`, `', $columns) . '`';
        $pdo     = DB::connection()->getPdo();

        $this->log()->info("[{$this->getSyncPrefix()}][en] Executing LOAD DATA LOCAL INFILE.", [
            'rows' => $count,
        ]);

        try {
            DB::statement('SET foreign_key_checks=0');
            DB::statement('SET unique_checks=0');
            DB::statement('SET autocommit=0');

            $pdo->exec(
                'LOAD DATA LOCAL INFILE ' . $pdo->quote($tsvPath) . "
                 INTO TABLE `{$stagingTable}`
                 CHARACTER SET utf8mb4
                 FIELDS TERMINATED BY '\t'
                 LINES TERMINATED BY '\n'
                 ({$colList})"
            );

            DB::statement('COMMIT');
        } finally {
            DB::statement('SET autocommit=1');
            DB::statement('SET unique_checks=1');
            DB::statement('SET foreign_key_checks=1');
            @unlink($tsvPath);
        }

        if ($onBatch !== null) {
            ($onBatch)($linesProcessed, $count);
        }

        $this->log()->info("[{$this->getSyncPrefix()}][en] LOAD DATA complete.", ['loaded' => $count]);

        return $count;
    }

    /**
     * Fallback path (ETG_USE_LOAD_DATA=false): batched INSERT statements via PDO.
     * Slower than LOAD DATA but requires no server-side configuration changes.
     * Accepts either a .zst archive (streamed on the fly) or a plain .jsonl file.
     */
    private function loadViaBatchInsert(string $dumpPath, string $stagingTable, ?callable $onBatch): int
    {
        DB::disableQueryLog();
        DB::statement('SET foreign_key_checks=0');
        DB::statement('SET unique_checks=0');

        [$handle, $isPipe] = $this->openDumpStream($dumpPath);

        $batch          = [];
        $totalCount     = 0;
        $linesProcessed = 0;
        $batchCount     = 0;
        $now            = now();

        DB::beginTransaction();

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '' || !is_array($data = json_decode($line, true))) {
                    continue;
                }

                $linesProcessed++;

                foreach ($this->mapLineToRecords($data, $now) as $row) {
                    $batch[] = $row;
                }

                if (count($batch) >= static::BATCH_SIZE) {
                    DB::table($stagingTable)->insert($batch);
                    $totalCount += count($batch);
                    $batchCount++;
                    $batch = [];

                    if ($batchCount % self::COMMIT_EVERY === 0) {
                        DB::commit();
                        if ($onBatch !== null) {
                            ($onBatch)($linesProcessed, $totalCount);
                        }
                        $this->log()->debug("[{$this->getSyncPrefix()}][en] Batch inserted.", [
                            'lines'    => $linesProcessed,
                            'inserted' => $totalCount,
                        ]);
                        DB::beginTransaction();
                    }
                }
            }

            if (!empty($batch)) {
                DB::table($stagingTable)->insert($batch);
                $totalCount += count($batch);
                if ($onBatch !== null) {
                    ($onBatch)($linesProcessed, $totalCount);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $this->closeDumpStream($handle, $isPipe);
            DB::statement('SET foreign_key_checks=1');
            DB::statement('SET unique_checks=1');
        }

        $this->log()->info("[{$this->getSyncPrefix()}][en] Batch INSERT complete.", ['total_inserted' => $totalCount]);

        return $totalCount;
    }

    /**
     * Write one row to a tab-separated file for LOAD DATA LOCAL INFILE.
     *
     * Rules (MySQL LOAD DATA INFILE tab-delimited format):
     *  - NULL  → \N  (literal backslash-N, outside any quotes)
     *  - Dates → Y-m-d H:i:s
     *  - Strings → escape \, TAB, LF, CR with backslash sequences
     *  - Numerics → written as-is
     *
     * @param resource $fp
     */
    private function writeTsvRow(mixed $fp, array $row): void
    {
        $parts = [];
        foreach ($row as $value) {
            if ($value === null) {
                $parts[] = '\\N';
            } elseif ($value instanceof \DateTimeInterface) {
                $parts[] = $value->format('Y-m-d H:i:s');
            } elseif (is_bool($value)) {
                $parts[] = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $parts[] = (string) $value;
            } else {
                $parts[] = str_replace(
                    ['\\',    "\t",   "\n",   "\r"],
                    ['\\\\', '\\t', '\\n', '\\r'],
                    (string) $value
                );
            }
        }
        fwrite($fp, implode("\t", $parts) . "\n");
    }

    /**
     * Import translations using a temporary table + single JOIN UPDATE.
     *
     * Why: the previous CASE WHEN UPDATE approach built a query with O(batch × columns) bound
     * parameters per call, so 6 000 huge queries for 3 M hotels. Here we:
     *   1. Stream all translation rows into a lightweight TEMPORARY TABLE (plain INSERT, no
     *      index contention with the live table).
     *   2. Fire one JOIN UPDATE at the end — MySQL resolves it with a hash/index join in one pass.
     *
     * Only existing rows are updated; hotels absent from the live table are silently skipped,
     * so this is safe even when EN and RU dumps have minor mismatches.
     */
    private function importTranslations(string $dumpPath, string $language, ?callable $onBatch = null): int
    {
        $this->log()->info("[{$this->getSyncPrefix()}][{$language}] Starting translation import via temp table.", ['file' => $dumpPath]);

        $pk       = $this->getPrimaryKey();
        $tmpTable = 'tmp_etg_' . $this->getSyncPrefix() . '_' . $language;

        DB::disableQueryLog();

        [$handle, $isPipe] = $this->openDumpStream($dumpPath);

        // Peek at the first valid mapped record to learn column names before creating the temp table.
        $transColumns = null;
        $savedLine    = null;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '' || !is_array($data = json_decode($trimmed, true))) {
                continue;
            }
            $fields = $this->mapTranslationFields($data, $language);
            if ($fields !== null) {
                $transColumns = array_values(array_filter(array_keys($fields), fn ($k) => 'pk' !== $k));
                $savedLine    = $trimmed;
                break;
            }
        }

        if ($transColumns === null) {
            $this->closeDumpStream($handle, $isPipe);
            $this->log()->warning("[{$this->getSyncPrefix()}][{$language}] No translatable records found in file.");
            return 0;
        }

        // Build and create the temporary table.
        DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$tmpTable}`");
        $colDefs = ["`{$pk}` bigint NOT NULL"];
        foreach ($transColumns as $col) {
            $colDefs[] = "`{$col}` text NULL";
        }
        $colDefs[] = "PRIMARY KEY (`{$pk}`)";
        DB::statement('CREATE TEMPORARY TABLE `' . $tmpTable . '` (' . implode(', ', $colDefs) . ')');

        $mapRow = function (array $fields) use ($pk): array {
            $row = [$pk => $fields['pk']];
            foreach ($fields as $k => $v) {
                if ($k !== 'pk') {
                    $row[$k] = $v;
                }
            }
            return $row;
        };

        $batch      = [];
        $totalCount = 0;
        $batchCount = 0;

        // Seed the batch with the peeked first line.
        $firstData   = json_decode($savedLine, true);
        $firstFields = $this->mapTranslationFields($firstData, $language);
        $batch[]     = $mapRow($firstFields);

        DB::beginTransaction();

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '' || !is_array($data = json_decode($line, true))) {
                    continue;
                }

                $fields = $this->mapTranslationFields($data, $language);
                if ($fields === null) {
                    continue;
                }

                $batch[] = $mapRow($fields);

                if (count($batch) >= static::BATCH_SIZE) {
                    DB::table($tmpTable)->insert($batch);
                    $totalCount += count($batch);
                    $batchCount++;
                    $batch = [];

                    if ($batchCount % self::COMMIT_EVERY === 0) {
                        DB::commit();
                        if ($onBatch !== null) {
                            ($onBatch)($totalCount, $totalCount);
                        }
                        $this->log()->debug("[{$this->getSyncPrefix()}][{$language}] Batch inserted into temp table.", ['total_so_far' => $totalCount]);
                        DB::beginTransaction();
                    }
                }
            }

            if (!empty($batch)) {
                DB::table($tmpTable)->insert($batch);
                $totalCount += count($batch);
                if ($onBatch !== null) {
                    ($onBatch)($totalCount, $totalCount);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$tmpTable}`");
            throw $e;
        } finally {
            $this->closeDumpStream($handle, $isPipe);
        }

        // One JOIN UPDATE — replaces 6 000 CASE WHEN queries with a single MySQL pass.
        $setClauses   = array_map(fn ($col) => "h.`{$col}` = t.`{$col}`", $transColumns);
        $setClauses[] = 'h.`updated_at` = NOW()';

        $this->log()->info("[{$this->getSyncPrefix()}][{$language}] Applying {$totalCount} translations via JOIN UPDATE.");

        DB::statement(
            "UPDATE `{$this->getTable()}` h
             JOIN `{$tmpTable}` t ON h.`{$pk}` = t.`{$pk}`
             SET " . implode(', ', $setClauses)
        );

        DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$tmpTable}`");

        $this->log()->info("[{$this->getSyncPrefix()}][{$language}] Translation import complete.", ['total_updated' => $totalCount]);

        return $totalCount;
    }

    private function lastUpdateKey(): string
    {
        return $this->getSyncPrefix() . '_last_update';
    }

    private function pendingUpdateKey(): string
    {
        return $this->getSyncPrefix() . '_pending_update';
    }

    protected static function str(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        // Fast path: byte length ≤ max means character count is definitely ≤ max too
        // (UTF-8 chars are 1-4 bytes, so char_count ≤ byte_count). This avoids the
        // expensive mb_strlen scan for the vast majority of values that fit within limits.
        if (strlen($value) <= $max) {
            return $value;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    protected function log(): LoggerInterface
    {
        return Log::channel('etg');
    }
}
