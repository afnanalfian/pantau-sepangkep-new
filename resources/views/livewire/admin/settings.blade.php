<div>
    <x-page-header title="Pengaturan sistem" />
    <div class="grid gap-6 xl:grid-cols-[1fr_26rem] [&>*]:min-w-0">
        <section class="card card-pad">
            <h2 class="font-bold">Aturan kinerja</h2>
            <form wire:submit="save" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label">Nama kantor</label><input wire:model="s.office_name" class="input"></div>
                <div><label class="label">Target poin default / bulan</label><input wire:model="s.default_monthly_target" type="number" class="input"><p class="hint">PRD: 100–120 poin (5–6 JKE × 20–22 hari).</p></div>
                <div><label class="label">Pimpinan utama (penerima eskalasi)</label>
                    <select wire:model="s.primary_pimpinan_id" class="input"><option value="">Otomatis</option>@foreach ($pimpinans as $p)<option value="{{ $p->id }}">{{ $p->display_name }}</option>@endforeach</select>
                    @if ($pimpinans->isEmpty())<p class="hint text-bps-orange-700">Belum ada akun berperan Pimpinan. Atur di menu Pengguna.</p>@endif</div>
                <div><label class="label">Batas waktu verifikasi (jam kerja)</label><input wire:model="s.sla_hours" type="number" class="input"></div>
                <div><label class="label">Jika batas terlewati</label>
                    <select wire:model="s.sla_action" class="input"><option value="escalate">Eskalasi ke pimpinan</option><option value="auto_approve">Setujui otomatis</option></select>
                    <p class="hint">Saat verifikator cuti/DL, persetujuan otomatis selalu dibekukan.</p></div>
                <div><label class="label">Pengingat deadline (jam sebelum)</label><input wire:model="s.reminder_hours" type="number" class="input"></div>
                <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" wire:model="s.require_output_on_submit" class="checkbox"> Wajib lampirkan output sebelum “Tandai selesai”</label>
                <div class="sm:col-span-2"><button class="btn btn-primary">Simpan pengaturan</button></div>
            </form>
        </section>

        <aside class="space-y-6">
            <section class="card card-pad">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl {{ $driveEnabled ? 'bg-bps-green-50 text-bps-green-700' : 'bg-slate-100 text-slate-500' }}"><x-icon name="cloud" /></span>
                    <div><h2 class="font-bold">Google Drive</h2><p class="text-xs text-navy-500">{{ $driveEnabled ? 'Aktif – lampiran disimpan ke Drive' : 'Nonaktif – lampiran disimpan di server' }}</p></div>
                </div>
                @if ($driveAuth === 'oauth')
                    <div class="mt-4 rounded-xl bg-paper p-3 text-sm">
                        @if (! $oauthReady)
                            <p class="text-navy-700">Isi <code class="rounded bg-white px-1">GOOGLE_DRIVE_CLIENT_ID</code> &amp; <code class="rounded bg-white px-1">GOOGLE_DRIVE_CLIENT_SECRET</code> di .env (lihat panduan instalasi), lalu kembali ke sini.</p>
                        @elseif ($googleConnected)
                            <p class="flex items-center gap-2 font-semibold text-bps-green-700"><x-icon name="check-circle" class="size-4" /> Akun Google terhubung</p>
                            <div class="mt-2 flex gap-2">
                                <a href="{{ route('admin.google.connect') }}" class="btn btn-ghost btn-sm">Hubungkan ulang</a>
                                <form method="POST" action="{{ route('admin.google.disconnect') }}">@csrf<button class="btn btn-quiet btn-sm" onclick="return confirm('Putuskan akun Google?')">Putuskan</button></form>
                            </div>
                        @else
                            <p class="mb-2 text-navy-700">Masuk dengan akun Google kantor yang akan menyimpan folder <b>PANTAU SEPANGKEP</b>.</p>
                            <a href="{{ route('admin.google.connect') }}" class="btn btn-blue btn-sm">Hubungkan akun Google</a>
                        @endif
                        @unless ($driveFlagOn)<p class="mt-2 text-xs text-bps-orange-700">Catatan: GOOGLE_DRIVE_ENABLED masih false di .env.</p>@endunless
                    </div>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-2 text-center text-xs">
                    @foreach (['synced' => 'Tersimpan di Drive', 'pending' => 'Antre upload', 'failed' => 'Gagal', 'local' => 'Hanya di server'] as $k => $l)
                        <div class="rounded-lg bg-paper py-2"><dt class="text-navy-500">{{ $l }}</dt><dd class="text-lg font-bold">{{ $sync[$k] ?? 0 }}</dd></div>
                    @endforeach
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button wire:click="testDrive" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">Tes koneksi</button>
                    @if ($driveEnabled)<button wire:click="retryFailed" class="btn btn-ghost btn-sm">Upload ulang yang gagal / lokal</button>@endif
                </div>
                @if ($driveStatus)
                    <p class="mt-3 rounded-xl p-3 text-sm {{ $driveStatus['ok'] ? 'bg-bps-green-50 text-bps-green-700' : 'bg-rose-50 text-rose-700' }}">{{ $driveStatus['message'] }}
                        @if (! empty($driveStatus['url']))<a href="{{ $driveStatus['url'] }}" target="_blank" class="mt-1 block font-semibold underline">Buka folder PANTAU SEPANGKEP</a>@endif</p>
                @endif
            </section>

            <section class="card card-pad">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl {{ $pushReady ? 'bg-bps-blue-50 text-bps-blue-700' : 'bg-slate-100 text-slate-500' }}"><x-icon name="phone" /></span>
                    <div><h2 class="font-bold">Notifikasi push HP</h2><p class="text-xs text-navy-500">{{ $pushReady ? $pushDevices.' perangkat terdaftar' : 'Kunci VAPID belum diisi (php artisan pantau:vapid --write)' }}</p></div>
                </div>
            </section>

            <section class="card card-pad">
                <h2 class="font-bold">Antrean latar belakang</h2>
                <p class="mt-1 text-sm text-navy-500">{{ $queuePending }} pekerjaan menunggu · {{ $queueFailed }} gagal</p>
                @if ($queuePending > 20)<p class="mt-2 text-xs text-bps-orange-700">Antrean menumpuk — pastikan queue worker (supervisor) berjalan.</p>@endif
            </section>
        </aside>
    </div>
</div>
