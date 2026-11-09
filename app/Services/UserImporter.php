<?php

namespace App\Services;

use App\Models\User;
use App\Support\Spreadsheet;
use Illuminate\Support\Str;

class UserImporter
{
    /** Normalisasi username: huruf kecil, buang spasi/karakter tak terlihat & kata sisipan (mis. "mega.ayu Bu" → "mega.ayu"). */
    public static function normalizeUsername(string $raw): string
    {
        $u = Str::lower(Spreadsheet::clean($raw));
        $u = ltrim($u, "-•* \t");
        $u = preg_split('/\s+/', $u)[0] ?? '';

        return preg_replace('/[^a-z0-9._@-]/', '', $u);
    }

    /**
     * Import akun dari Excel kolom: username | password.
     * Akun yang sudah ada dilewati. Semua akun baru wajib ganti password saat login pertama.
     */
    public function importAccounts(string $path): array
    {
        $rows = Spreadsheet::read($path);
        $res = ['created' => [], 'skipped' => [], 'errors' => []];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $username = self::normalizeUsername($row['username'] ?? '');
            $password = (string) ($row['password'] ?? '');

            if ($username === '') {
                $res['errors'][] = "Baris {$line}: username kosong.";

                continue;
            }
            if (strlen($password) < 6) {
                $res['errors'][] = "Baris {$line} ({$username}): password minimal 6 karakter.";

                continue;
            }
            if (User::withTrashed()->where('username', $username)->exists()) {
                $res['skipped'][] = $username;

                continue;
            }

            User::create([
                'username' => $username,
                'password' => $password,
                'must_change_password' => true,
                'is_active' => true,
            ]);
            $res['created'][] = $username;
        }

        return $res;
    }

    /** Import biodata: username | nama | nip | jabatan  (baris dicocokkan via username, atau NIP bila username kosong). */
    public function importBiodata(string $path): array
    {
        $rows = Spreadsheet::read($path);
        $res = ['updated' => [], 'notfound' => [], 'errors' => []];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $username = self::normalizeUsername($row['username'] ?? '');
            $nip = preg_replace('/\D/', '', (string) ($row['nip'] ?? ''));

            $user = $username !== ''
                ? User::where('username', $username)->first()
                : ($nip !== '' ? User::where('nip', $nip)->first() : null);

            if (! $user) {
                $res['notfound'][] = $username ?: ($nip ?: "baris {$line}");

                continue;
            }

            $data = array_filter([
                'name' => $row['nama'] ?? $row['name'] ?? null,
                'nip' => $nip ?: null,
                'jabatan' => $row['jabatan'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            if (! $data) {
                continue;
            }
            $user->update($data);
            $res['updated'][] = $user->username;
        }

        return $res;
    }
}
