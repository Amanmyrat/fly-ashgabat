<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtgSyncState extends Model
{
    protected $table = 'etg_sync_state';

    /**
     * Primary key is a string slug, e.g. "hotel_dump_last_update".
     */
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table has no created_at column — only updated_at.
     */
    const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
    ];

    // -------------------------------------------------------------------------
    // Convenience accessors
    // -------------------------------------------------------------------------

    /**
     * Retrieve the value for a given key, or null if the key doesn't exist.
     */
    public static function getValue(string $key): ?string
    {
        return static::find($key)?->value;
    }

    /**
     * Persist a key/value pair, creating or updating as needed.
     */
    public static function setValue(string $key, string $value): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Remove a key entirely.
     */
    public static function removeKey(string $key): void
    {
        static::destroy($key);
    }
}
