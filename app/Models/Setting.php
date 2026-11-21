<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function read(string $key, string $default = ''): string
    {
        return self::find($key)?->value ?? $default;
    }
}
