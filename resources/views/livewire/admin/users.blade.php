@php $st = $this->stats; @endphp
<div>
    <x-page-header title="Kelola pengguna" subtitle="Peran sistem (admin/pimpinan) diatur di sini; ketua tim & anggota diatur di Kelola Tim.">
        <x-slot:actions>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="btn btn-ghost"><x-icon name="upload" class="size-4" /> Import Excel <x-icon name="chevron-down" class="size-4" /></button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-72 rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                    <button wire:click="openImport('akun')" @click="open = false" class="w-full rounded-xl px-3 py-2.5 text-left hover:bg-navy-50"><b class="block text-sm">Import akun</b><span class="text-xs text-navy-500">Kolom: username, password</span></button>
                    <button wire:click="openImport('biodata')" @click="open = false" class="w-full rounded-xl px-3 py-2.5 text-left hover:bg-navy-50"><b class="block text-sm">Import biodata</b><span class="text-xs text-navy-500">Kolom: username, nama, nip, jabatan</span></button>
                </div>
            </div>
            <button wire:click="create" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Tambah akun</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Total akun', $st['total'], null], ['Aktif', $st['active'], 'aktif'], ['Belum ganti password', $st['pending'], 'belum_ganti'], ['Biodata kosong', $st['no_bio'], null]] as [$l, $v, $f])
            <button @if($f) wire:click="$set('status', '{{ $status === $f ? '' : $f }}')" @endif class="card p-4 text-left {{ $f && $status === $f ? 'ring-2 ring-navy-900' : '' }}">
                <p class="text-xs text-navy-500">{{ $l }}</p><p class="mt-1 text-2xl font-extrabold">{{ $v }}</p>
            </button>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <div class="relative min-w-56 flex-1 sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari username, nama, NIP" class="input py-2 pl-9">
        </div>
        <select wire:model.live="role" class="input w-auto py-2">
            <option value="">Semua peran</option><option value="admin">Administrator</option><option value="pimpinan">Pimpinan</option><option value="ketua">Ketua tim</option>
        </select>
        <select wire:model.live="status" class="input w-auto py-2">
            <option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option><option value="belum_ganti">Belum ganti password</option>
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="table-head"><tr>
                    <th class="px-5 py-3">Pegawai</th><th class="px-3 py-3">NIP / Jabatan</th><th class="px-3 py-3">Peran & tim</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Login terakhir</th><th class="px-5 py-3 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($users as $u)
                        <tr class="{{ $u->is_active ? '' : 'bg-paper/70 text-navy-500' }}" wire:key="u-{{ $u->id }}">
                            <td class="px-5 py-3"><span class="flex items-center gap-3"><x-avatar :user="$u" size="sm" />
                                <span class="min-w-0"><span class="block truncate font-semibold">{{ $u->name ?: '— belum diisi —' }}</span><span class="text-xs text-navy-500">{{ '@'.$u->username }}</span></span></span></td>
                            <td class="px-3 py-3"><span class="block">{{ $u->nip ?: '–' }}</span><span class="text-xs text-navy-500">{{ $u->jabatan }}</span></td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @if ($u->is_admin)<span class="chip bg-navy-900 text-white ring-navy-900">Admin</span>@endif
                                    @if ($u->is_pimpinan)<span class="chip bg-bps-orange-50 text-bps-orange-700 ring-bps-orange/30">Pimpinan</span>@endif
                                    @foreach ($u->teams as $t)
                                        <span class="chip {{ $t->pivot->role === 'ketua' ? 'bg-bps-blue-50 text-bps-blue-700 ring-bps-blue/30' : 'bg-white text-navy-700 ring-line' }}">{{ $t->pivot->role === 'ketua' ? '★ ' : '' }}{{ \Illuminate\Support\Str::after($t->name, 'Tim ') }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                @if (! $u->is_active)<span class="chip bg-slate-100 text-slate-600 ring-slate-400/30">Nonaktif</span>
                                @elseif ($u->must_change_password)<span class="chip bg-amber-50 text-amber-700 ring-amber-600/20">Belum ganti password</span>
                                @else<span class="chip bg-bps-green-50 text-bps-green-700 ring-bps-green/40">Aktif</span>@endif
                            </td>
                            <td class="px-3 py-3 text-xs text-navy-500">{{ $u->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="edit({{ $u->id }})" class="btn btn-quiet p-1.5" title="Ubah"><x-icon name="pencil" class="size-4" /></button>
                                    <button wire:click="resetPassword({{ $u->id }})" wire:confirm="Reset password {{ $u->username }}? Password baru akan ditampilkan." class="btn btn-quiet p-1.5" title="Reset password"><x-icon name="key" class="size-4" /></button>
                                    @if ($u->id !== auth()->id())
                                        <button wire:click="toggleActive({{ $u->id }})" class="btn btn-quiet p-1.5" title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><x-icon :name="$u->is_active ? 'lock' : 'check-circle'" class="size-4" /></button>
                                        <button wire:click="confirmDelete({{ $u->id }})" class="btn btn-quiet p-1.5 hover:text-rose-600" title="Hapus"><x-icon name="trash" class="size-4" /></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="users" title="Belum ada pengguna yang cocok" text="Import akun dari Excel untuk membuat banyak akun sekaligus." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-line px-5 py-3">{{ $users->links() }}</div>@endif
    </div>

    {{-- ========== Form akun ========== --}}
    <x-modal :show="$showForm" close="$set('showForm', false)" :title="$editId ? 'Ubah akun' : 'Tambah akun'" width="max-w-2xl">
        @if ($generatedPassword)
            <div class="rounded-2xl bg-bps-green-50 p-5 text-center">
                <p class="text-sm text-bps-green-700">Password untuk <b>{{ $form['username'] ?? '' }}</b>:</p>
                <p class="mt-2 font-mono text-2xl font-bold tracking-wider select-all">{{ $generatedPassword }}</p>
                <p class="mt-2 text-xs text-navy-500">Berikan ke pegawai. Mereka wajib menggantinya saat login pertama.</p>
            </div>
        @else
            <form wire:submit="save" id="user-form" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Username</label><input wire:model="form.username" class="input @error('form.username') input-error @enderror" autocapitalize="none">@error('form.username')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">{{ $editId ? 'Password baru (kosongkan bila tetap)' : 'Password awal' }}</label><input wire:model="form.password" class="input font-mono @error('form.password') input-error @enderror">@error('form.password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="label">Nama lengkap (dengan gelar)</label><input wire:model="form.name" class="input"></div>
                    <div><label class="label">NIP</label><input wire:model="form.nip" class="input" inputmode="numeric"></div>
                    <div><label class="label">Jabatan</label><input wire:model="form.jabatan" class="input" placeholder="mis. Statistisi Ahli Pertama"></div>
                    <div><label class="label">Email</label><input wire:model="form.email" type="email" class="input"></div>
                    <div><label class="label">Target poin / bulan</label><input wire:model="form.monthly_target" type="number" class="input" placeholder="default {{ \App\Support\Settings::get('default_monthly_target') }}"></div>
                </div>

                <fieldset>
                    <legend class="label">Peran sistem</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label class="flex items-center gap-2 rounded-xl border border-line p-3 text-sm"><input type="checkbox" wire:model="form.is_admin" class="checkbox"> Administrator</label>
                        <label class="flex items-center gap-2 rounded-xl border border-line p-3 text-sm"><input type="checkbox" wire:model="form.is_pimpinan" class="checkbox"> Pimpinan</label>
                        <label class="flex items-center gap-2 rounded-xl border border-line p-3 text-sm"><input type="checkbox" wire:model="form.is_active" class="checkbox"> Akun aktif</label>
                    </div>
                    @error('form.is_admin')<p class="error">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset>
                    <legend class="label">Tim</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($this->teams as $t)
                            <div class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm" wire:key="ft-{{ $t->id }}">
                                <span class="size-2.5 rounded-sm {{ $t->color() }}"></span><span class="flex-1">{{ $t->name }}</span>
                                <select wire:model="formTeams.{{ $t->id }}" class="rounded-lg border-line py-1 text-xs">
                                    <option value="">—</option><option value="anggota">Anggota</option><option value="ketua">Ketua</option>
                                </select>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </form>
        @endif
        <x-slot:footer>
            @if ($generatedPassword)
                <button wire:click="$set('showForm', false)" class="btn btn-primary">Selesai</button>
            @else
                <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
                <button type="submit" form="user-form" class="btn btn-blue">Simpan</button>
            @endif
        </x-slot:footer>
    </x-modal>

    {{-- ========== Import ========== --}}
    <x-modal :show="(bool) $importType" close="$set('importType', null)" :title="$importType === 'biodata' ? 'Import biodata pegawai' : 'Import akun pegawai'">
        @if (! $importResult)
            <ol class="mb-4 list-decimal space-y-1 pl-5 text-sm text-navy-700">
                <li>Unduh template, isi di Excel.
                    <a href="{{ route('admin.template', $importType ?? 'akun') }}" class="font-semibold text-bps-blue-700 hover:underline">Unduh template {{ $importType }}</a></li>
                @if ($importType === 'biodata')
                    <li>Kolom: <b>username, nama, nip, jabatan</b>. Template sudah berisi semua username terdaftar.</li>
                @else
                    <li>Kolom: <b>username, password</b>. Username yang sudah ada akan dilewati.</li>
                    <li>Semua akun baru wajib ganti password saat login pertama.</li>
                @endif
                <li>Unggah file .xlsx / .csv di bawah.</li>
            </ol>
            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="input">
            <div wire:loading wire:target="importFile" class="hint">Mengunggah…</div>
            @error('importFile')<p class="error">{{ $message }}</p>@enderror
        @else
            @php $r = $importResult; @endphp
            <div class="space-y-3 text-sm">
                @if (isset($r['created']))
                    <p class="rounded-xl bg-bps-green-50 p-3 text-bps-green-700"><b>{{ count($r['created']) }}</b> akun dibuat{{ count($r['created']) ? ': '.implode(', ', array_slice($r['created'], 0, 40)) : '' }}</p>
                    @if ($r['skipped'])<p class="rounded-xl bg-navy-50 p-3"><b>{{ count($r['skipped']) }}</b> dilewati (sudah ada): {{ implode(', ', $r['skipped']) }}</p>@endif
                @else
                    <p class="rounded-xl bg-bps-green-50 p-3 text-bps-green-700"><b>{{ count($r['updated']) }}</b> biodata diperbarui.</p>
                    @if ($r['notfound'])<p class="rounded-xl bg-amber-50 p-3 text-amber-800"><b>{{ count($r['notfound']) }}</b> tidak ditemukan: {{ implode(', ', $r['notfound']) }}</p>@endif
                @endif
                @if ($r['errors'])<div class="rounded-xl bg-rose-50 p-3 text-rose-700">@foreach ($r['errors'] as $e)<p>{{ $e }}</p>@endforeach</div>@endif
            </div>
        @endif
        <x-slot:footer>
            <button wire:click="$set('importType', null)" class="btn btn-ghost">Tutup</button>
            @unless ($importResult)<button wire:click="runImport" class="btn btn-blue" wire:loading.attr="disabled">Proses import</button>@endunless
        </x-slot:footer>
    </x-modal>

    {{-- ========== Hapus ========== --}}
    <x-modal :show="(bool) $confirmDeleteId" close="$set('confirmDeleteId', null)" title="Hapus akun?" subtitle="Akun tidak bisa login lagi. Riwayat tugas & poin tetap tersimpan untuk laporan.">
        <x-slot:footer>
            <button wire:click="$set('confirmDeleteId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="delete" class="btn btn-danger">Hapus akun</button>
        </x-slot:footer>
    </x-modal>
</div>
