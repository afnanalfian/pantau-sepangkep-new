<?php

namespace App\Services;

use App\Models\DriveFolder;
use App\Models\Task;
use App\Models\User;
use App\Support\WorkingDays;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integrasi Google Drive via REST API v3 (tanpa SDK).
 *
 * Struktur folder:
 *   PANTAU SEPANGKEP / {Nama Pegawai} / {Bulan Tahun} / {Judul Tugas} / file
 *   (judul sama di bulan yang sama → "Judul (2)", "Judul (3)", …)
 *
 * Mode autentikasi:
 *   - oauth           : refresh token akun Google kantor (direkomendasikan; file tersimpan di Drive akun tsb)
 *   - service_account : service account + Shared Drive (atau domain-wide delegation via GOOGLE_DRIVE_IMPERSONATE)
 */
class GoogleDriveService
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3';

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function enabled(): bool
    {
        $c = config('services.google_drive');
        if (! ($c['enabled'] ?? false)) {
            return false;
        }

        return ($c['auth'] ?? 'oauth') === 'service_account'
            ? ! empty($c['service_account_json']) && is_file($c['service_account_json'])
            : ! empty($c['client_id']) && ! empty($c['client_secret']) && $this->refreshToken();
    }

    /** Refresh token: dari .env, atau dari tombol "Hubungkan akun Google" (tersimpan terenkripsi). */
    public function refreshToken(): ?string
    {
        if ($t = config('services.google_drive.refresh_token')) {
            return $t;
        }
        $stored = \App\Support\Settings::get('google_refresh_token');
        try {
            return $stored ? decrypt($stored) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function oauthClientReady(): bool
    {
        return filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.client_secret'));
    }

    /* ======================================================= Token */

    public function accessToken(): string
    {
        return Cache::remember('gdrive_access_token', now()->addMinutes(50), function () {
            $c = config('services.google_drive');

            if (($c['auth'] ?? 'oauth') === 'service_account') {
                $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->serviceAccountJwt($c),
                ]);
            } else {
                $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $c['client_id'],
                    'client_secret' => $c['client_secret'],
                    'refresh_token' => $this->refreshToken(),
                ]);
            }

            if (! $res->successful() || ! $res->json('access_token')) {
                throw new RuntimeException('Gagal mendapatkan token Google: '.$res->body());
            }

            return $res->json('access_token');
        });
    }

    private function serviceAccountJwt(array $c): string
    {
        $sa = json_decode((string) file_get_contents($c['service_account_json']), true);
        $now = time();
        $claims = [
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        if (! empty($c['impersonate'])) {
            $claims['sub'] = $c['impersonate'];
        }
        $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode($claims));
        openssl_sign($unsigned, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.$b64($signature);
    }

    private function http()
    {
        return Http::withToken($this->accessToken())->timeout(60)->retry(2, 500, throw: false);
    }

    /* ======================================================= Folder */

    public function rootFolderId(): string
    {
        $parent = config('services.google_drive.parent_folder_id') ?: 'root';

        return $this->ensureFolder('root', config('services.google_drive.root_folder_name', 'PANTAU SEPANGKEP'), $parent);
    }

    public function userFolderId(User $user): string
    {
        $name = $user->name ? $user->name.' ('.$user->username.')' : $user->username;

        return $this->ensureFolder('user:'.$user->id, $name, $this->rootFolderId());
    }

    public function monthFolderId(User $user, $date): string
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : now();

        return $this->ensureFolder(
            'user:'.$user->id.':'.$date->format('Y-m'),
            WorkingDays::monthName($date),
            $this->userFolderId($user)
        );
    }

    /**
     * Nama folder tugas = judul tugas. Bila pegawai yang sama punya tugas lain berjudul sama
     * di bulan yang sama, folder berikutnya diberi akhiran " (2)", " (3)", … supaya file tidak tercampur.
     */
    public function taskFolderName(Task $task): string
    {
        $judul = Str::limit($this->sanitize($task->title), 100, '');
        $bulan = \Illuminate\Support\Carbon::parse($task->created_at ?? now());
        $sebelum = Task::withTrashed()
            ->where('assignee_id', $task->assignee_id)
            ->where('id', '<', $task->id)
            ->whereBetween('created_at', [$bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth()])
            ->pluck('title')
            ->filter(fn ($t) => mb_strtolower(Str::limit($this->sanitize((string) $t), 100, '')) === mb_strtolower($judul))
            ->count();

        return $sebelum > 0 ? $judul.' ('.($sebelum + 1).')' : $judul;
    }

    public function taskFolderId(Task $task): string
    {
        if ($task->drive_folder_id) {
            return $task->drive_folder_id;
        }
        $id = $this->ensureFolder('task:'.$task->id, $this->taskFolderName($task), $this->monthFolderId($task->assignee, $task->created_at));

        $task->forceFill([
            'drive_folder_id' => $id,
            'drive_folder_url' => 'https://drive.google.com/drive/folders/'.$id,
        ])->saveQuietly();

        return $id;
    }

    /**
     * Folder Drive milik pengguna (PANTAU SEPANGKEP / Nama Pegawai), dibuat bila belum ada,
     * dan dibagikan ke email pengguna (bisa melihat) bila emailnya diisi.
     *
     * @return array{id:string, url:string, dibagikan:?string}
     */
    public function userFolder(User $user): array
    {
        $id = $this->userFolderId($user);
        $email = trim((string) $user->email);
        $kunciBagi = 'share:user:'.$user->id;
        $sudah = DriveFolder::where('key', $kunciBagi)->value('drive_id');

        if ($email !== '' && $sudah !== mb_strtolower($email)) {
            $r = $this->http()->post(self::API.'/files/'.$id.'/permissions?supportsAllDrives=true&sendNotificationEmail=false', [
                'type' => 'user', 'role' => 'reader', 'emailAddress' => $email,
            ]);
            if ($r->successful()) {
                DriveFolder::updateOrCreate(['key' => $kunciBagi], ['drive_id' => mb_strtolower($email)]);
                $sudah = mb_strtolower($email);
            }
        }
        if (! $sudah) {
            $this->applySharing($id); // mengikuti GOOGLE_DRIVE_SHARE (domain / anyone) bila diatur
        }

        return ['id' => $id, 'url' => 'https://drive.google.com/drive/folders/'.$id, 'dibagikan' => $sudah];
    }

    /** ID folder pengguna yang sudah pernah dibuat (tanpa memanggil Drive). */
    public function cachedUserFolderId(User $user): ?string
    {
        return DriveFolder::where('key', 'user:'.$user->id)->value('drive_id');
    }

    /** Ganti nama file/folder di Drive. */
    public function rename(string $id, string $name): void
    {
        $this->http()->patch(self::API.'/files/'.$id.'?supportsAllDrives=true', ['name' => $this->sanitize($name)]);
    }

    /** Pindahkan file ke folder lain. */
    public function move(string $fileId, string $toFolderId): void
    {
        $parents = $this->http()->get(self::API.'/files/'.$fileId, ['fields' => 'parents', 'supportsAllDrives' => 'true'])->json('parents') ?? [];
        $hapus = implode(',', array_diff($parents, [$toFolderId]));
        if (in_array($toFolderId, $parents, true) && $hapus === '') {
            return;
        }
        $this->http()->patch(self::API.'/files/'.$fileId.'?'.http_build_query(array_filter([
            'supportsAllDrives' => 'true', 'addParents' => $toFolderId, 'removeParents' => $hapus ?: null,
        ])), []);
    }

    /** Jumlah isi folder (tidak termasuk sampah). */
    public function countChildren(string $folderId): int
    {
        return count($this->http()->get(self::API.'/files', [
            'q' => "'{$folderId}' in parents and trashed = false", 'fields' => 'files(id)', 'pageSize' => 10,
            'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
        ])->json('files') ?? []);
    }

    public function ensureFolder(string $key, string $name, string $parentId): string
    {
        $cached = DriveFolder::where('key', $key)->value('drive_id');
        if ($cached) {
            return $cached;
        }

        $name = $this->sanitize($name);
        $q = sprintf(
            "name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
            str_replace(["\\", "'"], ["\\\\", "\\'"], $name), self::FOLDER_MIME, $parentId
        );
        $found = $this->http()->get(self::API.'/files', [
            'q' => $q, 'fields' => 'files(id,name)', 'pageSize' => 1,
            'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
        ]);
        $id = $found->json('files.0.id');

        if (! $id) {
            $created = $this->http()->post(self::API.'/files?supportsAllDrives=true&fields=id', [
                'name' => $name, 'mimeType' => self::FOLDER_MIME, 'parents' => [$parentId],
            ]);
            if (! $created->successful()) {
                throw new RuntimeException('Gagal membuat folder Drive "'.$name.'": '.$created->body());
            }
            $id = $created->json('id');
        }

        DriveFolder::updateOrCreate(['key' => $key], ['drive_id' => $id]);

        return $id;
    }

    /* ======================================================= File */

    /** @return array{id:string, webViewLink:?string} */
    public function upload(string $absolutePath, string $name, ?string $mime, string $parentId): array
    {
        $mime = $mime ?: 'application/octet-stream';
        $init = $this->http()
            ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) filesize($absolutePath)])
            ->post(self::UPLOAD.'/files?uploadType=resumable&supportsAllDrives=true&fields=id,webViewLink', [
                'name' => $name, 'parents' => [$parentId],
            ]);

        $location = $init->header('Location');
        if (! $init->successful() || ! $location) {
            throw new RuntimeException('Gagal memulai upload: '.$init->body());
        }

        $res = Http::withToken($this->accessToken())->timeout(600)
            ->withBody(Utils::streamFor(fopen($absolutePath, 'r')), $mime)
            ->put($location);

        if (! $res->successful()) {
            throw new RuntimeException('Upload gagal: '.$res->body());
        }

        $id = $res->json('id');
        $this->applySharing($id);

        return ['id' => $id, 'webViewLink' => $res->json('webViewLink') ?: 'https://drive.google.com/file/d/'.$id.'/view'];
    }

    public function applySharing(string $fileId): void
    {
        $mode = config('services.google_drive.share');
        $body = match ($mode) {
            'anyone' => ['type' => 'anyone', 'role' => 'reader'],
            'domain' => ['type' => 'domain', 'role' => 'reader', 'domain' => config('services.google_drive.share_domain')],
            default => null,
        };
        if ($body && ($body['type'] !== 'domain' || ! empty($body['domain']))) {
            $this->http()->post(self::API.'/files/'.$fileId.'/permissions?supportsAllDrives=true', $body);
        }
    }

    /** Stream isi file dari Drive (untuk diunduh lewat aplikasi). */
    public function stream(string $fileId)
    {
        $res = Http::withToken($this->accessToken())->timeout(600)->withOptions(['stream' => true])
            ->get(self::API.'/files/'.$fileId, ['alt' => 'media', 'supportsAllDrives' => 'true']);

        if (! $res->successful()) {
            throw new RuntimeException('File tidak dapat diambil dari Drive.');
        }

        return $res->toPsrResponse()->getBody();
    }

    public function delete(string $fileId): void
    {
        // Pindahkan ke sampah (bisa dipulihkan), bukan hapus permanen.
        $this->http()->patch(self::API.'/files/'.$fileId.'?supportsAllDrives=true', ['trashed' => true]);
    }

    public function about(): array
    {
        return $this->http()->get(self::API.'/about', ['fields' => 'user(displayName,emailAddress),storageQuota'])->json() ?? [];
    }

    private function sanitize(string $name): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name)) ?: 'Tanpa Nama';
    }
}
