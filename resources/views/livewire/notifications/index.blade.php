<div>
    <x-page-header title="Notifikasi" />
    <div class="grid gap-6 xl:grid-cols-[1fr_24rem] [&>*]:min-w-0">
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                <div class="flex rounded-xl bg-navy-50 p-1">
                    <button wire:click="$set('filter', 'semua')" class="tab {{ $filter === 'semua' ? 'active' : '' }}">Semua</button>
                    <button wire:click="$set('filter', 'belum')" class="tab {{ $filter === 'belum' ? 'active' : '' }}">Belum dibaca</button>
                </div>
                <button wire:click="markAllRead" class="btn btn-quiet btn-sm">Tandai semua dibaca</button>
            </div>
            <div class="divide-y divide-line">
                @forelse ($items as $n)
                    <div class="group flex items-start gap-3 px-4 py-3.5 hover:bg-paper/70 {{ $n->read_at ? '' : 'bg-bps-blue-50/30' }}" wire:key="n-{{ $n->id }}">
                        <button wire:click="open('{{ $n->id }}')" class="flex min-w-0 flex-1 items-start gap-3 text-left">
                            @include('livewire.notifications.partials-item', ['n' => $n])
                        </button>
                        <button wire:click="remove('{{ $n->id }}')" class="hidden rounded-lg p-1 text-navy-300 group-hover:block hover:text-rose-600" aria-label="Hapus"><x-icon name="x" class="size-4" /></button>
                    </div>
                @empty
                    <x-empty icon="bell" title="Tidak ada notifikasi" />
                @endforelse
            </div>
            @if ($items->hasPages())<div class="border-t border-line px-4 py-3">{{ $items->links() }}</div>@endif
        </section>

        {{-- Pengaturan notifikasi HP --}}
        <aside class="space-y-4">
            <section class="card card-pad" x-data="{ state: 'loading', busy: false, msg: '', ok: '',
                async init() { this.state = window.PantauPush ? await PantauPush.status().catch(() => 'unsupported') : 'unsupported' },
                async on() { this.busy = true; this.msg = ''; try { await PantauPush.subscribe(); this.state = 'subscribed'; $wire.$refresh() } catch (e) { this.msg = e.message } this.busy = false },
                async off() { this.busy = true; await PantauPush.unsubscribe(); this.state = 'default'; this.busy = false; $wire.$refresh() },
                async test() { this.busy = true; const r = await PantauPush.test(); this.ok = r.ok ? 'Terkirim. Notifikasi akan muncul dalam beberapa detik.' : (r.message || 'Gagal mengirim.'); this.busy = false } }">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-bps-blue text-white"><x-icon name="phone" /></span>
                    <div><h2 class="font-bold">Notifikasi di perangkat ini</h2><p class="text-xs text-navy-500">Muncul walau aplikasi ditutup</p></div>
                </div>

                @unless ($pushReady)
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Server belum dikonfigurasi untuk push (kunci VAPID kosong). Hubungi admin.</p>
                @endunless

                <div class="mt-4 text-sm">
                    <template x-if="state === 'subscribed'"><p class="flex items-center gap-2 font-semibold text-bps-green-700"><x-icon name="check-circle" /> Aktif di perangkat ini</p></template>
                    <template x-if="state === 'default'"><p class="text-navy-700">Belum aktif. Tekan tombol di bawah lalu pilih <b>Izinkan</b>.</p></template>
                    <template x-if="state === 'denied'"><p class="text-rose-700">Izin notifikasi diblokir. Buka pengaturan situs (ikon gembok di address bar) → Notifikasi → Izinkan, lalu muat ulang halaman.</p></template>
                    <template x-if="state === 'ios-install'"><p class="text-navy-700">Di iPhone/iPad: buka di Safari → ketuk <b>Bagikan</b> → <b>Tambah ke Layar Utama</b>. Buka PANTAU dari ikon di layar utama, lalu aktifkan notifikasi di halaman ini.</p></template>
                    <template x-if="state === 'unsupported'"><p class="text-navy-700">Browser ini belum mendukung notifikasi push. Gunakan Chrome (Android/PC) atau Safari iOS 16.4+.</p></template>
                    <p x-show="msg" x-text="msg" class="mt-2 text-rose-600"></p>
                    <p x-show="ok" x-text="ok" class="mt-2 text-bps-green-700"></p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button x-show="state === 'default'" @click="on" :disabled="busy" class="btn btn-blue">Aktifkan notifikasi</button>
                    <button x-show="state === 'subscribed'" @click="test" :disabled="busy" class="btn btn-ghost">Kirim tes</button>
                    <button x-show="state === 'subscribed'" @click="off" :disabled="busy" class="btn btn-quiet">Matikan</button>
                </div>
            </section>

            <section class="card card-pad">
                <h3 class="text-sm font-bold">Perangkat terdaftar ({{ $devices->count() }})</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($devices as $d)
                        <li class="flex items-center gap-2"><x-icon name="phone" class="size-4 text-navy-300" /><span class="flex-1">{{ $d->device }}</span><span class="text-xs text-navy-500">{{ $d->created_at->diffForHumans() }}</span></li>
                    @empty
                        <li class="text-navy-500">Belum ada perangkat.</li>
                    @endforelse
                </ul>
                <p class="mt-3 text-xs text-navy-500">Saat Anda keluar (logout) dari sebuah perangkat, notifikasi ke perangkat itu otomatis berhenti.</p>
            </section>
        </aside>
    </div>
</div>
