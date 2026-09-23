<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value settings edited in the HERO admin UI. Values are stored as JSON.
 */
class Setting extends Model
{
    protected $table = 'setting';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);
        if ($row === null || $row->value === null) {
            return $default;
        }

        return json_decode($row->value, true);
    }

    public static function put(string $key, mixed $value): void
    {
        if ($value === null) {
            static::query()->whereKey($key)->delete();

            return;
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}
