@php $c = $this->counts; $u = auth()->user(); @endphp
<div>
    <x-page-header title="Persetujuan & verifikasi" subtitle="Batas waktu tinjauan {{ \App\Support\Settings::get('sla_hours') }} jam kerja. Lewat batas, tugas otomatis {{ \App\Support\Settings::get('sla_action') === 'auto_approve' ? 'disetujui sistem' : 'dieskalasi ke pimpinan' }}." />

    <div class="mb-5 flex gap-1 overflow-x-auto">
        @foreach (['review' => ['Hasil kerja', $c['review']], 'pengajuan' => ['Pengajuan mandiri', $c['pengajuan']], 'perpanjangan' => ['Perpanjangan waktu', $c['perpanjangan']]] as $k => [$label, $n])
            <button wire:click="$set('tab', '{{ $k }}')" class="tab inline-flex shrink-0 items-center gap-2 {{ $tab === $k ? 'active' : '' }}">{{ $label }}
                @if ($n)<span class="rounded-full bg-bps-orange px-1.5 text-[11px] font-bold text-white">{{ $n }}</span>@endif</button>
        @endforeach
        @if ($u->is_pimpinan)
            <button wire:click="$set('tab', 'semua')" class="tab shrink-0 {{ $tab === 'semua' ? 'active' : '' }}">Semua antrean kantor</button>
        @endif
    </div>

    <div class="space-y-3">
        @forelse ($this->items as $t)
            @php $sla = $tab !== 'perpanjangan' ? $this->slaDeadline($t) : null; @endphp
            <article class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5" wire:key="ap-{{ $t->id }}">
                <x-avatar :user="$t->assignee" />
                <div class="min-w-0 flex-1">
                    <a href="{{ route('tasks.show', $t) }}" wire:navigate class="font-semibold hover:text-bps-blue-700">{{ $t->title }}</a>
                    <p class="mt-0.5 text-sm text-navy-500">{{ $t->assignee->display_name }} · {{ $t->code }} · <b class="text-navy-900">{{ poin($t->points) }} poin</b>
                        @if ($tab !== 'perpanjangan') · {{ $t->attachments_count }} lampiran @endif</p>
                    @if ($tab === 'perpanjangan')
                        @php $ext = app(\App\Services\TaskService::class)->pendingExtension($t); @endphp
                        <p class="mt-1 text-sm">Minta sampai <b>{{ tgl($ext['until']) }}</b> (deadline {{ tgl($t->due_date) }}) — {{ $ext['reason'] }}</p>
                    @elseif ($sla)
                        <p class="mt-1 text-xs {{ $sla->isPast() ? 'font-semibold text-rose-600' : 'text-navy-500' }}">
                            Diajukan {{ $t->submitted_at->diffForHumans() }} · batas tinjau {{ $sla->isPast() ? 'terlewati' : $sla->diffForHumans() }}
                            @if ($tab === 'semua') · verifikator {{ $t->verifier?->display_name }} @endif
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($tab === 'perpanjangan')
                        <button wire:click="decideExtension({{ $t->id }}, true)" class="btn btn-green btn-sm">Setujui</button>
                        <button wire:click="decideExtension({{ $t->id }}, false)" class="btn btn-ghost btn-sm">Tolak</button>
                    @else
                        <a href="{{ route('tasks.show', $t) }}" wire:navigate class="btn btn-ghost btn-sm"><x-icon name="eye" class="size-4" /> Periksa</a>
                        <button wire:click="approve({{ $t->id }})" wire:confirm="Setujui {{ $t->status === 'pengajuan' ? 'pengajuan' : 'hasil kerja' }} ini?" class="btn btn-green btn-sm"><x-icon name="check" class="size-4" /> Setujui</button>
                        <button wire:click="openNote({{ $t->id }}, '{{ $t->status === 'pengajuan' ? 'reject' : 'revise' }}')" class="btn btn-sm {{ $t->status === 'pengajuan' ? 'btn-danger' : 'btn-orange' }}">{{ $t->status === 'pengajuan' ? 'Tolak' : 'Revisi' }}</button>
                    @endif
                </div>
            </article>
        @empty
            <div class="card"><x-empty icon="check-circle" title="Semua beres" text="Tidak ada yang menunggu tindakan Anda di sini." /></div>
        @endforelse
    </div>

    <x-modal :show="(bool) $noteFor" close="$set('noteFor', null)" :title="$noteType === 'reject' ? 'Tolak pengajuan' : 'Minta revisi'">
        <label class="label">{{ $noteType === 'reject' ? 'Alasan penolakan' : 'Apa yang perlu diperbaiki?' }}</label>
        <textarea wire:model="note" rows="4" class="input @error('note') input-error @enderror"></textarea>
        @error('note') <p class="error">{{ $message }}</p> @enderror
        <x-slot:footer>
            <button wire:click="$set('noteFor', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="submitNote" class="btn {{ $noteType === 'reject' ? 'btn-danger' : 'btn-orange' }}">Kirim</button>
        </x-slot:footer>
    </x-modal>
</div>
