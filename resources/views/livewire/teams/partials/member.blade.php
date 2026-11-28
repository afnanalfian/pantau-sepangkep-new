<li class="flex items-center gap-3 px-5 py-2.5">
    <x-avatar :user="$m" size="sm" />
    <div class="min-w-0 flex-1">
        <p class="flex items-center gap-1.5 truncate text-sm font-medium">{{ $m->display_name }}
            @if ($m->pivot->role === 'ketua')<span class="chip bg-bps-orange-50 text-bps-orange-700 ring-bps-orange/30">Ketua</span>@endif</p>
        @if ($s)<x-completion-bar class="mt-1.5" height="h-1.5" :selesai="$s['tugas_selesai']" :berjalan="$s['tugas_berjalan']" :gagal="$s['tugas_gagal']" />@endif
    </div>
    @if ($s)<span class="w-16 text-right text-xs"><x-pct :value="$s['tugas_pct']" /><span class="block text-[11px] text-navy-500">{{ $s['aktif'] }} aktif</span></span>@endif
</li>
