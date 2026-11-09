@php
    $u = auth()->user();
    $titles = [
        'assign' => ['Beri tugas', 'Penerima mendapat notifikasi di web dan HP.'],
        'mandiri' => ['Ajukan tugas mandiri', 'Pengajuan diverifikasi ketua tim (atau pimpinan bila Anda ketua tim) sebelum poinnya dihitung.'],
        'delegate' => ['Teruskan ke anggota', 'Pecah tugas ini menjadi sub-tugas untuk anggota tim. Anda yang memverifikasi hasilnya.'],
        'edit' => ['Ubah tugas', null],
    ];
@endphp
<div>
    <x-modal :show="$open" close="close" width="max-w-3xl" :title="$titles[$mode][0]" :subtitle="$titles[$mode][1]">
        @if (in_array($mode, ['assign', 'mandiri']) && $u->canAssign())
            <div class="mb-5 inline-flex rounded-xl bg-navy-50 p-1">
                <button type="button" wire:click="$set('mode', 'assign')" class="tab {{ $mode === 'assign' ? 'active' : '' }}">Beri tugas ke orang lain</button>
                <button type="button" wire:click="$set('mode', 'mandiri')" class="tab {{ $mode === 'mandiri' ? 'active' : '' }}">Ajukan untuk diri sendiri</button>
            </div>
        @endif

        <form wire:submit="save" id="task-form" class="grid gap-5 lg:grid-cols-[1fr_17rem]">
            <div class="space-y-4">
                <div>
                    <label class="label" for="tf-catalog">Kegiatan dari katalog</label>
                    <select wire:model.live="catalog_id" id="tf-catalog" class="input">
                        <option value="">— Kegiatan ad-hoc (poin = jam kerja × koefisien) —</option>
                        @foreach ($this->catalogs->groupBy('rumpun') as $rumpun => $items)
                            <optgroup label="{{ $rumpun ?: 'Lainnya' }}">
                                @foreach ($items as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ poin($c->points) }} poin / {{ $c->unit }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @if ($this->selectedCatalog?->rhk)
                        <p class="hint">RHK: {{ $this->selectedCatalog->rhk }}</p>
                    @endif
                </div>

                <div>
                    <label class="label" for="tf-title">Judul tugas</label>
                    <input wire:model="title" id="tf-title" class="input @error('title') input-error @enderror" placeholder="mis. Validasi data Susenas Kec. Bungoro">
                    @error('title') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="tf-desc">Instruksi / uraian</label>
                    <textarea wire:model="description" id="tf-desc" rows="4" class="input" placeholder="Jelaskan apa yang harus dikerjakan, output yang diharapkan, dan catatan penting."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="label">{{ $this->selectedCatalog ? 'Volume ('.$this->selectedCatalog->unit.')' : 'Estimasi jam' }}</label>
                        <input wire:model.live.debounce.300ms="quantity" type="number" step="any" min="0.1" class="input @error('quantity') input-error @enderror">
                    </div>
                    <div>
                        <label class="label">Kompleksitas</label>
                        <select wire:model.live="complexity" class="input" @disabled($this->selectedCatalog)>
                            @foreach (\App\Models\Task::COMPLEXITY as $k => $c)<option value="{{ $k }}">{{ $c['label'] }} ×{{ $c['coef'] }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Prioritas</label>
                        <select wire:model="priority" class="input">
                            @foreach (\App\Models\Task::PRIORITIES as $k => $p)<option value="{{ $k }}">{{ $p['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Tim</label>
                        <select wire:model="team_id" class="input">
                            <option value="">—</option>
                            @foreach ($this->teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Mulai</label>
                        <input wire:model="start_date" type="date" class="input">
                    </div>
                    <div>
                        <label class="label">Deadline</label>
                        <input wire:model="due_date" type="date" class="input @error('due_date') input-error @enderror">
                        @error('due_date') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Lampiran awal --}}
                @if ($mode !== 'edit')
                    <div class="rounded-2xl border border-dashed border-navy-100 p-4">
                        <p class="text-sm font-semibold">Lampiran (opsional)</p>
                        <p class="text-xs text-navy-500">Bisa ditambah lagi nanti dari halaman detail tugas. Maks. 50 MB per file.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label class="btn btn-ghost cursor-pointer justify-start">
                                <x-icon name="document" class="size-4 text-bps-orange-600" /> Surat tugas
                                <input type="file" wire:model="newSurat" multiple class="sr-only" accept=".pdf,.doc,.docx,image/*">
                            </label>
                            <label class="btn btn-ghost cursor-pointer justify-start">
                                <x-icon name="clip" class="size-4 text-bps-blue" /> Dokumen pendukung
                                <input type="file" wire:model="newDokumen" multiple class="sr-only">
                            </label>
                        </div>
                        <div wire:loading wire:target="newSurat,newDokumen" class="mt-2 text-xs text-bps-blue-700">Mengunggah…</div>
                        @error('newSurat.*') <p class="error">{{ $message }}</p> @enderror
                        @error('newDokumen.*') <p class="error">{{ $message }}</p> @enderror

                        @if ($suratTugas || $dokumen)
                            <ul class="mt-3 space-y-1.5 text-sm">
                                @foreach ($suratTugas as $i => $f)
                                    <li class="flex items-center gap-2 rounded-lg bg-bps-orange-50 px-3 py-1.5"><span class="text-xs font-semibold text-bps-orange-700">Surat tugas</span><span class="min-w-0 flex-1 truncate">{{ $f->getClientOriginalName() }}</span><button type="button" wire:click="removeStaged('suratTugas', {{ $i }})" class="text-navy-300 hover:text-rose-600"><x-icon name="x" class="size-4" /></button></li>
                                @endforeach
                                @foreach ($dokumen as $i => $f)
                                    <li class="flex items-center gap-2 rounded-lg bg-bps-blue-50 px-3 py-1.5"><span class="text-xs font-semibold text-bps-blue-700">Dokumen</span><span class="min-w-0 flex-1 truncate">{{ $f->getClientOriginalName() }}</span><button type="button" wire:click="removeStaged('dokumen', {{ $i }})" class="text-navy-300 hover:text-rose-600"><x-icon name="x" class="size-4" /></button></li>
                                @endforeach
                            </ul>
                        @endif

                        @foreach ($links as $i => $l)
                            <div class="mt-2 flex gap-2" wire:key="link-{{ $i }}">
                                <input wire:model="links.{{ $i }}.url" placeholder="https://drive.google.com/…" class="input flex-1 py-2">
                                <input wire:model="links.{{ $i }}.name" placeholder="Nama link" class="input w-36 py-2">
                                <button type="button" wire:click="removeLink({{ $i }})" class="btn btn-quiet px-2"><x-icon name="x" class="size-4" /></button>
                            </div>
                        @endforeach
                        <button type="button" wire:click="addLink" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-bps-blue-700 hover:underline"><x-icon name="link" class="size-4" /> Tambah link</button>
                    </div>
                @endif
            </div>

            {{-- Kolom kanan: poin & penerima --}}
            <div class="space-y-4">
                <div class="rounded-2xl bg-navy-900 p-4 text-white">
                    <p class="text-xs text-navy-100/70">Bobot poin tugas</p>
                    <p class="mt-1 text-4xl font-extrabold">{{ poin($this->points) }}</p>
                    <p class="mt-1 text-xs text-navy-100/70">
                        @if ($this->selectedCatalog)
                            {{ poin($this->selectedCatalog->points) }} × {{ poin((float) $quantity) }} {{ $this->selectedCatalog->unit }} (dikunci katalog)
                        @else
                            {{ poin((float) $quantity) }} jam × {{ \App\Models\Task::COMPLEXITY[$complexity]['coef'] }} koefisien
                        @endif
                    </p>
                    <div class="mt-3 flex h-1" aria-hidden="true"><span class="flex-1 bg-bps-blue"></span><span class="flex-1 bg-bps-green"></span><span class="flex-1 bg-bps-orange"></span></div>
                </div>

                @if (in_array($mode, ['assign', 'delegate', 'edit']) && ($mode !== 'edit' || $u->canAssign()))
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="label mb-0">{{ $mode === 'edit' ? 'Pelaksana' : 'Penerima tugas' }}</label>
                            @if (count($assignees))<span class="text-xs font-semibold text-bps-blue-700">{{ count($assignees) }} dipilih</span>@endif
                        </div>
                        <input wire:model.live.debounce.250ms="assigneeSearch" type="search" placeholder="Cari nama / jabatan" class="input mt-1.5 py-2">
                        <div class="mt-2 max-h-72 space-y-0.5 overflow-y-auto rounded-xl border border-line p-1">
                            @foreach ($this->candidates as $c)
                                @php $sel = in_array($c->id, $assignees); @endphp
                                <button type="button" wire:key="cand-{{ $c->id }}" wire:click="toggleAssignee({{ $c->id }})"
                                        class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left {{ $sel ? 'bg-bps-blue-50' : 'hover:bg-paper' }}">
                                    <x-avatar :user="$c" size="xs" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium">{{ $c->display_name }}{{ $c->id === $u->id ? ' (saya)' : '' }}</span>
                                        <span class="block truncate text-[11px] text-navy-500">{{ $c->teams->pluck('name')->join(', ') ?: ($c->jabatan ?: '@'.$c->username) }}</span>
                                    </span>
                                    <span class="grid size-5 place-items-center rounded-md border {{ $sel ? 'border-bps-blue bg-bps-blue text-white' : 'border-navy-100' }}">@if ($sel)<x-icon name="check" class="size-3.5" />@endif</span>
                                </button>
                            @endforeach
                        </div>
                        @error('assignees') <p class="error">{{ $message }}</p> @enderror
                        @if ($mode === 'assign' && count($assignees) > 1)
                            <p class="hint">Setiap penerima mendapat tugas sendiri, sehingga poinnya tercatat per orang.</p>
                        @endif
                    </div>
                @endif
            </div>
        </form>

        <x-slot:footer>
            <button type="button" wire:click="close" class="btn btn-ghost">Batal</button>
            <button type="submit" form="task-form" class="btn btn-blue" wire:loading.attr="disabled" wire:target="save,newSurat,newDokumen">
                <span wire:loading.remove wire:target="save">{{ ['assign' => 'Kirim tugas', 'mandiri' => 'Kirim pengajuan', 'delegate' => 'Teruskan tugas', 'edit' => 'Simpan perubahan'][$mode] }}</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </x-slot:footer>
    </x-modal>
</div>
