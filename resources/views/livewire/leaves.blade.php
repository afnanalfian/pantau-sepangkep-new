<div>
    <x-page-header title="Cuti & dinas luar" subtitle="Selama cuti/DL, target poin disesuaikan prorata dan persetujuan tugas anggota dialihkan ke pimpinan.">
        <x-slot:actions><button wire:click="$set('showForm', true)" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Catat jadwal</button></x-slot:actions>
    </x-page-header>

    @if ($today->isNotEmpty())
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-bold text-navy-700">Sedang tidak di kantor hari ini</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($today as $l)
                    <span class="inline-flex items-center gap-2 rounded-full border border-line bg-white py-1 pr-3 pl-1 text-sm">
                        <x-avatar :user="$l->user" size="xs" /> {{ $l->user->display_name }} <span class="text-xs text-navy-500">{{ $l->type_label }} s.d. {{ tgl($l->end_date, 'j M') }}</span>
                    </span>
                @endforeach
            </div>
        </section>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="table-head"><tr><th class="px-5 py-3">Pegawai</th><th class="px-3 py-3">Jenis</th><th class="px-3 py-3">Tanggal</th><th class="px-3 py-3">Keterangan</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($items as $l)
                        <tr wire:key="lv-{{ $l->id }}">
                            <td class="px-5 py-3"><span class="flex items-center gap-2"><x-avatar :user="$l->user" size="xs" />{{ $l->user->display_name }}</span></td>
                            <td class="px-3 py-3"><span class="chip bg-navy-50 text-navy-700 ring-line">{{ $l->type_label }}</span></td>
                            <td class="px-3 py-3">{{ tgl($l->start_date) }} – {{ tgl($l->end_date) }}</td>
                            <td class="px-3 py-3 text-navy-500">{{ $l->note }}</td>
                            <td class="px-5 py-3 text-right"><button wire:click="delete({{ $l->id }})" wire:confirm="Hapus jadwal ini?" class="btn btn-quiet p-1.5 hover:text-rose-600"><x-icon name="trash" class="size-4" /></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="plane" title="Belum ada jadwal cuti / dinas luar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal :show="$showForm" close="$set('showForm', false)" title="Catat cuti / dinas luar">
        <form wire:submit="save" id="leave-form" class="space-y-4">
            @if ($manageAll)
                <div><label class="label">Pegawai</label>
                    <select wire:model="user_id" class="input">@foreach ($users as $us)<option value="{{ $us->id }}">{{ $us->display_name }}</option>@endforeach</select></div>
            @endif
            <div><label class="label">Jenis</label>
                <select wire:model="type" class="input">@foreach (\App\Models\Leave::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Dari</label><input wire:model="start_date" type="date" class="input"></div>
                <div><label class="label">Sampai</label><input wire:model="end_date" type="date" class="input @error('end_date') input-error @enderror">@error('end_date')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            <div><label class="label">Keterangan</label><input wire:model="note" class="input" placeholder="mis. No. surat tugas / tujuan"></div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="leave-form" class="btn btn-blue">Simpan</button>
        </x-slot:footer>
    </x-modal>
</div>
