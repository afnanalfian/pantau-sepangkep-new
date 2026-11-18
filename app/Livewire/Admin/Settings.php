<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\GoogleDriveService;
use App\Support\Settings as AppSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan Sistem')]
class Settings extends Component
{
    use Toasts;

    public array $s = [];

    public ?array $driveStatus = null;

    public function mount(): void
    {
        if ($msg = session('toast')) {
            $this->dispatch('toast', message: $msg, type: 'info');
        }
        $all = AppSettings::all();
        $this->s = [
            'office_name' => $all['office_name'],
            'bobot_tugas' => (float) $all['bobot_tugas'],
            'bobot_apel' => (float) $all['bobot_apel'],
            'bobot_rapat' => (float) $all['bobot_rapat'],
            'batas_potongan' => (float) $all['batas_potongan'],
            'potongan_ringan' => (float) $all['potongan_ringan'],
            'potongan_berat' => (float) $all['potongan_berat'],
            'beban_ringan' => (int) $all['beban_ringan'],
            'beban_padat' => (int) $all['beban_padat'],
            'sla_hours' => (int) $all['sla_hours'],
            'sla_action' => $all['sla_action'],
            'reminder_hours' => (int) $all['reminder_hours'],
            'primary_pimpinan_id' => $all['primary_pimpinan_id'],
            'require_output_on_submit' => (bool) $all['require_output_on_submit'],
        ];
    }

    public function save(): void
    {
        $this->validate([
            's.office_name' => 'required|string|max:150',
            's.bobot_tugas' => 'required|numeric|min:0|max:100',
            's.bobot_apel' => 'required|numeric|min:0|max:100',
            's.bobot_rapat' => 'required|numeric|min:0|max:100',
            's.batas_potongan' => 'required|numeric|min:0|max:99.99',
            's.potongan_ringan' => 'required|numeric|min:0|max:100',
            's.potongan_berat' => 'required|numeric|min:0|max:100',
            's.beban_ringan' => 'required|integer|min:1|max:50',
            's.beban_padat' => 'required|integer|min:1|max:100|gte:s.beban_ringan',
            's.sla_hours' => 'required|integer|min:4|max:240',
            's.sla_action' => 'required|in:escalate,auto_approve',
            's.reminder_hours' => 'required|integer|min:1|max:168',
            's.primary_pimpinan_id' => 'nullable|exists:users,id',
            's.require_output_on_submit' => 'boolean',
        ]);
        if ((float) $this->s['bobot_tugas'] + (float) $this->s['bobot_apel'] + (float) $this->s['bobot_rapat'] <= 0) {
            $this->addError('s.bobot_tugas', 'Minimal satu bobot harus lebih dari 0.');

            return;
        }
        foreach ($this->s as $k => $v) {
            AppSettings::set($k, $v === '' ? null : $v);
        }
        $this->toast('Pengaturan disimpan.');
    }

    public function testDrive(GoogleDriveService $drive): void
    {
        if (! $drive->enabled()) {
            $this->driveStatus = ['ok' => false, 'message' => 'Google Drive belum diaktifkan / kredensial belum lengkap di file .env.'];

            return;
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('gdrive_access_token');
            $about = $drive->about();
            $root = $drive->rootFolderId();
            $this->driveStatus = [
                'ok' => true,
                'message' => 'Terhubung sebagai '.($about['user']['displayName'] ?? '-').' ('.($about['user']['emailAddress'] ?? '-').')',
                'url' => 'https://drive.google.com/drive/folders/'.$root,
            ];
        } catch (\Throwable $e) {
            $this->driveStatus = ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function retryFailed(): void
    {
        $ids = TaskAttachment::whereIn('sync_status', ['failed', 'local'])->whereNotNull('local_path')->pluck('id');
        TaskAttachment::whereIn('id', $ids)->update(['sync_status' => 'pending', 'sync_error' => null]);
        foreach ($ids as $id) {
            \App\Jobs\SyncAttachmentToDrive::dispatch($id);
        }
        $this->toast($ids->count().' file dijadwalkan untuk diunggah ulang ke Drive.');
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'pimpinans' => User::active()->where('is_pimpinan', true)->get(),
            'driveEnabled' => app(GoogleDriveService::class)->enabled(),
            'driveFlagOn' => (bool) config('services.google_drive.enabled'),
            'driveAuth' => config('services.google_drive.auth'),
            'oauthReady' => app(GoogleDriveService::class)->oauthClientReady(),
            'googleConnected' => (bool) app(GoogleDriveService::class)->refreshToken(),
            'pushReady' => WebPushChannel::configured(),
            'sync' => TaskAttachment::where('kind', 'file')->selectRaw('sync_status, COUNT(*) as n')->groupBy('sync_status')->pluck('n', 'sync_status'),
            'queuePending' => rescue(fn () => DB::table('jobs')->count(), 0, false),
            'queueFailed' => rescue(fn () => DB::table('failed_jobs')->count(), 0, false),
            'pushDevices' => \App\Models\PushSubscription::count(),
        ]);
    }
}
