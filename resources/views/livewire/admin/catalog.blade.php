<div>
    <x-page-header title="Katalog kegiatan" subtitle="Bobot poin terkunci di sini — ketua tim dan pegawai tidak bisa mengubahnya saat membuat tugas. 1 poin = 1 jam kerja efektif.">
        <x-slot:actions><button wire:click="create" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Tambah kegiatan</button></x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <div class="relative min-w-56 flex-1 sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari kegiatan" class="input py-2 pl-9">
        </div>
        <select wire:model.live="rumpun" class="input w-auto py-2"><option value="">Semua rumpun</option>@foreach ($rumpuns as $r)<option>{{ $r }}</option>@endforeach</select>
    </div>

    <div class="space-y-5">
        @forelse ($items as $group => $rows)
            <section class="card overflow-hidden">
                <h2 class="border-b border-line bg-navy-50/50 px-5 py-2.5 text-sm font-bold">{{ $group }} <span class="font-medium text-navy-500">({{ $rows->count() }})</span></h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $c)
                                <tr class="{{ $c->is_active ? '' : 'text-navy-300' }}" wire:key="cat-{{ $c->id }}">
                                    <td class="px-5 py-3"><p class="font-medium">{{ $c->name }}</p>@if ($c->rhk)<p class="text-xs text-navy-500">RHK: {{ \Illuminate\Support\Str::limit($c->rhk, 90) }}</p>@endif</td>
                                    <td class="w-40 px-3 py-3"><b>{{ poin($c->points) }}</b> poin <span class="text-navy-500">/ {{ $c->unit }}</span></td>
                                    <td class="w-28 px-3 py-3"><span class="chip bg-navy-50 text-navy-700 ring-line">{{ \App\Models\Task::COMPLEXITY[$c->complexity]['label'] }}</span></td>
                                    <td class="w-32 px-3 py-3 text-xs text-navy-500">{{ $c->team?->name ?? 'Semua tim' }}</td>
                                    <td class="w-36 px-5 py-3 text-right">
                                        <button wire:click="toggle({{ $c->id }})" class="btn btn-quiet btn-sm">{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        <button wire:click="edit({{ $c->id }})" class="btn btn-quiet p-1.5"><x-icon name="pencil" class="size-4" /></button>
                                        <button wire:click="delete({{ $c->id }})" wire:confirm="Hapus kegiatan ini?" class="btn btn-quiet p-1.5 hover:text-rose-600"><x-icon name="trash" class="size-4" /></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="card"><x-empty icon="book" title="Katalog kosong" /></div>
        @endforelse
    </div>

    <x-modal :show="$showForm" close="$set('showForm', false)" :title="$editId ? 'Ubah kegiatan' : 'Tambah kegiatan'">
        <form wire:submit="save" id="cat-form" class="space-y-4">
            <div><label class="label">Nama kegiatan</label><input wire:model="form.name" class="input @error('form.name') input-error @enderror">@error('form.name')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Rumpun jabatan</label><input wire:model="form.rumpun" list="rumpun-list" class="input"><datalist id="rumpun-list">@foreach ($rumpuns as $r)<option value="{{ $r }}">@endforeach</datalist></div>
                <div><label class="label">Tim (opsional)</label><select wire:model="form.team_id" class="input"><option value="">Semua tim</option>@foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                <div><label class="label">Poin per satuan</label><input wire:model="form.points" type="number" step="any" class="input @error('form.points') input-error @enderror">@error('form.points')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Satuan</label><input wire:model="form.unit" class="input" placeholder="mis. laporan, SLS, paket 10 surat"></div>
                <div class="col-span-2"><label class="label">Kompleksitas</label>
                    <select wire:model="form.complexity" class="input">@foreach (\App\Models\Task::COMPLEXITY as $k => $c)<option value="{{ $k }}">{{ $c['label'] }} (koef. {{ $c['coef'] }})</option>@endforeach</select></div>
            </div>
            <div><label class="label">Butir RHK SKP (KIPPAPP)</label><textarea wire:model="form.rhk" rows="2" class="input" placeholder="Rencana hasil kerja yang terkait"></textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_active" class="checkbox"> Aktif</label>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="cat-form" class="btn btn-blue">Simpan</button>
        </x-slot:footer>
    </x-modal>
</div>
