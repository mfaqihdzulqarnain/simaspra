<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasPublicCode
{
    /** Crockford Base32 tanpa I, L, O, U — cegah salah baca manusia */
    protected const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    protected const CODE_LENGTH = 8;

    public static function generatePublicCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }

            $exists = static::withoutGlobalScopes()
                ->where('public_code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'public_code';
    }

    public function scopeByPublicCode(Builder $query, string $code): Builder
    {
        return $query->where('public_code', strtoupper($code));
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/a/' . $this->public_code);
    }
}