{{-- Satu seksi lampiran per kategori. Vars: $cat, $items, $prop, $allowUpload, $accept, $folder (bool) --}}
@php
    $meta = \App\Models\TaskAttachment::CATEGORIES[$cat];
    $accent = ['surat_tugas' => 'text-bps-orange-600 bg-bps-orange-50', 'dokumen' => 'text-bps-blue-700 bg-bps-blue-50', 'gambar' => 'text-violet-600 bg-violet-50', 'output' => 'text-bps-green-700 bg-bps-green-50'][$cat];
    $icon = ['surat_tugas' => 'document', 'dokumen' => 'clip', 'gambar' => 'photo', 'output' => 'check-circle'][$cat];
    $images = $items->filter->isImage();
    $others = $cat === 'gambar' ? $items->reject->isImage() : $items;
@endphp
<section class="card" x-data="{ over: false }"
         @if ($allowUpload)
         x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false"
         x-on:drop.prevent="over = false; $wire.uploadMultiple('{{ $prop }}', $event.dataTransfer.files)"
         @endif
         :class="over && 'ring-2 ring-bps-blue ring-offset-2'">
    <header class="flex flex-wrap items-center gap-3 px-5 pt-4 pb-3">
        <span class="grid size-8 place-items-center rounded-lg {{ $accent }}"><x-icon :name="$icon" class="size-4.5" /></span>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-bold">{{ $meta['label'] }} <span class="font-medium text-navy-300">({{ $items->count() }})</span></h3>
            @if ($cat === 'output')<p class="text-xs text-navy-500">Wajib diisi sebelum mengajukan selesai: PDF, Word, gambar, atau link.</p>@endif
        </div>
        @if ($allowUpload)
            <div class="flex flex-wrap gap-1.5">
                <label class="btn btn-ghost btn-sm cursor-pointer">
                    <x-icon name="upload" class="size-3.5" /> {{ $cat === 'gambar' ? 'Gambar' : 'File' }}
                    <input type="file" wire:model="{{ $prop }}" multiple class="sr-only" @if($accept) accept="{{ $accept }}" @endif>
                </label>
                @if ($folder)
                    <label class="btn btn-ghost btn-sm cursor-pointer"
                           x-on:change.capture="$wire.folderName = ($event.target.files[0]?.webkitRelativePath || '').split('/')[0]; $wire.folderCategory = '{{ $cat }}'">
                        <x-icon name="folder" class="size-3.5" /> Folder
                        <input type="file" wire:model="uploadFolder" webkitdirectory directory multiple class="sr-only">
                    </label>
                @endif
                @if ($cat !== 'gambar')
                    <button type="button" wire:click="openLink('{{ $cat }}')" class="btn btn-ghost btn-sm"><x-icon name="link" class="size-3.5" /> Link</button>
                @endif
            </div>
        @endif
    </header>

    <div wire:loading.flex wire:target="{{ $prop }}{{ $folder ? ',uploadFolder' : '' }}" class="mx-5 mb-3 items-center gap-2 rounded-lg bg-bps-blue-50 px-3 py-2 text-xs font-medium text-bps-blue-700">
        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"/></svg>
        Mengunggah file…
    </div>
    @error($prop.'.*') <p class="error mx-5 mb-3">{{ $message }}</p> @enderror

    @if ($linkCategory === $cat)
        <form wire:submit="saveLink" class="mx-5 mb-3 flex flex-col gap-2 rounded-xl bg-paper p-3 sm:flex-row">
            <input wire:model="linkUrl" class="input flex-1 py-2" placeholder="https://…" autofocus>
            <input wire:model="linkName" class="input py-2 sm:w-44" placeholder="Nama (opsional)">
            <div class="flex gap-2">
                <button class="btn btn-blue btn-sm">Simpan</button>
                <button type="button" wire:click="$set('linkCategory', '')" class="btn btn-quiet btn-sm">Batal</button>
            </div>
            @error('linkUrl') <p class="error">{{ $message }}</p> @enderror
        </form>
    @endif

    @if ($items->isEmpty())
        <p class="px-5 pb-5 text-sm text-navy-300">{{ $allowUpload ? 'Belum ada. Klik tombol di atas atau seret file ke kotak ini.' : 'Belum ada lampiran.' }}</p>
    @else
        @if ($images->isNotEmpty())
            <div class="grid grid-cols-3 gap-2 px-5 pb-4 sm:grid-cols-4">
                @foreach ($images as $a)
                    <div class="group relative aspect-square overflow-hidden rounded-xl bg-navy-50" wire:key="att-{{ $a->id }}">
                        <a href="{{ $a->previewUrl() }}" target="_blank"><img src="{{ $a->previewUrl() }}" alt="{{ $a->name }}" loading="lazy" class="size-full object-cover transition group-hover:scale-105"></a>
                        @if ($a->user_id === auth()->id() || $this->can['manage'])
                            <button wire:click="deleteAttachment({{ $a->id }})" wire:confirm="Hapus gambar ini?" class="absolute top-1.5 right-1.5 hidden rounded-lg bg-white/90 p-1 text-rose-600 group-hover:block"><x-icon name="trash" class="size-4" /></button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        @if ($others->isNotEmpty())
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($others->groupBy(fn ($a) => $a->folder_label ?: '') as $folderLabel => $group)
                    @if ($folderLabel)
                        <li class="flex items-center gap-2 bg-paper/60 px-5 py-2 text-xs font-semibold text-navy-700"><x-icon name="folder" class="size-4 text-bps-orange" /> {{ $folderLabel }} <span class="font-normal text-navy-500">({{ $group->count() }} file)</span></li>
                    @endif
                    @foreach ($group as $a)
                        <li class="flex items-center gap-3 px-5 py-2.5 {{ $folderLabel ? 'pl-9' : '' }}" wire:key="att-{{ $a->id }}">
                            <x-file-icon :type="$a->iconType()" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ $a->openUrl() }}" target="_blank" class="block truncate text-sm font-medium hover:text-bps-blue-700">{{ $a->name }}</a>
                                <p class="truncate text-xs text-navy-500">
                                    {{ $a->user?->display_name }} · {{ $a->created_at->diffForHumans() }}{{ $a->humanSize() ? ' · '.$a->humanSize() : '' }}
                                    @if ($a->kind === 'file')
                                        @switch($a->sync_status)
                                            @case('synced') · <span class="text-bps-green-700">tersimpan di Drive</span> @break
                                            @case('pending') · <span class="text-bps-blue-700">mengunggah ke Drive…</span> @break
                                            @case('failed') · <span class="text-rose-600" title="{{ $a->sync_error }}">gagal ke Drive</span>
                                                <button wire:click="retrySync({{ $a->id }})" class="font-semibold text-rose-700 underline">ulangi</button> @break
                                        @endswitch
                                    @endif
                                </p>
                            </div>
                            @if ($a->kind === 'file' && $a->url)
                                <a href="{{ $a->url }}" target="_blank" class="btn btn-quiet hidden p-1.5 sm:inline-flex" title="Buka di Google Drive"><x-icon name="external" class="size-4" /></a>
                            @endif
                            @if ($a->kind === 'file')
                                <a href="{{ route('attachments.show', $a) }}" class="btn btn-quiet p-1.5" title="Unduh"><x-icon name="download" class="size-4" /></a>
                            @endif
                            @if ($a->user_id === auth()->id() || $this->can['manage'])
                                <button wire:click="deleteAttachment({{ $a->id }})" wire:confirm="Hapus lampiran “{{ $a->name }}”?" class="btn btn-quiet p-1.5 hover:text-rose-600" title="Hapus"><x-icon name="trash" class="size-4" /></button>
                            @endif
                        </li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    @endif
</section>
