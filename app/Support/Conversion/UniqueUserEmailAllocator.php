<?php

namespace App\Support\Conversion;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class UniqueUserEmailAllocator
{
    public function __construct(
        protected string $connection,
    ) {}

    /**
     * @param  array<string, true>  $seenEmails
     */
    public function allocate(string $email, ?int $ignoreUserId = null, array $seenEmails = []): string
    {
        $email = trim($email);

        if ($email === '') {
            $email = 'user'.($ignoreUserId ?? random_int(100000, 999999)).'@imported.invalid';
        }

        if (! $this->isTaken($email, $ignoreUserId, $seenEmails)) {
            return $email;
        }

        [$local, $domain] = $this->splitEmail($email);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $candidate = $this->composeEmail($local, (string) random_int(100000, 999999), $domain);

            if (! $this->isTaken($candidate, $ignoreUserId, $seenEmails)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Could not allocate a unique email for [{$email}].");
    }

    /**
     * @param  array<string, true>  $seenEmails
     */
    public function isTaken(string $email, ?int $ignoreUserId = null, array $seenEmails = []): bool
    {
        $key = mb_strtolower(trim($email));

        if ($key !== '' && isset($seenEmails[$key])) {
            return true;
        }

        $query = DB::connection($this->connection)
            ->table('users')
            ->whereRaw('LOWER(LTRIM(RTRIM(CAST(email AS nvarchar(255))))) = ?', [$key]);

        if ($ignoreUserId !== null) {
            $query->where('id', '<>', $ignoreUserId);
        }

        return $query->exists();
    }

    /** @return array{0: string, 1: string} */
    protected function splitEmail(string $email): array
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return [$email !== '' ? $email : 'user', 'imported.invalid'];
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);

        return [
            $local !== '' ? $local : 'user',
            $domain !== '' ? $domain : 'imported.invalid',
        ];
    }

    protected function composeEmail(string $local, string $suffix, string $domain): string
    {
        $candidate = $local.'.'.$suffix.'@'.$domain;

        if (strlen($candidate) <= 255) {
            return $candidate;
        }

        $maxLocal = max(1, 255 - strlen($suffix) - strlen($domain) - 2);

        return substr($local, 0, $maxLocal).'.'.$suffix.'@'.$domain;
    }
}
