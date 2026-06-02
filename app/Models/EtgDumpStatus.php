<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtgDumpStatus extends Model
{
    protected $table = 'etg_dump_status';

    protected $fillable = [
        'type',
        'language',
        'status',
        'progress',
        'file_size',
        'downloaded_bytes',
        'records_processed',
        'lines_processed',
        'total_records',
        'last_update',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'progress'    => 'integer',
    ];

    public function isRunning(): bool
    {
        return in_array($this->status, ['downloading', 'decompressing', 'importing'], true);
    }

    public function isIdle(): bool
    {
        return in_array($this->status, ['idle', 'finished', 'failed'], true);
    }

    public static function forTypeAndLanguage(string $type, string $language): static
    {
        return static::firstOrCreate(
            ['type' => $type, 'language' => $language],
            ['status' => 'idle', 'progress' => 0]
        );
    }

    public static function markStarted(string $type, string $language): void
    {
        static::where('type', $type)->where('language', $language)->update([
            'status'            => 'downloading',
            'progress'          => 0,
            'file_size'         => null,
            'downloaded_bytes'  => 0,
            'records_processed' => 0,
            'lines_processed'   => null,
            // total_records is intentionally preserved from the previous run so that
            // ImportDumpJob skips the expensive full-file line-count on every re-import.
            // It will be refreshed the very first time it is 0 (brand-new installation).
            'started_at'        => now(),
            'finished_at'       => null,
            'error_message'     => null,
        ]);
    }

    public static function markFailed(string $type, string $language, string $message): void
    {
        static::where('type', $type)->where('language', $language)->update([
            'status'        => 'failed',
            'error_message' => $message,
        ]);
    }

    public static function stampLastUpdate(string $type, string $lastUpdate): void
    {
        static::where('type', $type)->update(['last_update' => $lastUpdate]);
    }

    public static function cancelAll(string $type): void
    {
        static::where('type', $type)->update([
            'status'        => 'idle',
            'error_message' => 'Cancelled by user.',
        ]);
    }

    public static function markCancelled(string $type, string $language): void
    {
        static::where('type', $type)->where('language', $language)->update([
            'status'        => 'idle',
            'error_message' => 'Cancelled by user.',
        ]);
    }

    /** Reset failed state so user can retry. */
    public static function resetFailed(string $type, string $language): void
    {
        static::where('type', $type)->where('language', $language)->update([
            'status'        => 'idle',
            'error_message' => null,
        ]);
    }

    public static function isCancelled(string $type, string $language): bool
    {
        $row = static::where('type', $type)->where('language', $language)->first();
        return $row && $row->error_message === 'Cancelled by user.';
    }
}
