<div x-data="{ open: false }" class="relative" wire:poll.30s>
    <button @click="open = !open" class="relative rounded-xl p-2 text-navy-700 hover:bg-white" aria-label="Notifikasi">
        <x-icon name="bell" class="size-6" />
        @if ($count)
            <span class="absolute top-1 right-1 grid min-w-4.5 place-items-center rounded-full bg-bps-orange px-1 text-[10px] font-bold text-white ring-2 ring-paper">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right
         class="fixed inset-x-3 top-16 z-50 rounded-2xl border border-line bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-96">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="font-bold">Notifikasi</p>
            @if ($count)<button wire:click="markAllRead" class="text-xs font-semibold text-bps-blue-700 hover:underline">Tandai semua dibaca</button>@endif
        </div>
        <div class="max-h-[60vh] divide-y divide-line overflow-y-auto">
            @forelse ($items as $n)
                <button wire:click="open('{{ $n->id }}')" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-paper/70 {{ $n->read_at ? '' : 'bg-bps-blue-50/40' }}">
                    @include('livewire.notifications.partials-item', ['n' => $n])
                </button>
            @empty
                <x-empty icon="bell" title="Belum ada notifikasi" />
            @endforelse
        </div>
        <a href="{{ route('notifications') }}" wire:navigate class="block border-t border-line px-4 py-3 text-center text-sm font-semibold text-navy-700 hover:bg-paper/70">Lihat semua & atur notifikasi HP</a>
    </div>
</div>
