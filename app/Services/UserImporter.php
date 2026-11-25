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

    /** "0812-3456" / "+62 812…" → "628123456…". Kosong → null. */
    public static function normalizePhone(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw);
        if ($d === '') {
            return null;
        }

        return str_starts_with($d, '0') ? '62'.substr($d, 1) : $d;
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

    /**
     * Import biodata: username | nama | nip_baru | nip_lama | jabatan | golongan | pangkat | nomor_wa
     * (kolom "nip" lama tetap diterima sebagai NIP baru). Baris dicocokkan via username, atau NIP baru bila username kosong.
     */
    public function importBiodata(string $path): array
    {
        $rows = Spreadsheet::read($path);
        $res = ['updated' => [], 'notfound' => [], 'errors' => []];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $username = self::normalizeUsername($row['username'] ?? '');
            $nip = preg_replace('/\D/', '', (string) ($row['nip_baru'] ?? $row['nip'] ?? ''));

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
                'nip_lama' => preg_replace('/\D/', '', (string) ($row['nip_lama'] ?? '')) ?: null,
                'jabatan' => $row['jabatan'] ?? null,
                'golongan' => $row['golongan_akhir'] ?? $row['golongan'] ?? null,
                'pangkat' => $row['pangkat'] ?? null,
                'phone' => self::normalizePhone((string) ($row['nomor_wa'] ?? $row['no_wa'] ?? '')),
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
