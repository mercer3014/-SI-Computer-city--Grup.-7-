<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class CaseInsensitiveUserProvider extends EloquentUserProvider
{
    /**
     * Postgres compara email con case-sensitive; el login ya normaliza a minúsculas.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if ($credentials === []) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);

                continue;
            }

            if ($key === 'email' && is_string($value)) {
                $query->whereRaw('lower(email) = ?', [Str::lower($value)]);

                continue;
            }

            $query->where($key, $value);
        }

        return $query->first();
    }
}
