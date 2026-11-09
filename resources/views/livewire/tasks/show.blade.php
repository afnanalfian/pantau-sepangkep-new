@php
    $t = $task;
    $can = $this->can;
    $att = $this->attachmentsByCategory;
    $due = $t->effectiveDueDate();
    $ooo = $this->reviewerOutOfOffice;
    $ext = $this->pendingExtension;
@endphp
<div>
    {{-- Breadcrumb --}}
    <nav class="mb-4 flex items-center gap-1.5 text-sm text-navy-500">
        <a href="{{ route('tasks.index') }}" wire:navigate class="hover:text-navy-900">Tugas</a>
        @if ($t->parent)
            <x-icon name="chevron-right" class="size-3.5" />
            <a href="{{ route('tasks.show', $t->parent) }}" wire:navigate class="max-w-40 truncate hover:text-navy-900">{{ $t->parent->code }}</a>
        @endif
        <x-icon name="chevron-right" class="size-3.5" />
        <span class="font-medium text-navy-900">{{ $t->code }}</span>
    </nav>

    @if ($t->trashed())
        <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">Tugas ini sudah dihapus dan hanya ditampilkan sebagai arsip.</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem] [&>*]:min-w-0">
        {{-- ============ Kolom utama ============ --}}
        <div class="min-w-0 space-y-5">
            <section class="card card-pad sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status :status="$t->status" />
                    <x-priority :priority="$t->priority" />
                    <span class="chip bg-navy-50 text-navy-700 ring-line">{{ \App\Models\Task::TYPES[$t->type] ?? $t->type }}</span>
                    @if ($t->isOverdue())<span class="chip bg-rose-50 text-rose-700 ring-rose-600/20">Terlambat – poin dibekukan</span>@endif
                </div>
                <h1 class="mt-3 text-xl leading-snug font-extrabold tracking-tight sm:text-2xl">{{ $t->title }}</h1>

                @if ($t->description)
                    <div class="mt-4 text-sm leading-relaxed whitespace-pre-line text-navy-700">{{ $t->description }}</div>
                @else
                    <p class="mt-4 text-sm text-navy-300">Tidak ada uraian tambahan.</p>
                @endif

                {{-- Pemberitahuan pengalihan verifikator --}}
                @if ($ooo && in_array($t->status, ['review', 'pengajuan', 'in_progress', 'todo', 'revisi']) && $can['assignee'])
                    <div class="mt-5 flex gap-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-900">
                        <x-icon name="plane" class="size-5 shrink-0" />
                        <p>{{ $t->verifier->display_name }} sedang <b>{{ $ooo->type_label }}</b> sampai {{ tgl($ooo->end_date) }}. Verifikasi tugas Anda otomatis dialihkan ke pimpinan saat diajukan.</p>
                    </div>
                @endif

                {{-- Bar aksi --}}
                <div class="mt-6 flex flex-wrap gap-2 border-t border-line pt-5">
                    @if ($can['start'])
                        <button wire:click="start" class="btn btn-blue"><x-icon name="play" class="size-4" /> Mulai kerjakan</button>
                    @endif
                    @if ($can['submit'])
                        <button wire:click="openAction('submit')" class="btn btn-green"><x-icon name="check" class="size-4" /> Tandai selesai</button>
                    @endif
                    @if ($can['review'])
                        <button wire:click="openAction('approve')" class="btn btn-green"><x-icon name="check-circle" class="size-4" /> Setujui hasil</button>
                        <button wire:click="openAction('revise')" class="btn btn-orange"><x-icon name="rotate" class="size-4" /> Minta revisi</button>
                    @endif
                    @if ($can['decideSubmission'])
                        <button wire:click="openAction('accept')" class="btn btn-green"><x-icon name="check" class="size-4" /> Setujui pengajuan</button>
                        <button wire:click="openAction('reject')" class="btn btn-danger"><x-icon name="x" class="size-4" /> Tolak</button>
                    @endif
                    @if ($can['delegate'])
                        <button @click="$dispatch('open-task-form', { mode: 'delegate', parent: {{ $t->id }} })" class="btn btn-ghost"><x-icon name="forward" class="size-4" /> Teruskan ke anggota</button>
                    @endif

                    <div class="ml-auto flex flex-wrap gap-2" x-data="{ more: false }">
                        @if ($can['edit'])
                            <button @click="$dispatch('open-task-form', { mode: 'edit', task: {{ $t->id }} })" class="btn btn-ghost"><x-icon name="pencil" class="size-4" /> Ubah</button>
                        @endif
                        @if ($can['extension'] || $can['cancel'] || $can['reopen'] || $can['delete'])
                            <div class="relative">
                                <button @click="more = !more" class="btn btn-ghost px-2.5" aria-label="Aksi lainnya"><x-icon name="dots" class="size-5" /></button>
                                <div x-show="more" x-cloak @click.outside="more = false" class="absolute right-0 z-20 mt-2 w-56 rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                                    @if ($can['extension'])<button wire:click="openAction('extension')" @click="more=false" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-navy-50"><x-icon name="clock" class="size-4" /> Ajukan perpanjangan</button>@endif
                                    @if ($can['reopen'])<button wire:click="openAction('reopen')" @click="more=false" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-navy-50"><x-icon name="rotate" class="size-4" /> Buka kembali</button>@endif
                                    @if ($can['cancel'])<button wire:click="openAction('cancel')" @click="more=false" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-bps-orange-700 hover:bg-bps-orange-50"><x-icon name="x" class="size-4" /> Batalkan tugas</button>@endif
                                    @if ($can['delete'])<button wire:click="openAction('delete')" @click="more=false" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50"><x-icon name="trash" class="size-4" /> Hapus tugas</button>@endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Permintaan perpanjangan menunggu --}}
            @if ($ext)
                <section class="card flex flex-col gap-3 border-amber-200 bg-amber-50/50 p-4 sm:flex-row sm:items-center">
                    <x-icon name="clock" class="size-6 text-amber-600" />
                    <div class="flex-1 text-sm">
                        <p><b>{{ $t->assignee->display_name }}</b> mengajukan perpanjangan sampai <b>{{ tgl($ext['until']) }}</b></p>
                        <p class="text-navy-500">Alasan: {{ $ext['reason'] }}</p>
                    </div>
                    @if ($can['decideExtension'])
                        <div class="flex gap-2">
                            <button wire:click="decideExtension(true)" class="btn btn-green btn-sm">Setujui</button>
                            <button wire:click="decideExtension(false)" class="btn btn-ghost btn-sm">Tolak</button>
                        </div>
                    @else
                        <span class="chip bg-amber-100 text-amber-800 ring-amber-600/20">Menunggu keputusan</span>
                    @endif
                </section>
            @endif

            {{-- Sub-tugas --}}
            @if ($this->children->isNotEmpty())
                <section class="card">
                    <header class="flex items-center justify-between px-5 pt-4 pb-2">
                        <h3 class="text-sm font-bold">Diteruskan ke anggota</h3>
                        <span class="text-xs text-navy-500">{{ $this->children->where('status', 'selesai')->count() }}/{{ $this->children->count() }} selesai</span>
                    </header>
                    <ul class="divide-y divide-line">
                        @foreach ($this->children as $c)
                            <li><a href="{{ route('tasks.show', $c) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-paper/70">
                                <x-avatar :user="$c->assignee" size="sm" />
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ $c->assignee->display_name }}</p><p class="truncate text-xs text-navy-500">{{ $c->code }} · {{ poin($c->points) }} poin</p></div>
                                <x-status :status="$c->status" />
                            </a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Lampiran --}}
            @include('livewire.tasks.partials.attachments', ['cat' => 'output', 'items' => $att->get('output', collect()), 'prop' => 'uploadOutput', 'allowUpload' => $can['uploadOutput'] || ($can['manage'] && $t->status !== 'selesai'), 'accept' => null, 'folder' => true])
            @include('livewire.tasks.partials.attachments', ['cat' => 'surat_tugas', 'items' => $att->get('surat_tugas', collect()), 'prop' => 'uploadSurat', 'allowUpload' => $can['upload'], 'accept' => '.pdf,.doc,.docx,.odt,image/*', 'folder' => false])
            @include('livewire.tasks.partials.attachments', ['cat' => 'dokumen', 'items' => $att->get('dokumen', collect()), 'prop' => 'uploadDokumen', 'allowUpload' => $can['upload'], 'accept' => null, 'folder' => true])
            @include('livewire.tasks.partials.attachments', ['cat' => 'gambar', 'items' => $att->get('gambar', collect()), 'prop' => 'uploadGambar', 'allowUpload' => $can['upload'], 'accept' => 'image/*', 'folder' => false])

            {{-- Diskusi & riwayat --}}
            <section class="card">
                <h3 class="px-5 pt-4 text-sm font-bold">Diskusi & riwayat</h3>
                <form wire:submit="postComment" class="flex items-start gap-3 px-5 pt-3 pb-4">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <div class="flex-1">
                        <textarea wire:model="comment" rows="2" class="input" placeholder="Tulis komentar atau pertanyaan…"></textarea>
                        @error('comment') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <button class="btn btn-primary px-3" aria-label="Kirim komentar"><x-icon name="send" class="size-4" /></button>
                </form>
                <ol class="border-t border-line px-5 py-4">
                    @foreach ($this->timeline as $e)
                        @if ($e['type'] === 'comment')
                            @php $cm = $e['item']; @endphp
                            <li class="flex gap-3 pb-4">
                                <x-avatar :user="$cm->user" size="sm" />
                                <div class="min-w-0 flex-1 rounded-2xl px-4 py-2.5 {{ $cm->type === 'revisi' ? 'bg-bps-orange-50 ring-1 ring-bps-orange/30' : 'bg-paper' }}">
                                    <p class="text-xs text-navy-500"><b class="text-navy-900">{{ $cm->user?->display_name }}</b>{{ $cm->type === 'revisi' ? ' · catatan revisi' : '' }} · {{ $cm->created_at->diffForHumans() }}</p>
                                    <p class="mt-1 text-sm whitespace-pre-line">{{ $cm->body }}</p>
                                </div>
                            </li>
                        @else
                            @php $a = $e['item']; @endphp
                            <li class="flex items-center gap-3 pb-3 pl-2.5 text-xs text-navy-500">
                                <span class="size-2.5 shrink-0 rounded-full {{ $a->action === 'sla_breach' || $a->action === 'escalated' ? 'bg-rose-400' : ($a->action === 'status' ? 'bg-bps-blue' : 'bg-navy-100') }}"></span>
                                <p class="flex-1"><b class="text-navy-700">{{ $a->user?->display_name ?? 'Sistem' }}</b> {{ $a->description }}</p>
                                <time title="{{ $a->created_at->format('d/m/Y H:i') }}">{{ $a->created_at->diffForHumans() }}</time>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- ============ Panel samping ============ --}}
        <aside class="space-y-5">
            <section class="card overflow-hidden">
                <div class="bg-navy-900 p-5 text-white">
                    <p class="text-xs text-navy-100/70">Bobot tugas</p>
                    <p class="text-4xl font-extrabold">{{ poin($t->points) }} <span class="text-base font-semibold text-navy-100/70">poin</span></p>
                    <p class="mt-1 text-xs text-navy-100/70">
                        @if ($t->catalog) {{ poin($t->quantity) }} {{ $t->catalog->unit }} × {{ poin($t->catalog->points) }} poin @else {{ poin($t->quantity) }} jam × koef. {{ \App\Models\Task::COMPLEXITY[$t->complexity]['coef'] ?? 1 }} @endif
                    </p>
                </div>
                <dl class="divide-y divide-line text-sm">
                    @foreach ([['Pelaksana', $t->assignee], ['Pemberi tugas', $t->creator], ['Verifikator', $t->verifier]] as [$label, $person])
                        <div class="flex items-center gap-3 px-5 py-3">
                            <dt class="w-28 text-navy-500">{{ $label }}</dt>
                            <dd class="flex min-w-0 flex-1 items-center gap-2">
                                @if ($person)<x-avatar :user="$person" size="xs" /><span class="truncate font-medium">{{ $person->display_name }}</span>@else<span class="text-navy-300">Otomatis</span>@endif
                            </dd>
                        </div>
                    @endforeach
                    <div class="flex px-5 py-3"><dt class="w-28 text-navy-500">Tim</dt><dd class="font-medium">{{ $t->team?->name ?? '–' }}</dd></div>
                    @if ($t->catalog)<div class="flex px-5 py-3"><dt class="w-28 shrink-0 text-navy-500">Kegiatan</dt><dd class="font-medium">{{ $t->catalog->name }}@if($t->catalog->rhk)<span class="mt-0.5 block text-xs font-normal text-navy-500">RHK: {{ $t->catalog->rhk }}</span>@endif</dd></div>@endif
                    <div class="flex px-5 py-3"><dt class="w-28 text-navy-500">Mulai</dt><dd class="font-medium">{{ tgl($t->start_date) }}</dd></div>
                    <div class="flex px-5 py-3"><dt class="w-28 text-navy-500">Deadline</dt>
                        <dd class="font-medium {{ $t->isOverdue() ? 'text-rose-600' : '' }}">
                            {{ $due ? tgl($due) : '–' }}
                            @if ($t->extension_until)<span class="block text-xs font-normal text-navy-500">diperpanjang dari {{ tgl($t->due_date) }}</span>@endif
                            @if ($due && ! in_array($t->status, ['selesai', 'batal']))<span class="block text-xs font-normal text-navy-500">{{ $due->copy()->endOfDay()->diffForHumans() }}</span>@endif
                        </dd>
                    </div>
                    @if ($t->completed_at)<div class="flex px-5 py-3"><dt class="w-28 text-navy-500">Selesai</dt><dd class="font-medium text-bps-green-700">{{ tgl($t->completed_at, 'j M Y H:i') }}</dd></div>@endif
                </dl>
            </section>

            {{-- Progres --}}
            @if (! in_array($t->status, ['selesai', 'batal', 'ditolak', 'pengajuan']))
                <section class="card card-pad" x-data="{ p: @entangle('progress') }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold">Progres pengerjaan</h3>
                        <span class="text-lg font-extrabold" x-text="p + '%'"></span>
                    </div>
                    @if ($can['assignee'])
                        <input type="range" min="0" max="100" step="5" x-model="p" class="mt-3 w-full accent-bps-blue" @change="$wire.saveProgress()">
                        <p class="hint">Geser untuk memperbarui progres harian.</p>
                    @else
                        <div class="slant-track mt-3 h-3"><div class="absolute inset-y-0 left-0 bg-bps-blue" :style="'width:' + p + '%'"></div></div>
                    @endif
                </section>
            @endif

            @if ($t->drive_folder_url)
                <a href="{{ $t->drive_folder_url }}" target="_blank" class="card flex items-center gap-3 p-4 hover:border-navy-300">
                    <span class="grid size-10 place-items-center rounded-xl bg-bps-green-50 text-bps-green-700"><x-icon name="cloud" /></span>
                    <span class="flex-1 text-sm"><b class="block">Folder Google Drive</b><span class="text-navy-500">Semua lampiran tugas ini</span></span>
                    <x-icon name="external" class="size-4 text-navy-300" />
                </a>
            @endif
        </aside>
    </div>

    {{-- ============ Modal aksi ============ --}}
    @php
        $modals = [
            'submit' => ['Tandai tugas selesai', 'Hasil kerja akan dikirim ke '.($t->verifier?->display_name ?? 'verifikator').' untuk diverifikasi.', 'Kirim untuk verifikasi', 'btn-green', 'Catatan untuk verifikator (opsional)'],
            'approve' => ['Setujui hasil kerja', 'Poin '.poin($t->points).' akan tercatat sebagai poin terverifikasi '.$t->assignee->display_name.'.', 'Setujui', 'btn-green', 'Catatan (opsional)'],
            'revise' => ['Minta revisi', 'Jelaskan apa yang perlu diperbaiki. Tugas kembali ke pelaksana.', 'Kirim permintaan revisi', 'btn-orange', 'Catatan revisi (wajib)'],
            'accept' => ['Setujui pengajuan mandiri', 'Tugas masuk ke daftar kerja '.$t->assignee->display_name.' dan poinnya dihitung setelah selesai diverifikasi.', 'Setujui pengajuan', 'btn-green', null],
            'reject' => ['Tolak pengajuan', 'Beri alasan penolakan agar pegawai memahami.', 'Tolak pengajuan', 'btn-danger', 'Alasan (wajib)'],
            'cancel' => ['Batalkan tugas', 'Tugas tidak lagi dihitung dalam beban kerja.', 'Batalkan tugas', 'btn-danger', 'Alasan (opsional)'],
            'reopen' => ['Buka kembali tugas', 'Status kembali menjadi “Sedang dikerjakan” dan poin terverifikasi ditarik.', 'Buka kembali', 'btn-primary', null],
            'extension' => ['Ajukan perpanjangan waktu', 'Selama tugas terlambat, poinnya dibekukan dari proyeksi sampai perpanjangan disetujui.', 'Kirim pengajuan', 'btn-blue', 'Alasan (wajib)'],
            'delete' => ['Hapus tugas', 'Tugas dihapus dari daftar. Riwayat tetap tersimpan untuk audit.', 'Hapus', 'btn-danger', null],
        ];
        $m = $actionModal ? $modals[$actionModal] : null;
    @endphp
    <x-modal :show="(bool) $m" close="$set('actionModal', null)" :title="$m[0] ?? ''" :subtitle="$m[1] ?? null">
        @if ($actionModal === 'submit' && ($att->get('output', collect())->isEmpty()))
            <div class="mb-4 flex gap-2 rounded-xl bg-bps-orange-50 p-3 text-sm text-bps-orange-700"><x-icon name="alert" class="size-5 shrink-0" /> Belum ada output/bukti dukung. Unggah file, gambar, atau link di seksi “Output / hasil kerja” terlebih dahulu.</div>
        @endif
        @if ($actionModal === 'extension')
            <label class="label">Perpanjang sampai</label>
            <input wire:model="extensionDate" type="date" class="input mb-4 @error('extensionDate') input-error @enderror">
            @error('extensionDate') <p class="error -mt-3 mb-3">{{ $message }}</p> @enderror
        @endif
        @if ($m && $m[4])
            <label class="label">{{ $m[4] }}</label>
            <textarea wire:model="actionNote" rows="3" class="input @error('actionNote') input-error @enderror"></textarea>
            @error('actionNote') <p class="error">{{ $message }}</p> @enderror
        @endif
        <x-slot:footer>
            <button wire:click="$set('actionModal', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="confirmAction" class="btn {{ $m[3] ?? 'btn-primary' }}" wire:loading.attr="disabled">{{ $m[2] ?? 'OK' }}</button>
        </x-slot:footer>
    </x-modal>
</div>
