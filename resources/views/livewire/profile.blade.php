<div>
    <x-page-header title="Profil saya" />
    <div class="grid gap-6 lg:grid-cols-[22rem_1fr] [&>*]:min-w-0">
        <section class="card overflow-hidden">
            <div class="flex flex-col items-center px-6 pt-8 pb-6 text-center">
                <label class="group relative cursor-pointer">
                    <x-avatar :user="$user" size="xl" />
                    <span class="absolute inset-0 grid place-items-center rounded-full bg-navy-900/60 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="photo" /></span>
                    <input type="file" wire:model="avatar" accept="image/*" class="sr-only">
                </label>
                <div wire:loading wire:target="avatar" class="mt-2 text-xs text-bps-blue-700">Mengunggah…</div>
                @error('avatar') <p class="error">{{ $message }}</p> @enderror
                <h2 class="mt-4 text-lg font-extrabold">{{ $user->display_name }}</h2>
                <p class="text-sm text-navy-500">{{ '@'.$user->username }}</p>
                <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                    @foreach ($user->roleLabels() as $r)<span class="chip bg-navy-50 text-navy-700 ring-line">{{ $r }}</span>@endforeach
                </div>
            </div>
            <dl class="divide-y divide-line border-t border-line text-sm">
                <div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">NIP</dt><dd class="font-medium">{{ $user->nip ?: '–' }}</dd></div>
                @if ($user->nip_lama)<div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">NIP lama</dt><dd class="font-medium">{{ $user->nip_lama }}</dd></div>@endif
                <div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">Jabatan</dt><dd class="font-medium">{{ $user->jabatan ?: '–' }}</dd></div>
                <div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">Pangkat</dt><dd class="font-medium">{{ $user->pangkat_golongan ?: '–' }}</dd></div>
                <div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">Tim</dt>
                    <dd class="font-medium">@forelse ($user->teams as $t){{ $t->name }}{{ $t->pivot->role === 'ketua' ? ' (Ketua)' : '' }}@if(!$loop->last), @endif @empty – @endforelse</dd></div>
                @if ($leave)<div class="flex px-5 py-3"><dt class="w-24 text-navy-500">Status</dt><dd class="font-medium text-amber-700">{{ $leave->type_label }} s.d. {{ tgl($leave->end_date) }}</dd></div>@endif
            </dl>
            @if ($driveAktif)
                <div class="border-t border-line px-5 py-4">
                    <p class="text-sm font-bold">Folder Google Drive saya</p>
                    <p class="mt-0.5 text-xs text-navy-500">Semua file tugas Anda tersimpan di sini, per bulan lalu per judul tugas.</p>
                    @if ($driveFolderId && $driveSiap)
                        <a href="https://drive.google.com/drive/folders/{{ $driveFolderId }}" target="_blank" rel="noopener" class="btn btn-blue btn-sm mt-3 w-full justify-center">
                            <x-icon name="external" class="size-4" /> Buka folder Drive</a>
                    @else
                        <button wire:click="bukaFolderDrive" wire:loading.attr="disabled" class="btn btn-blue btn-sm mt-3 w-full justify-center">
                            <x-icon name="external" class="size-4" /> <span wire:loading.remove wire:target="bukaFolderDrive">{{ $driveFolderId ? 'Bagikan folder ke email saya' : 'Siapkan folder Drive saya' }}</span><span wire:loading wire:target="bukaFolderDrive">Menyiapkan folder…</span></button>
                        @if ($driveFolderId)
                            <a href="https://drive.google.com/drive/folders/{{ $driveFolderId }}" target="_blank" rel="noopener" class="mt-2 block text-center text-xs font-semibold text-bps-blue-700">Buka folder Drive →</a>
                        @endif
                    @endif
                    @if (! $user->email)
                        <p class="mt-2 text-xs text-amber-700">Isi email Google Anda di bagian Kontak supaya folder ini dibagikan ke akun Anda dan bisa dibuka.</p>
                    @endif
                </div>
            @endif
            <p class="border-t border-line px-5 py-3 text-xs text-navy-500">Nama, NIP, jabatan, pangkat/golongan, dan tim diatur oleh admin.</p>
        </section>

        <div class="space-y-6">
            @php $now = $riwayat->first(); @endphp
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5">
                    <div><h2 class="font-bold">Matriks {{ bulan_tahun($now['bulan']) }}</h2><p class="text-xs text-navy-500">Sementara — tugas yang belum jatuh tempo belum dihitung</p></div>
                    <a href="{{ route('profile.matriks', $now['bulan']->format('Y-m')) }}" class="btn btn-ghost btn-sm"><x-icon name="download" class="size-4" /> PDF</a>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div><x-ring class="mx-auto" :pct="$now['tugas_pct']" color="text-bps-blue" /><p class="mt-1 text-xs font-semibold">Tugas</p><p class="text-[11px] text-navy-500">{{ $now['tugas_selesai'] }}/{{ $now['tugas_total'] }}</p></div>
                        <div><x-ring class="mx-auto" :pct="$now['apel_pct']" color="text-bps-orange" /><p class="mt-1 text-xs font-semibold">Apel</p><p class="text-[11px] text-navy-500">{{ $now['apel_hadir'] }}/{{ $now['apel_wajib'] }}</p></div>
                        <div><x-ring class="mx-auto" :pct="$now['rapat_pct']" color="text-bps-green" /><p class="mt-1 text-xs font-semibold">Rapat</p><p class="text-[11px] text-navy-500">{{ $now['rapat_hadir'] }}/{{ $now['rapat_wajib'] }}</p></div>
                    </div>
                    <div class="rounded-2xl bg-navy-900 px-6 py-4 text-center text-white">
                        <p class="text-xs text-navy-100/70">Nilai akhir</p>
                        <p class="text-4xl font-extrabold">{{ rtrim(rtrim(number_format($now['nilai'], 2, ',', '.'), '0'), ',') }}%</p>
                        <x-potongan :value="$now['potongan']" class="mt-2" />
                    </div>
                </div>
                @if ($now['tugas_items']->isNotEmpty())
                    <details class="border-t border-line">
                        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-bps-blue-700">Lihat {{ $now['tugas_semua'] }} tugas tanggungan bulan ini</summary>
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($now['tugas_items'] as $it)
                                <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                                    <span class="grid size-6 shrink-0 place-items-center rounded-full {{ $it['done'] ? 'bg-bps-green text-white' : ($it['pending'] ? 'bg-bps-blue-50 text-bps-blue-700' : 'bg-rose-50 text-rose-600') }}"><x-icon :name="$it['done'] ? 'check' : ($it['pending'] ? 'clock' : 'x')" class="size-3.5" /></span>
                                    <a href="{{ route('tasks.show', $it['task']->id) }}" wire:navigate class="min-w-0 flex-1 truncate hover:text-bps-blue-700">{{ $it['task']->title }}</a>
                                    @if ($it['carry'])<span class="chip bg-rose-50 text-rose-700 ring-rose-600/20">Tunggakan</span>@endif
                                    <span class="text-xs whitespace-nowrap text-navy-500">{{ tgl($it['due'], 'j M') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>

            <section class="card overflow-hidden">
                <h2 class="px-5 pt-5 font-bold">Riwayat matriks</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="table-head"><tr>
                            <th class="px-5 py-2.5">Bulan</th><th class="px-3 py-2.5 text-right">Tugas</th><th class="px-3 py-2.5 text-right">Apel</th>
                            <th class="px-3 py-2.5 text-right">Rapat</th><th class="px-3 py-2.5 text-right">Nilai</th><th class="px-3 py-2.5 text-right">Potongan</th><th class="px-5 py-2.5"></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($riwayat as $h)
                                <tr>
                                    <td class="px-5 py-2.5 font-medium">{{ bulan_tahun($h['bulan']) }}@if ($loop->first)<span class="ml-1 text-xs font-normal text-navy-500">(berjalan)</span>@endif</td>
                                    <td class="px-3 py-2.5 text-right"><x-pct :value="$h['tugas_pct']" :num="$h['tugas_selesai']" :den="$h['tugas_total']" /></td>
                                    <td class="px-3 py-2.5 text-right"><x-pct :value="$h['apel_pct']" :num="$h['apel_hadir']" :den="$h['apel_wajib']" /></td>
                                    <td class="px-3 py-2.5 text-right"><x-pct :value="$h['rapat_pct']" :num="$h['rapat_hadir']" :den="$h['rapat_wajib']" /></td>
                                    <td class="px-3 py-2.5 text-right"><x-pct :value="$h['nilai']" /></td>
                                    <td class="px-3 py-2.5 text-right"><x-potongan :value="$h['potongan']" /></td>
                                    <td class="px-5 py-2.5 text-right"><a href="{{ route('profile.matriks', $h['bulan']->format('Y-m')) }}" class="btn btn-quiet p-1.5" title="Unduh PDF"><x-icon name="download" class="size-4" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card card-pad">
                <h2 class="font-bold">Kontak</h2>
                <form wire:submit="saveContact" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Email</label><input wire:model="email" type="email" class="input @error('email') input-error @enderror">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">No. HP / WhatsApp</label><input wire:model="phone" class="input"></div>
                    <div class="sm:col-span-2"><button class="btn btn-primary">Simpan kontak</button></div>
                </form>
            </section>

            <section class="card card-pad">
                <h2 class="font-bold">Ganti password</h2>
                <form wire:submit="changePassword" class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div><label class="label">Password lama</label><input wire:model="current_password" type="password" class="input @error('current_password') input-error @enderror">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Password baru</label><input wire:model="password" type="password" class="input @error('password') input-error @enderror">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Ulangi password baru</label><input wire:model="password_confirmation" type="password" class="input"></div>
                    <div class="sm:col-span-3"><button class="btn btn-primary">Ganti password</button></div>
                </form>
            </section>
        </div>
    </div>
</div>
